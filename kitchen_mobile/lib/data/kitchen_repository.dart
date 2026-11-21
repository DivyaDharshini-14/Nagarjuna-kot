import '../core/api_client.dart';
import '../core/secure_token_store.dart';
import '../domain/kitchen_models.dart';

class KitchenRepository {
  const KitchenRepository({required this.api, required this.tokens});

  final KitchenApiClient api;
  final KitchenTokenStore tokens;

  Future<KitchenAccount> signIn(String email, String password) async {
    final response = await api.post(
      '/kitchen-mobile/auth/login',
      authenticated: false,
      body: {
        'email': email.trim(),
        'password': password,
        'device_name': 'Nagarjuna Kitchen Android',
      },
    );
    final data = response['data'] as Map<String, dynamic>;
    await tokens.save(data['token'] as String);
    return KitchenAccount.fromJson(data['user'] as Map<String, dynamic>);
  }

  Future<KitchenAccount> restoreSession() async {
    final response = await api.get('/kitchen-mobile/auth/me');
    final data = response['data'] as Map<String, dynamic>;
    return KitchenAccount.fromJson(data['user'] as Map<String, dynamic>);
  }

  Future<List<KitchenStation>> getKitchens() async {
    final response = await api.get('/kitchen-mobile/kitchens');
    final data = response['data'] as Map<String, dynamic>;
    return (data['kitchens'] as List<dynamic>? ?? const [])
        .whereType<Map<String, dynamic>>()
        .map(KitchenStation.fromJson)
        .toList(growable: false);
  }

  Future<List<KotTicket>> getTickets(
    int? kitchenId, {
    String view = 'active',
  }) async => (await getTicketPage(kitchenId, view: view)).items;

  Future<KitchenPage<KotTicket>> getTicketPage(
    int? kitchenId, {
    String view = 'active',
    int page = 1,
  }) async {
    final response = await api.get(
      kitchenId == null
          ? '/kitchen-mobile/kot?view=$view&page=$page'
          : '/kitchen-mobile/kitchens/$kitchenId/kot?view=$view&page=$page',
    );
    final data = response['data'] as Map<String, dynamic>;
    return KitchenPage.fromData(data, 'tickets', KotTicket.fromJson);
  }

  Future<void> acceptTicket(int ticketId) async {
    await api.patch(
      '/kitchen-mobile/kot-tickets/$ticketId/status',
      body: {'status': 'accepted'},
    );
  }

  Future<void> markItemReady(int itemId) async {
    await api.patch('/kitchen-mobile/kot-items/$itemId/ready', body: const {});
  }

  Future<List<KitchenStockItem>> getStockItems() async =>
      (await getStockPage()).items;

  Future<KitchenPage<KitchenStockItem>> getStockPage({int page = 1}) async {
    final response = await api.get('/kitchen-mobile/stock?page=$page');
    final data = response['data'] as Map<String, dynamic>;
    return KitchenPage.fromData(data, 'items', KitchenStockItem.fromJson);
  }

  Future<KitchenStockItem> adjustStock({
    required int menuItemId,
    required String type,
    required int quantity,
    required String reason,
    String? direction,
  }) async {
    final response = await api.post(
      '/kitchen-mobile/stock/$menuItemId/adjustments',
      body: {
        'type': type,
        'quantity': quantity,
        'reason': reason,
        'direction': ?direction,
      },
    );
    final data = response['data'] as Map<String, dynamic>;
    return KitchenStockItem.fromJson(data['item'] as Map<String, dynamic>);
  }

  Future<KitchenStockItem> setAvailability({
    required int menuItemId,
    required String availability,
    required String reason,
  }) async {
    final response = await api.patch(
      '/kitchen-mobile/stock/$menuItemId/availability',
      body: {'availability': availability, 'reason': reason},
    );
    final data = response['data'] as Map<String, dynamic>;
    return KitchenStockItem.fromJson(data['item'] as Map<String, dynamic>);
  }

  Future<List<KitchenStockTransaction>> getStockTransactions(
    int menuItemId,
  ) async => (await getStockTransactionPage(menuItemId)).items;

  Future<KitchenPage<KitchenStockTransaction>> getStockTransactionPage(
    int menuItemId, {
    int page = 1,
  }) async {
    final response = await api.get(
      '/kitchen-mobile/stock/$menuItemId/transactions?page=$page',
    );
    final data = response['data'] as Map<String, dynamic>;
    return KitchenPage.fromData(
      data,
      'transactions',
      KitchenStockTransaction.fromJson,
    );
  }

  Future<void> signOut() async {
    try {
      await api.post('/kitchen-mobile/auth/logout');
    } finally {
      await tokens.clear();
    }
  }
}
