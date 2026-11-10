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

  Future<List<KotTicket>> getTickets(int kitchenId) async {
    final response = await api.get('/kitchen-mobile/kitchens/$kitchenId/kot');
    final data = response['data'] as Map<String, dynamic>;
    return (data['tickets'] as List<dynamic>? ?? const [])
        .whereType<Map<String, dynamic>>()
        .map(KotTicket.fromJson)
        .toList(growable: false);
  }

  Future<void> updateStatus(int ticketId, String status) async {
    await api.patch(
      '/kitchen-mobile/kot-tickets/$ticketId/status',
      body: {'status': status},
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
