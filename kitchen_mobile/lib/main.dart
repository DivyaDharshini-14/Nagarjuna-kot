import 'dart:async';

import 'package:flutter/material.dart';

import 'app/kitchen_theme.dart';
import 'core/api_client.dart';
import 'core/kitchen_live_connection.dart';
import 'core/push_notifications.dart';
import 'core/secure_token_store.dart';
import 'data/kitchen_repository.dart';
import 'domain/kitchen_models.dart';
import 'presentation/kitchen_board_screen.dart';
import 'presentation/kitchen_stock_screen.dart';
import 'presentation/kitchen_sign_in_screen.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final pushNotifications = MobilePushNotifications();
  await pushNotifications.initialize();
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
      pushNotifications: pushNotifications,
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
    required this.pushNotifications,
    required this.liveConnection,
  });

  final KitchenRepository repository;
  final MobilePushNotifications pushNotifications;
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
  String _boardView = 'active';
  DateTime? _refreshedAt;
  bool _hasBoardSnapshot = false;
  bool _liveConnected = false;
  bool _showStockControl = false;
  Map<int, String> _ticketStatuses = const {};
  Map<int, int> _ticketCancellationQuantities = const {};
  Map<String, dynamic>? _pendingPushData;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    unawaited(
      widget.pushNotifications.bind(
        onForegroundMessage: _handleForegroundPush,
        onNotificationOpened: _handlePushOpened,
      ),
    );
    _restoreSession();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _refreshTimer?.cancel();
    widget.liveConnection.dispose();
    unawaited(widget.pushNotifications.dispose());
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed && _account != null) {
      unawaited(_activatePush());
      _refreshTickets(notify: true);
      _connectLive();
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
          _startupError =
              'Could not restore the kitchen session. Retry or sign in again.';
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
      _selectedKitchen = null;
      _tickets = const [];
      _boardView = 'active';
      _restoring = false;
      _startupError = null;
      _refreshing = false;
      _loadingBoard = false;
      _boardError = null;
      _hasBoardSnapshot = false;
      _ticketStatuses = const {};
      _ticketCancellationQuantities = const {};
      _liveConnected = false;
    });
    if (kitchens.isNotEmpty) {
      await _refreshTickets();
      if (mounted && _account != null) {
        _connectLive();
        _startRefreshTimer();
      }
    }
    unawaited(_activatePush());
    final pendingPushData = _pendingPushData;
    _pendingPushData = null;
    if (pendingPushData != null) _handlePushOpened(pendingPushData);
  }

  Future<void> _activatePush() =>
      widget.pushNotifications.activate(registerToken: _registerPushToken);

  Future<void> _registerPushToken(String token) async {
    await widget.repository.api.post(
      '/kitchen-mobile/push-token',
      body: {'token': token, 'platform': widget.pushNotifications.platform},
    );
  }

  Future<void> _unregisterPushToken(String token) async {
    await widget.repository.api.delete(
      '/kitchen-mobile/push-token',
      body: {'token': token},
    );
  }

  void _handleForegroundPush(Map<String, dynamic> data) {
    if (!mounted || _account == null) return;
    final type = data['type'];
    if (type == 'kot_created' || type == 'kot_cancelled') {
      _scaffoldMessengerKey.currentState?.showSnackBar(
        SnackBar(
          content: Text(
            type == 'kot_created'
                ? 'A new kitchen ticket arrived'
                : 'A kitchen ticket was cancelled',
          ),
        ),
      );
      unawaited(_refreshTickets(notify: true, force: true));
    }
  }

  void _handlePushOpened(Map<String, dynamic> data) {
    if (_account == null) {
      _pendingPushData = data;
      return;
    }
    final kitchenId = int.tryParse('${data['kitchen_id'] ?? ''}');
    KitchenStation? kitchen;
    for (final station in _kitchens) {
      if (station.id == kitchenId) {
        kitchen = station;
        break;
      }
    }
    if (kitchen != null && _selectedKitchen?.id != kitchen.id) {
      unawaited(_changeKitchen(kitchen));
    } else {
      unawaited(_refreshTickets(notify: true, force: true));
    }
  }

  void _startRefreshTimer() {
    _refreshTimer?.cancel();
    _refreshTimer = Timer.periodic(
      const Duration(seconds: 12),
      (_) => _refreshTickets(notify: true),
    );
  }

  Future<void> _changeKitchen(KitchenStation? kitchen) async {
    if (_selectedKitchen?.id == kitchen?.id) return;
    setState(() {
      _selectedKitchen = kitchen;
      _tickets = const [];
      _refreshing = false;
      _loadingBoard = false;
      _hasBoardSnapshot = false;
      _ticketStatuses = const {};
      _ticketCancellationQuantities = const {};
    });
    _connectLive();
    await _refreshTickets(force: true);
  }

  List<int> get _liveKitchenIds {
    final selected = _selectedKitchen;
    if (selected != null) return [selected.id];
    return _kitchens.map((kitchen) => kitchen.id).toList(growable: false);
  }

  void _connectLive() {
    if (mounted) setState(() => _liveConnected = false);
    final kitchenIds = _liveKitchenIds;
    if (kitchenIds.isEmpty) {
      widget.liveConnection.disconnect();
      return;
    }
    widget.liveConnection.connect(
      kitchenIds: kitchenIds,
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
    if (_kitchens.isEmpty || (_refreshing && !force) || !mounted) return;
    final generation = ++_refreshGeneration;
    setState(() {
      _refreshing = true;
      _loadingBoard = !_hasBoardSnapshot;
      _boardError = null;
    });
    try {
      final tickets = await widget.repository.getTickets(
        kitchen?.id,
        view: _boardView,
      );
      if (!mounted || generation != _refreshGeneration) return;
      if (_selectedKitchen?.id != kitchen?.id) {
        setState(() {
          _loadingBoard = false;
          _refreshing = false;
        });
        return;
      }
      if (notify && _hasBoardSnapshot && _boardView == 'active') {
        _notifyTicketChanges(tickets);
      }
      setState(() {
        _tickets = tickets;
        _ticketStatuses = {
          for (final ticket in tickets) ticket.id: ticket.status,
        };
        _ticketCancellationQuantities = {
          for (final ticket in tickets)
            ticket.id: ticket.dishes.fold(
              0,
              (sum, dish) => sum + dish.cancelledQuantity,
            ),
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

  Future<void> _changeBoardView(String view) async {
    if (_boardView == view) return;
    setState(() {
      _boardView = view;
      _tickets = const [];
      _hasBoardSnapshot = false;
    });
    await _refreshTickets(force: true);
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
    final cancelledItems = tickets.where((ticket) {
      final total = ticket.dishes.fold(
        0,
        (sum, dish) => sum + dish.cancelledQuantity,
      );
      return total > (_ticketCancellationQuantities[ticket.id] ?? 0);
    });
    final notices = <String>[];
    if (added.isNotEmpty) notices.add('New KOT ${added.first.number} received');
    if (cancelled.isNotEmpty) {
      notices.add('KOT ${cancelled.first.number} was cancelled');
    }
    if (cancelledItems.isNotEmpty) {
      notices.add('Cancelled items on KOT ${cancelledItems.first.number}');
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
    await widget.pushNotifications.deactivate(
      unregisterToken: _unregisterPushToken,
    );
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
      _ticketCancellationQuantities = const {};
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
    if (_showStockControl) {
      return KitchenStockScreen(
        repository: widget.repository,
        canManageStock: account.canManageStock,
        onBack: () => setState(() => _showStockControl = false),
      );
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
      boardView: _boardView,
      onBoardViewChanged: _changeBoardView,
      onKitchenChanged: _changeKitchen,
      onStatusChange: _updateTicket,
      onSignOut: _signOut,
      onStockControl: account.canManageStock
          ? () => setState(() => _showStockControl = true)
          : null,
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
