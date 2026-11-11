import 'dart:async';

import 'package:flutter/material.dart';

import 'app/app_theme.dart';
import 'core/network/mobile_api_client.dart';
import 'core/network/mobile_order_live_connection.dart';
import 'core/push_notifications.dart';
import 'features/auth/auth_repository.dart';
import 'features/auth/data/sanctum_auth_repository.dart';
import 'features/auth/data/secure_token_store.dart';
import 'features/auth/presentation/sign_in_screen.dart';
import 'features/menu/data/menu_repository.dart';
import 'features/menu/presentation/menu_composer_screen.dart';
import 'features/orders/data/mobile_order_repository.dart';
import 'features/orders/data/pending_order_draft_store.dart';
import 'features/orders/domain/order_confirmation.dart';
import 'features/orders/presentation/cancellation_approval_screen.dart';
import 'features/orders/presentation/order_progress_screen.dart';
import 'features/tables/data/floor_table_repository.dart';
import 'features/tables/domain/floor_table.dart';
import 'features/tables/presentation/floor_table_screen.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final pushNotifications = MobilePushNotifications();
  await pushNotifications.initialize();
  final tokenStore = SecureTokenStore();
  const apiBaseUrl = String.fromEnvironment('API_BASE_URL');
  final apiUri = Uri.tryParse(apiBaseUrl);
  const reverbHost = String.fromEnvironment('REVERB_HOST');
  const reverbScheme = String.fromEnvironment('REVERB_SCHEME');
  final secureReverb = reverbScheme.isEmpty
      ? apiUri?.scheme == 'https'
      : reverbScheme == 'https';
  final api = MobileApiClient(tokenReader: tokenStore.read);
  runApp(
    NagarjunaWaiterApp(
      api: api,
      authRepository: SanctumAuthRepository(api: api, tokens: tokenStore),
      floorTableRepository: ApiFloorTableRepository(api: api),
      menuRepository: ApiMenuRepository(api: api),
      orderRepository: ApiMobileOrderRepository(api: api),
      pendingDraftStore: PendingOrderDraftStore(),
      pushNotifications: pushNotifications,
      liveConnection: MobileOrderLiveConnection(
        apiBaseUrl: apiBaseUrl,
        appKey: const String.fromEnvironment('REVERB_APP_KEY'),
        host: reverbHost.isEmpty ? apiUri?.host ?? '' : reverbHost,
        port:
            int.tryParse(const String.fromEnvironment('REVERB_PORT')) ??
            (secureReverb ? 443 : 8080),
        secure: secureReverb,
        readToken: tokenStore.read,
      ),
      resolveAssetUri: api.resolveAssetUri,
    ),
  );
}

class NagarjunaWaiterApp extends StatefulWidget {
  const NagarjunaWaiterApp({
    super.key,
    required this.api,
    required this.authRepository,
    required this.floorTableRepository,
    required this.menuRepository,
    required this.orderRepository,
    required this.pendingDraftStore,
    required this.pushNotifications,
    required this.liveConnection,
    required this.resolveAssetUri,
  });

  final MobileApiClient api;
  final AuthRepository authRepository;
  final FloorTableRepository floorTableRepository;
  final MenuRepository menuRepository;
  final MobileOrderRepository orderRepository;
  final PendingOrderDraftStore pendingDraftStore;
  final MobilePushNotifications pushNotifications;
  final MobileOrderLiveConnection liveConnection;
  final Uri? Function(String?) resolveAssetUri;

  @override
  State<NagarjunaWaiterApp> createState() => _NagarjunaWaiterAppState();
}

