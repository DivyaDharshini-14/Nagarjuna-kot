import 'dart:async';

import 'package:flutter/material.dart';

import 'app/app_theme.dart';
import 'core/network/mobile_api_client.dart';
import 'core/network/mobile_order_live_connection.dart';
import 'core/push_notifications.dart';
import 'core/restaurant_day.dart';
import 'features/auth/auth_repository.dart';
import 'features/auth/data/sanctum_auth_repository.dart';
import 'features/auth/data/secure_token_store.dart';
import 'features/auth/presentation/sign_in_screen.dart';
import 'features/menu/data/menu_repository.dart';
import 'features/menu/presentation/menu_composer_screen.dart';
import 'features/orders/data/mobile_order_repository.dart';
import 'features/orders/data/pending_order_draft_store.dart';
import 'features/orders/domain/order_confirmation.dart';
import 'features/orders/presentation/cancellation_history_screen.dart';
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
  String? _permissionsError;
  String? _sessionError;
  RestaurantTable? _selectedTable;
  int? _trackedOrderId;
  String? _trackedOrderNumber;
  int? _pendingPushOrderId;
  final _orderActivity = <int, DateTime>{};
  bool _canViewOrders = false;
  bool _hasFloorTableAccess = false;
  bool _canAddOrderItems = false;
  bool _canEditOrders = false;
  bool _canCancelOrders = false;
  bool _showCancellationHistory = false;
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
    if (!mounted) return;
    setState(() {
      _isAuthenticated = false;
      _selectedTable = null;
      _trackedOrderId = null;
      _trackedOrderNumber = null;
      _canViewOrders = false;
      _hasFloorTableAccess = false;
      _canAddOrderItems = false;
      _canEditOrders = false;
      _canCancelOrders = false;
      _permissionsError = null;
      _permissionsLoading = false;
      _showCancellationHistory = false;
      _orderActivity.clear();
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
    if (data['type'] != 'order_ready') return;
    final orderId = int.tryParse('${data['order_id'] ?? ''}');
    if (orderId != null && orderId > 0) {
      unawaited(_showOrderReady(orderId, openOrder: false));
    }
  }

  void _handlePushOpened(Map<String, dynamic> data) {
    if (data['type'] != 'order_ready') return;
    final orderId = int.tryParse('${data['order_id'] ?? ''}');
    if (orderId == null || orderId < 1) return;
    if (!_isAuthenticated) {
      _pendingPushOrderId = orderId;
      return;
    }
    unawaited(_showOrderReady(orderId, openOrder: true));
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
    if (mounted) {
      setState(() {
        _permissionsLoading = true;
        _permissionsError = null;
      });
    }
    try {
      final permissions = await widget.authRepository.loadPermissions();
      if (!mounted) return;
      setState(() {
        _canViewOrders = permissions.contains('order.view');
        _canAddOrderItems = permissions.contains('order.add');
        _canEditOrders = permissions.contains('order.edit');
        _canCancelOrders = permissions.contains('order.cancel');
        _hasFloorTableAccess =
            permissions.contains('floor.view') &&
            permissions.contains('table.view') &&
            permissions.contains('category.view') &&
            permissions.contains('menu-type.view') &&
            permissions.contains('menu-item.view') &&
            _canViewOrders &&
            _canAddOrderItems;
        _permissionsLoading = false;
        _permissionsError = null;
      });
      await _recoverPendingAddition();
    } on Object {
      if (!mounted) return;
      setState(() {
        _permissionsLoading = false;
        _permissionsError = 'We could not check your staff permissions. Check your connection and retry.';
      });
    }
  }

  void _openPendingPushOrder() {
    final orderId = _pendingPushOrderId;
    if (orderId == null) return;
    _pendingPushOrderId = null;
    unawaited(_showOrderReady(orderId, openOrder: true));
  }

  Future<void> _showOrderReady(int orderId, {required bool openOrder}) async {
    if (!mounted ||
        !_isAuthenticated ||
        isFromPreviousRestaurantDay(_orderActivity[orderId])) {
      return;
    }
    final userId = widget.authRepository.currentUserId;
    try {
      final order = await widget.orderRepository.getOrderStatus(orderId);
      if (!mounted ||
          !_isAuthenticated ||
          userId != widget.authRepository.currentUserId) {
        return;
      }
      final activity = order.firstKnownActivityAt;
      final cachedActivity = _orderActivity[orderId];
      if (activity != null &&
          (cachedActivity == null || activity.isBefore(cachedActivity))) {
        _orderActivity[orderId] = activity;
      }
      if (isFromPreviousRestaurantDay(_orderActivity[orderId])) return;
      if (openOrder) {
        setState(() {
          _selectedTable = null;
          _trackedOrderId = orderId;
          _trackedOrderNumber = order.orderNumber;
        });
      } else if (order.orderStatus != 'completed' &&
          order.orderStatus != 'cancelled') {
        _scaffoldMessengerKey.currentState?.showSnackBar(
          const SnackBar(content: Text('A kitchen ticket is ready')),
        );
      }
    } on Object {
      // The alert does not change the current screen if the order is unavailable.
    }
  }

  void _trackSubmittedOrder(OrderConfirmation order) {
    unawaited(_rememberHistoryOrders([order.id]));
    setState(() {
      _selectedTable = null;
      _trackedOrderId = order.id;
      _trackedOrderNumber = order.orderNumber;
    });
  }

  Future<void> _rememberHistoryOrders(Iterable<int> ids) async {
    final userId = widget.authRepository.currentUserId;
    if (userId == null) return;
    try {
      await widget.pendingDraftStore.rememberCancellationOrders(userId, ids);
    } catch (_) {
      // A local history index failure must not interrupt order entry.
    }
  }

  Future<bool> _recoverPendingAddition() async {
    final userId = widget.authRepository.currentUserId;
    if (userId == null || !_hasFloorTableAccess) return false;
    final addition = await widget.pendingDraftStore.readQuantityAddition(
      userId,
    );
    if (!mounted || addition == null) return false;
    setState(() {
      _selectedTable = null;
      _trackedOrderId = addition.orderId;
      _trackedOrderNumber = 'Saved dish addition';
    });
    return true;
  }

  Future<void> _selectTable(RestaurantTable table) async {
    if (await _recoverPendingAddition() || !mounted) return;
    if (isFromPreviousRestaurantDay(_orderActivity[table.activeOrderId])) {
      return;
    }
    setState(() => _selectedTable = table);
  }

  Future<void> _trackTableOrder(RestaurantTable table) async {
    if (await _recoverPendingAddition() || !mounted) return;
    final orderId = table.activeOrderId;
    if (orderId == null) return;
    setState(() {
      _trackedOrderId = orderId;
      _trackedOrderNumber = table.activeOrderNumber ?? 'Order';
    });
  }

  Future<void> _editOrderInMenu(RestaurantTable table) async {
    if (await _recoverPendingAddition() || !mounted) return;
    if (isFromPreviousRestaurantDay(_orderActivity[table.activeOrderId])) {
      return;
    }
    setState(() {
      _trackedOrderId = null;
      _trackedOrderNumber = null;
      _selectedTable = table;
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
    if (_permissionsError != null) {
      return _PermissionRetryScreen(
        message: _permissionsError!,
        onRetry: _refreshPermissions,
        onSignOut: _signOut,
      );
    }
    if (_showCancellationHistory) {
      return CancellationHistoryScreen(
        repository: widget.orderRepository,
        store: widget.pendingDraftStore,
        userId: widget.authRepository.currentUserId!,
        canViewOrders: _canViewOrders,
        floorRepository: _hasFloorTableAccess
            ? widget.floorTableRepository
            : null,
        onBack: _hasFloorTableAccess
            ? () => setState(() => _showCancellationHistory = false)
            : _signOut,
        onSessionExpired: _expireSession,
      );
    }
    if (!_hasFloorTableAccess) {
      return _NoMobileAccessScreen(onSignOut: _signOut);
    }
    final selectedTable = _selectedTable;
    if (selectedTable != null) {
      return MenuComposerScreen(
        userId: widget.authRepository.currentUserId!,
        table: selectedTable,
        activeOrderActivity: _orderActivity[selectedTable.activeOrderId],
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
        key: ValueKey(trackedOrderId),
        orderId: trackedOrderId,
        orderNumber: _trackedOrderNumber ?? 'Order',
        firstKnownActivity: _orderActivity[trackedOrderId],
        repository: widget.orderRepository,
        pendingDraftStore: widget.pendingDraftStore,
        userId: widget.authRepository.currentUserId!,
        onRecoverPendingOrder: (orderId) => setState(() {
          _trackedOrderId = orderId;
          _trackedOrderNumber = 'Saved dish addition';
        }),
        liveConnection: widget.liveConnection,
        onBack: _closeOrderProgress,
        onOrderCompleted: _closeOrderProgress,
        onEditOrder: _editOrderInMenu,
        canAddItems: _canAddOrderItems,
        canEditOrders: _canEditOrders,
        canCancelOrders: _canCancelOrders,
        onSessionExpired: _expireSession,
      );
    }
    return FloorTableScreen(
      repository: widget.floorTableRepository,
      orderRepository: widget.orderRepository,
      orderActivity: _orderActivity,
      onSignOut: _signOut,
      onSessionExpired: _expireSession,
      onTableSelected: _selectTable,
      onTrackOrder: _trackTableOrder,
      onActiveOrdersSeen: (ids) => unawaited(_rememberHistoryOrders(ids)),
      onOpenCancellationHistory: () =>
          setState(() => _showCancellationHistory = true),
    );
  }

  String _messageFor(Object error) {
    if (error is ApiConfigurationException) return error.message;
    if (error is ApiTransportException) return error.message;
    if (error is ApiException) return error.message;
    return 'We could not check your saved session. Check your connection and retry.';
  }
}

class _PermissionRetryScreen extends StatelessWidget {
  const _PermissionRetryScreen({
    required this.message,
    required this.onRetry,
    required this.onSignOut,
  });

  final String message;
  final Future<void> Function() onRetry;
  final Future<void> Function() onSignOut;

  @override
  Widget build(BuildContext context) => Scaffold(
    body: SafeArea(
      child: Center(
        child: Padding(
          padding: const EdgeInsets.all(26),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.security_rounded, size: 42),
              const SizedBox(height: 14),
              Text(
                'Could not confirm your access',
                style: Theme.of(context).textTheme.titleLarge,
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 8),
              Text(message, textAlign: TextAlign.center),
              const SizedBox(height: 20),
              FilledButton.icon(
                onPressed: onRetry,
                icon: const Icon(Icons.refresh_rounded),
                label: const Text('Retry'),
              ),
              TextButton(onPressed: onSignOut, child: const Text('Sign out')),
            ],
          ),
        ),
      ),
    ),
  );
}

class _NoMobileAccessScreen extends StatelessWidget {
  const _NoMobileAccessScreen({required this.onSignOut});

  final Future<void> Function() onSignOut;

  @override
  Widget build(BuildContext context) => Scaffold(
    body: SafeArea(
      child: Center(
        child: Padding(
          padding: const EdgeInsets.all(26),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.lock_outline_rounded, size: 42),
              const SizedBox(height: 14),
              Text(
                'Mobile access is not assigned',
                style: Theme.of(context).textTheme.titleLarge,
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 8),
              const Text(
                'Ask your manager to assign a waiter role, then sign in again.',
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 20),
              FilledButton.icon(
                onPressed: onSignOut,
                icon: const Icon(Icons.logout_rounded),
                label: const Text('Sign out'),
              ),
            ],
          ),
        ),
      ),
    ),
  );
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
