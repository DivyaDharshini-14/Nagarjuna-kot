import 'dart:io';

import '../../../core/network/mobile_api_client.dart';
import '../auth_repository.dart';
import 'secure_token_store.dart';

class SanctumAuthRepository implements AuthRepository {
  const SanctumAuthRepository({required this.api, required this.tokens});

  final MobileApiClient api;
  final SecureTokenStore tokens;

  @override
  Future<void> signIn({required String email, required String password}) async {
    final response = await api.post(
      'auth/login',
      authenticated: false,
      body: {
        'email': email,
        'password': password,
        'device_name': 'Nagarjuna Waiter (${Platform.operatingSystem})',
      },
    );
    final data = response['data'];
    final token = data is Map<String, dynamic> ? data['token'] : null;
    if (token is! String || token.isEmpty) {
      throw const ApiException(
        502,
        'The server did not provide a sign-in token.',
      );
    }
    await tokens.save(token);
  }

  @override
  Future<bool> restoreSession() async {
    final token = await tokens.read();
    if (token == null || token.isEmpty) return false;

    try {
      await api.get('auth/me');
      return true;
    } on ApiException catch (error) {
      if (error.statusCode != 401 && error.statusCode != 403) rethrow;
      await tokens.clear();
      return false;
    }
  }

  @override
  Future<Set<String>> loadPermissions() async {
    final response = await api.get('auth/me');
    final data = response['data'];
    final user = data is Map<String, dynamic> ? data['user'] : null;
    final permissions = user is Map<String, dynamic>
        ? user['permissions']
        : null;
    return permissions is List
        ? permissions.whereType<String>().toSet()
        : const <String>{};
  }

  @override
  Future<void> signOut() async {
    try {
      if (await tokens.read() != null) await api.post('auth/logout');
    } finally {
      await tokens.clear();
    }
  }
}
