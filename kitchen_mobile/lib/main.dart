import 'dart:async';

import 'package:flutter/material.dart';

import 'app/kitchen_theme.dart';
import 'core/api_client.dart';
import 'core/kitchen_live_connection.dart';
import 'core/secure_token_store.dart';
import 'data/kitchen_repository.dart';
import 'domain/kitchen_models.dart';
import 'presentation/kitchen_board_screen.dart';
import 'presentation/kitchen_sign_in_screen.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  final tokens = KitchenTokenStore();
  const apiBaseUrl = String.fromEnvironment('API_BASE_URL');
  final apiUri = Uri.tryParse(apiBaseUrl);
  const reverbHost = String.fromEnvironment('REVERB_HOST');
  const reverbScheme = String.fromEnvironment('REVERB_SCHEME');
  final secureReverb = reverbScheme.isEmpty
      ? apiUri?.scheme == 'https'
      : reverbScheme == 'https';
  final api = KitchenApiClient(readToken: tokens.read);
  runApp(
    KitchenMobileApp(
      repository: KitchenRepository(api: api, tokens: tokens),
      liveConnection: KitchenLiveConnection(
        apiBaseUrl: apiBaseUrl,
        appKey: const String.fromEnvironment('REVERB_APP_KEY'),
        host: reverbHost.isEmpty ? apiUri?.host ?? '' : reverbHost,
        port:
            int.tryParse(const String.fromEnvironment('REVERB_PORT')) ??
            (secureReverb ? 443 : 8080),
        secure: secureReverb,
        readToken: tokens.read,
      ),
    ),
  );
}

class KitchenMobileApp extends StatefulWidget {
  const KitchenMobileApp({
    super.key,
    required this.repository,
    required this.liveConnection,
  });

  final KitchenRepository repository;
  final KitchenLiveConnection liveConnection;

  @override
  State<KitchenMobileApp> createState() => _KitchenMobileAppState();
}

