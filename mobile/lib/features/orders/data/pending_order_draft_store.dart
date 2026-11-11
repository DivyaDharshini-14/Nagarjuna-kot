import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class PendingOrderDraftStore {
  PendingOrderDraftStore({FlutterSecureStorage? storage})
    : _storage = storage ?? const FlutterSecureStorage();

  static const _key = 'pending_order_submission_v1';
  final FlutterSecureStorage _storage;

  Future<Map<String, dynamic>?> read() async {
    final value = await _storage.read(key: _key);
    if (value == null || value.isEmpty) return null;
    try {
      final decoded = jsonDecode(value);
      return decoded is Map<String, dynamic> ? decoded : null;
    } on FormatException {
      await clear();
      return null;
    }
  }

  Future<void> write(Map<String, Object?> value) =>
      _storage.write(key: _key, value: jsonEncode(value));

  Future<void> clear() => _storage.delete(key: _key);
}
