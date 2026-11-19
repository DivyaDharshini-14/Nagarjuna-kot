import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class KitchenTokenStore {
  KitchenTokenStore({FlutterSecureStorage? storage})
    : _storage = storage ?? const FlutterSecureStorage();

  static const _tokenKey = 'kitchen_mobile_access_token';
  final FlutterSecureStorage _storage;

  Future<String?> read() => _storage.read(key: _tokenKey);
  Future<void> save(String token) =>
      _storage.write(key: _tokenKey, value: token);
  Future<void> clear() => _storage.delete(key: _tokenKey);

  String _stockKey(int userId, int itemId) =>
      'unconfirmed_stock_movement_v1_${userId}_$itemId';

  Future<String?> readUnconfirmedStock(int userId, int itemId) =>
      _storage.read(key: _stockKey(userId, itemId));

  Future<void> saveUnconfirmedStock(
    int userId,
    int itemId,
    String description,
  ) => _storage.write(key: _stockKey(userId, itemId), value: description);

  Future<void> clearUnconfirmedStock(int userId, int itemId) =>
      _storage.delete(key: _stockKey(userId, itemId));
}
