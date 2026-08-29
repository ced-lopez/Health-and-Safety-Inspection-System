import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../constants/storage_keys.dart';
import '../models/user.dart';

/// Persists auth secrets (Sanctum token) and the remembered user. The token is
/// stored via [FlutterSecureStorage] so it never lands in plain-text prefs.
class TokenStorage {
  TokenStorage({FlutterSecureStorage? storage})
    : _storage = storage ?? const FlutterSecureStorage();

  final FlutterSecureStorage _storage;

  Future<void> saveSession({required String token, required User user}) async {
    await _storage.write(key: StorageKeys.authToken, value: token);
    await _storage.write(
      key: StorageKeys.authUser,
      value: jsonEncode(user.toJson()),
    );
  }

  Future<String?> readToken() => _storage.read(key: StorageKeys.authToken);

  Future<User?> readUser() async {
    final String? raw = await _storage.read(key: StorageKeys.authUser);
    if (raw == null || raw.isEmpty) return null;
    try {
      return User.fromJson(jsonDecode(raw) as Map<String, dynamic>);
    } catch (_) {
      return null;
    }
  }

  Future<void> clear() async {
    await _storage.delete(key: StorageKeys.authToken);
    await _storage.delete(key: StorageKeys.authUser);
  }

  Future<void> setRememberMe(bool value) =>
      _storage.write(key: StorageKeys.rememberMe, value: '$value');

  Future<bool> readRememberMe() async =>
      (await _storage.read(key: StorageKeys.rememberMe)) == 'true';
}