class _KitchenMobileAppState extends State<KitchenMobileApp>
    with WidgetsBindingObserver {
  final GlobalKey<ScaffoldMessengerState> _scaffoldMessengerKey =
      GlobalKey<ScaffoldMessengerState>();
  KitchenAccount? _account;
  List<KitchenStation> _kitchens = const [];
  KitchenStation? _selectedKitchen;
  List<KotTicket> _tickets = const [];
  Timer? _refreshTimer;
  int _refreshGeneration = 0;
  bool _restoring = true;
  bool _loadingBoard = false;
  bool _refreshing = false;
  String? _startupError;
  String? _boardError;
  String? _busyTicketId;
  DateTime? _refreshedAt;
  bool _hasBoardSnapshot = false;
  bool _liveConnected = false;
  Map<int, String> _ticketStatuses = const {};

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _restoreSession();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _refreshTimer?.cancel();
    widget.liveConnection.dispose();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed && _account != null) {
      _refreshTickets(notify: true);
      final kitchen = _selectedKitchen;
      if (kitchen != null) _connectLive(kitchen.id);
      _startRefreshTimer();
    } else if (state != AppLifecycleState.resumed) {
      _refreshTimer?.cancel();
      widget.liveConnection.disconnect();
    }
  }

  Future<void> _restoreSession() async {
    setState(() {
      _restoring = true;
      _startupError = null;
    });
    final token = await widget.repository.tokens.read();
    if (token == null || token.isEmpty) {
      if (mounted) setState(() => _restoring = false);
      return;
    }
    try {
      final account = await widget.repository.restoreSession();
      await _openKitchenBoard(account);
    } on KitchenApiException catch (error) {
      if (error.requiresSignIn) {
        await widget.repository.tokens.clear();
        if (mounted) setState(() => _restoring = false);
      } else if (mounted) {
        setState(() {
          _startupError = error.message;
          _restoring = false;
        });
      }
    } on KitchenApiConfigurationException catch (error) {
      if (mounted) {
        setState(() {
          _startupError = error.message;
          _restoring = false;
        });
      }
    } on KitchenApiTransportException catch (error) {
      if (mounted) {
        setState(() {
          _startupError = error.message;
          _restoring = false;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _startupError = 'Could not restore the kitchen session. Retry or sign in again.';
          _restoring = false;
        });
      }
    }
  }

  Future<void> _signIn(String email, String password) async {
    final account = await widget.repository.signIn(email, password);
    await _openKitchenBoard(account);
  }

  Future<void> _openKitchenBoard(KitchenAccount account) async {
    final kitchens = await widget.repository.getKitchens();
    if (!mounted) return;
    setState(() {
      _account = account;
      _kitchens = kitchens;
      _selectedKitchen = kitchens.isEmpty ? null : kitchens.first;
      _tickets = const [];
      _restoring = false;
      _startupError = null;
      _refreshing = false;
      _loadingBoard = false;
      _boardError = null;
      _hasBoardSnapshot = false;
      _ticketStatuses = const {};
      _liveConnected = false;
    });
    if (kitchens.isNotEmpty) {
      await _refreshTickets();
      if (mounted && _account != null) {
        _connectLive(kitchens.first.id);
        _startRefreshTimer();
      }
    }
  }

  void _startRefreshTimer() {
    _refreshTimer?.cancel();
    _refreshTimer = Timer.periodic(
      const Duration(seconds: 12),
      (_) => _refreshTickets(notify: true),
    );
  }

  Future<void> _changeKitchen(KitchenStation kitchen) async {
    if (_selectedKitchen?.id == kitchen.id) return;
    setState(() {
      _selectedKitchen = kitchen;
      _tickets = const [];
      _refreshing = false;
      _loadingBoard = false;
      _hasBoardSnapshot = false;
      _ticketStatuses = const {};
    });
    _connectLive(kitchen.id);
    await _refreshTickets(force: true);
  }

  void _connectLive(int kitchenId) {
    if (mounted) setState(() => _liveConnected = false);
    widget.liveConnection.connect(
      kitchenId: kitchenId,
      onConnectionChanged: (connected) {
        if (mounted) {
          setState(() => _liveConnected = connected);
          if (connected) _refreshTickets(notify: true, force: true);
        }
      },
      onTicketChanged: () => _refreshTickets(notify: true, force: true),
    );
  }

  Future<void> _refreshTickets({
    bool notify = false,
    bool force = false,
  }) async {
    final kitchen = _selectedKitchen;
    if (kitchen == null || (_refreshing && !force) || !mounted) return;
    final generation = ++_refreshGeneration;
    setState(() {
      _refreshing = true;
      _loadingBoard = !_hasBoardSnapshot;
      _boardError = null;
    });
    try {
      final tickets = await widget.repository.getTickets(kitchen.id);
      if (!mounted || generation != _refreshGeneration) return;
      if (_selectedKitchen?.id != kitchen.id) {
        setState(() {
          _loadingBoard = false;
          _refreshing = false;
        });
        return;
      }
      if (notify && _hasBoardSnapshot) _notifyTicketChanges(tickets);
      setState(() {
        _tickets = tickets;
        _ticketStatuses = {
          for (final ticket in tickets) ticket.id: ticket.status,
        };
        _hasBoardSnapshot = true;
        _refreshedAt = DateTime.now();
        _loadingBoard = false;
        _refreshing = false;
      });
    } on KitchenApiException catch (error) {
      if (!mounted || generation != _refreshGeneration) return;
      if (error.requiresSignIn) {
        await _signOut(clearOnly: true);
        return;
      }
      if (mounted) {
        setState(() {
          _boardError = error.message;
          _loadingBoard = false;
          _refreshing = false;
        });
      }
    } on KitchenApiConfigurationException catch (error) {
      if (mounted && generation == _refreshGeneration) {
        setState(() {
          _boardError = error.message;
          _loadingBoard = false;
          _refreshing = false;
        });
      }
    } on KitchenApiTransportException catch (error) {
      if (mounted && generation == _refreshGeneration) {
        setState(() {
          _boardError = error.message;
          _loadingBoard = false;
          _refreshing = false;
        });
      }
    }
  }

  void _notifyTicketChanges(List<KotTicket> tickets) {
    final added = tickets.where(
      (ticket) =>
          !_ticketStatuses.containsKey(ticket.id) && ticket.status == 'new',
    );
    final cancelled = tickets.where(
      (ticket) =>
          ticket.status == 'cancelled' &&
          _ticketStatuses[ticket.id] != 'cancelled',
    );
    final notices = <String>[];
    if (added.isNotEmpty) notices.add('New KOT ${added.first.number} received');
    if (cancelled.isNotEmpty) {
      notices.add('KOT ${cancelled.first.number} was cancelled');
    }
    final text = notices.isEmpty ? null : notices.join(' · ');
    final messenger = _scaffoldMessengerKey.currentState;
    if (text == null || !mounted || messenger == null) return;
    messenger
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(text)));
  }

  Future<void> _updateTicket(KotTicket ticket, String status) async {
    setState(() => _busyTicketId = '${ticket.id}');
    try {
      await widget.repository.updateStatus(ticket.id, status);
      await _refreshTickets(force: true);
      if (mounted) {
        _scaffoldMessengerKey.currentState?.showSnackBar(
          SnackBar(
            content: Text('${ticket.number} moved to ${_statusLabel(status)}'),
          ),
        );
      }
    } on KitchenApiException catch (error) {
      if (mounted) {
        _scaffoldMessengerKey.currentState?.showSnackBar(
          SnackBar(content: Text(error.message)),
        );
        if (error.statusCode == 409) await _refreshTickets(force: true);
        if (error.requiresSignIn) await _signOut(clearOnly: true);
      }
    } on KitchenApiTransportException catch (error) {
      if (mounted) {
        _scaffoldMessengerKey.currentState?.showSnackBar(
          SnackBar(content: Text(error.message)),
        );
      }
    } finally {
      if (mounted) setState(() => _busyTicketId = null);
    }
  }

  Future<void> _signOut({bool clearOnly = false}) async {
    _refreshTimer?.cancel();
    _refreshGeneration++;
    widget.liveConnection.disconnect();
    if (clearOnly) {
      await widget.repository.tokens.clear();
    } else {
      try {
        await widget.repository.signOut();
      } catch (_) {
        // The repository clears local credentials even if the API cannot be reached.
      }
    }
    if (!mounted) return;
    setState(() {
      _account = null;
      _kitchens = const [];
      _selectedKitchen = null;
      _tickets = const [];
      _boardError = null;
      _busyTicketId = null;
      _loadingBoard = false;
      _refreshing = false;
      _hasBoardSnapshot = false;
      _ticketStatuses = const {};
      _liveConnected = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Nagarjuna Kitchen',
      debugShowCheckedModeBanner: false,
      scaffoldMessengerKey: _scaffoldMessengerKey,
      theme: KitchenTheme.light,
      home: _home(),
    );
  }

  Widget _home() {
    if (_restoring) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }
    final account = _account;
    if (account == null && _startupError != null) {
      return _RetrySessionScreen(
        message: _startupError!,
        onRetry: _restoreSession,
        onSignIn: () => setState(() {
          _startupError = null;
          _account = null;
        }),
      );
    }
    if (account == null) {
      return KitchenSignInScreen(onSignIn: _signIn);
    }
    return KitchenBoardScreen(
      account: account,
      kitchens: _kitchens,
      selectedKitchen: _selectedKitchen,
      tickets: _tickets,
      busyTicketId: _busyTicketId,
      isLoading: _loadingBoard,
      isRefreshing: _refreshing,
      error: _boardError,
      refreshedAt: _refreshedAt,
      liveConfigured: widget.liveConnection.isConfigured,
      liveConnected: _liveConnected,
      onRefresh: () => _refreshTickets(notify: false),
      onKitchenChanged: _changeKitchen,
      onStatusChange: _updateTicket,
      onSignOut: _signOut,
    );
  }
}

String _statusLabel(String status) => switch (status) {
  'accepted' => 'accepted',
  'preparing' => 'preparing',
  'ready' => 'ready',
  _ => status,
};

class _RetrySessionScreen extends StatelessWidget {
  const _RetrySessionScreen({
    required this.message,
    required this.onRetry,
    required this.onSignIn,
  });

  final String message;
  final VoidCallback onRetry;
  final VoidCallback onSignIn;

  @override
  Widget build(BuildContext context) => Scaffold(
    body: SafeArea(
      child: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 420),
          child: Padding(
            padding: const EdgeInsets.all(28),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Icon(Icons.cloud_off_rounded, size: 46),
                const SizedBox(height: 18),
                Text(
                  'Kitchen board unavailable',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                const SizedBox(height: 8),
                Text(message, textAlign: TextAlign.center),
                const SizedBox(height: 22),
                FilledButton.icon(
                  onPressed: onRetry,
                  icon: const Icon(Icons.refresh_rounded),
                  label: const Text('Retry connection'),
                ),
                TextButton(
                  onPressed: onSignIn,
                  child: const Text('Go to sign in'),
                ),
              ],
            ),
          ),
        ),
      ),
    ),
  );
}
