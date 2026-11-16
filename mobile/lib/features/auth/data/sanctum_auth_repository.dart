import 'dart:io';

import '../../../core/network/mobile_api_client.dart';
import '../auth_repository.dart';
import 'secure_token_store.dart';

class SanctumAuthRepository implements AuthRepository {
  SanctumAuthRepository({required this.api, required this.tokens});

  final MobileApiClient api;
  final SecureTokenStore tokens;
  int? _currentUserId;

  @override
  int? get currentUserId => _currentUserId;

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
    _captureUser(response);
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
      _captureUser(await api.get('auth/me'));
      return true;
    } on ApiException catch (error) {
      if (error.statusCode != 401 && error.statusCode != 403) rethrow;
      await tokens.clear();
      _currentUserId = null;
      return false;
    }
  }

  @override
  Future<Set<String>> loadPermissions() async {
    final response = await api.get('auth/me');
    _captureUser(response);
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
      _currentUserId = null;
    }
  }

  void _captureUser(Map<String, dynamic> response) {
    final data = response['data'];
    final user = data is Map<String, dynamic> ? data['user'] : null;
    final id = user is Map<String, dynamic> ? user['id'] : null;
    if (id is! num || id < 1) {
      throw const ApiException(
        502,
        'The server did not return your staff identity.',
      );
    }
    _currentUserId = id.toInt();
  }
}
