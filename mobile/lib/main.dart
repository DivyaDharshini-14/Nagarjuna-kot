import 'package:flutter/material.dart';

import 'app/app_theme.dart';
import 'core/network/mobile_api_client.dart';
import 'features/auth/auth_repository.dart';
import 'features/auth/data/sanctum_auth_repository.dart';
import 'features/auth/data/secure_token_store.dart';
import 'features/auth/presentation/sign_in_screen.dart';
import 'features/menu/data/menu_repository.dart';
import 'features/menu/presentation/menu_composer_screen.dart';
import 'features/orders/data/mobile_order_repository.dart';
import 'features/orders/domain/order_confirmation.dart';
import 'features/orders/presentation/order_progress_screen.dart';
import 'features/tables/data/floor_table_repository.dart';
import 'features/tables/domain/floor_table.dart';
import 'features/tables/presentation/floor_table_screen.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  final tokenStore = SecureTokenStore();
  final api = MobileApiClient(tokenReader: tokenStore.read);
  runApp(
    NagarjunaWaiterApp(
      authRepository: SanctumAuthRepository(api: api, tokens: tokenStore),
      floorTableRepository: ApiFloorTableRepository(api: api),
      menuRepository: ApiMenuRepository(api: api),
      orderRepository: ApiMobileOrderRepository(api: api),
      resolveAssetUri: api.resolveAssetUri,
    ),
  );
}

class NagarjunaWaiterApp extends StatefulWidget {
  const NagarjunaWaiterApp({
    super.key,
    required this.authRepository,
    required this.floorTableRepository,
    required this.menuRepository,
    required this.orderRepository,
    required this.resolveAssetUri,
  });

  final AuthRepository authRepository;
  final FloorTableRepository floorTableRepository;
  final MenuRepository menuRepository;
  final MobileOrderRepository orderRepository;
  final Uri? Function(String?) resolveAssetUri;

  @override
  State<NagarjunaWaiterApp> createState() => _NagarjunaWaiterAppState();
}

class _NagarjunaWaiterAppState extends State<NagarjunaWaiterApp> {
  bool _isRestoringSession = true;
  bool _isAuthenticated = false;
  String? _sessionError;
  RestaurantTable? _selectedTable;
  int? _trackedOrderId;
  String? _trackedOrderNumber;

  @override
  void initState() {
    super.initState();
    _restoreSession();
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
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _sessionError = _messageFor(error);
        _isRestoringSession = false;
      });
    }
  }

  Future<void> _signOut() async {
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
    });
  }

  Future<void> _expireSession() => _signOut();

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
        onAuthenticated: () => setState(() => _isAuthenticated = true),
      );
    }
    final selectedTable = _selectedTable;
    if (selectedTable != null) {
      return MenuComposerScreen(
        table: selectedTable,
        repository: widget.menuRepository,
        orderRepository: widget.orderRepository,
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
        onBack: _closeOrderProgress,
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
