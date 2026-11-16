import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class KitchenTokenStore {
  KitchenTokenStore({FlutterSecureStorage? storage})
    : _storage = storage ?? const FlutterSecureStorage();

  static const _tokenKey = 'kitchen_mobile_access_token';
  static const _soundKey = 'kitchen_mobile_foreground_sound_enabled';
  final FlutterSecureStorage _storage;

  Future<String?> read() => _storage.read(key: _tokenKey);
  Future<void> save(String token) =>
      _storage.write(key: _tokenKey, value: token);
  Future<void> clear() => _storage.delete(key: _tokenKey);
  Future<bool> readSoundEnabled() async =>
      await _storage.read(key: _soundKey) == 'true';
  Future<void> saveSoundEnabled(bool enabled) =>
      _storage.write(key: _soundKey, value: '$enabled');
}