class _NagarjunaWaiterAppState extends State<NagarjunaWaiterApp>
    with WidgetsBindingObserver {
  bool _isRestoringSession = true;
  bool _isAuthenticated = false;
  bool _permissionsLoading = false;
  String? _sessionError;
  RestaurantTable? _selectedTable;
  int? _trackedOrderId;
  String? _trackedOrderNumber;
  int? _pendingPushOrderId;
  bool _canApproveCancellations = false;
  bool _hasFloorTableAccess = false;
  bool _showCancellationApprovals = false;
  final GlobalKey<ScaffoldMessengerState> _scaffoldMessengerKey =
      GlobalKey<ScaffoldMessengerState>();

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    widget.liveConnection.dispose();
    unawaited(widget.pushNotifications.dispose());
    super.dispose();
  }

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
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed && _isAuthenticated) {
      unawaited(_activatePush());
    }
  }

  Future<void> _restoreSession() async {
    setState(() {
      _isRestoringSession = true;
      _sessionError = null;
    });
    try {
      final hasSession = await widget.authRepository.restoreSession();
      if (!mounted) return;
      setState(() {
        _isAuthenticated = hasSession;
        _isRestoringSession = false;
        _permissionsLoading = hasSession;
      });
      if (hasSession) {
        await _refreshPermissions();
        if (!mounted) return;
        unawaited(_activatePush());
        _openPendingPushOrder();
      }
      if (!hasSession) await widget.pendingDraftStore.clear();
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _sessionError = _messageFor(error);
        _isRestoringSession = false;
      });
    }
  }

  Future<void> _signOut() async {
    await widget.pushNotifications.deactivate(
      unregisterToken: _unregisterPushToken,
    );
    try {
      await widget.authRepository.signOut();
    } catch (_) {
      // The local token is cleared by the repository even if the server is offline.
    }
    await widget.pendingDraftStore.clear();
    if (!mounted) return;
    setState(() {
      _isAuthenticated = false;
      _selectedTable = null;
      _trackedOrderId = null;
      _trackedOrderNumber = null;
      _canApproveCancellations = false;
      _hasFloorTableAccess = false;
      _permissionsLoading = false;
      _showCancellationApprovals = false;
    });
  }

  Future<void> _expireSession() => _signOut();

  Future<void> _activatePush() =>
      widget.pushNotifications.activate(registerToken: _registerPushToken);

  Future<void> _registerPushToken(String token) async {
    await widget.api.post(
      'mobile/push-token',
      body: {'token': token, 'platform': widget.pushNotifications.platform},
    );
  }

  Future<void> _unregisterPushToken(String token) async {
    await widget.api.delete('mobile/push-token', body: {'token': token});
  }

  void _handleForegroundPush(Map<String, dynamic> data) {
    if (!mounted || data['type'] != 'order_ready') return;
    _scaffoldMessengerKey.currentState?.showSnackBar(
      const SnackBar(content: Text('A kitchen ticket is ready')),
    );
  }

  void _handlePushOpened(Map<String, dynamic> data) {
    if (data['type'] != 'order_ready') return;
    final orderId = int.tryParse('${data['order_id'] ?? ''}');
    if (orderId == null || orderId < 1) return;
    if (!_isAuthenticated) {
      _pendingPushOrderId = orderId;
      return;
    }
    setState(() {
      _selectedTable = null;
      _trackedOrderId = orderId;
      _trackedOrderNumber = 'Order';
    });
  }

  void _onAuthenticated() {
    setState(() {
      _isAuthenticated = true;
      _permissionsLoading = true;
    });
    unawaited(_refreshPermissions());
    _openPendingPushOrder();
    unawaited(_activatePush());
  }

  Future<void> _refreshPermissions() async {
    try {
      final permissions = await widget.authRepository.loadPermissions();
      if (!mounted) return;
      setState(() {
        _canApproveCancellations = permissions.contains('order.approve');
        _hasFloorTableAccess =
            permissions.contains('floor.view') &&
            permissions.contains('table.view');
        _permissionsLoading = false;
        if (_canApproveCancellations && !_hasFloorTableAccess) {
          _showCancellationApprovals = true;
        }
      });
    } on Object {
      if (!mounted) return;
      setState(() {
        _canApproveCancellations = false;
        _hasFloorTableAccess = false;
        _permissionsLoading = false;
      });
    }
  }

  void _openPendingPushOrder() {
    final orderId = _pendingPushOrderId;
    if (orderId == null) return;
    _pendingPushOrderId = null;
    setState(() {
      _selectedTable = null;
      _trackedOrderId = orderId;
      _trackedOrderNumber = 'Order';
    });
  }

  void _trackSubmittedOrder(OrderConfirmation order) {
    setState(() {
      _selectedTable = null;
      _trackedOrderId = order.id;
      _trackedOrderNumber = order.orderNumber;
    });
  }

  void _trackTableOrder(RestaurantTable table) {
    final orderId = table.activeOrderId;
    if (orderId == null) return;
    setState(() {
      _trackedOrderId = orderId;
      _trackedOrderNumber = table.activeOrderNumber ?? 'Order';
    });
  }

  void _closeOrderProgress() {
    setState(() {
      _trackedOrderId = null;
      _trackedOrderNumber = null;
    });
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Nagarjuna Waiter',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light,
      scaffoldMessengerKey: _scaffoldMessengerKey,
      home: _home(),
    );
  }

  Widget _home() {
    if (_isRestoringSession) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }
    if (_sessionError != null) {
      return _SessionRetryScreen(
        message: _sessionError!,
        onRetry: _restoreSession,
        onSignInInstead: () => setState(() {
          _sessionError = null;
          _isAuthenticated = false;
        }),
      );
    }
    if (!_isAuthenticated) {
      return SignInScreen(
        repository: widget.authRepository,
        onAuthenticated: _onAuthenticated,
      );
    }
    if (_permissionsLoading) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }
    if (_showCancellationApprovals) {
      return CancellationApprovalScreen(
        repository: widget.orderRepository,
        onBack: _hasFloorTableAccess
            ? () => setState(() => _showCancellationApprovals = false)
            : _signOut,
        onSessionExpired: _expireSession,
      );
    }
    if (!_hasFloorTableAccess && _canApproveCancellations) {
      return CancellationApprovalScreen(
        repository: widget.orderRepository,
        onBack: _signOut,
        onSessionExpired: _expireSession,
      );
    }
    final selectedTable = _selectedTable;
    if (selectedTable != null) {
      return MenuComposerScreen(
        table: selectedTable,
        repository: widget.menuRepository,
        orderRepository: widget.orderRepository,
        pendingDraftStore: widget.pendingDraftStore,
        liveConnection: widget.liveConnection,
        resolveAssetUri: widget.resolveAssetUri,
        onBack: () => setState(() => _selectedTable = null),
        onOrderSubmitted: _trackSubmittedOrder,
        onSignOut: _signOut,
        onSessionExpired: _expireSession,
      );
    }
    final trackedOrderId = _trackedOrderId;
    if (trackedOrderId != null) {
      return OrderProgressScreen(
        orderId: trackedOrderId,
        orderNumber: _trackedOrderNumber ?? 'Order',
        repository: widget.orderRepository,
        liveConnection: widget.liveConnection,
        onBack: _closeOrderProgress,
        onOrderCompleted: _closeOrderProgress,
        onSessionExpired: _expireSession,
      );
    }
    return FloorTableScreen(
      repository: widget.floorTableRepository,
      onSignOut: _signOut,
      onSessionExpired: _expireSession,
      onTableSelected: (RestaurantTable table) =>
          setState(() => _selectedTable = table),
      onTrackOrder: _trackTableOrder,
      canApproveCancellations: _canApproveCancellations,
      onOpenCancellationApprovals: () =>
          setState(() => _showCancellationApprovals = true),
    );
  }

  String _messageFor(Object error) {
    if (error is ApiConfigurationException) return error.message;
    if (error is ApiTransportException) return error.message;
    if (error is ApiException) return error.message;
    return 'We could not check your saved session. Check your connection and retry.';
  }
}

class _SessionRetryScreen extends StatelessWidget {
  const _SessionRetryScreen({
    required this.message,
    required this.onRetry,
    required this.onSignInInstead,
  });

  final String message;
  final VoidCallback onRetry;
  final VoidCallback onSignInInstead;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 420),
            child: Padding(
              padding: const EdgeInsets.all(28),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Icon(Icons.cloud_off_outlined, size: 42),
                  const SizedBox(height: 16),
                  Text(
                    'Could not restore your session',
                    style: Theme.of(context).textTheme.titleLarge,
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: 8),
                  Text(message, textAlign: TextAlign.center),
                  const SizedBox(height: 22),
                  FilledButton.icon(
                    onPressed: onRetry,
                    icon: const Icon(Icons.refresh_rounded),
                    label: const Text('Retry'),
                  ),
                  TextButton(
                    onPressed: onSignInInstead,
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
}
