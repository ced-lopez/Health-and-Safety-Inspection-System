import 'package:flutter/foundation.dart';

import '../models/user.dart';
import 'token_storage.dart';

/// In-memory session state backing the [TokenStorage]. Exposes the current
/// Sanctum token and authenticated user to the transport and UI layers. Any
/// change notifies listeners so the router and token interceptor stay in sync.
class SessionManager extends ChangeNotifier {
  SessionManager(this._storage);

  final TokenStorage _storage;

  String? _token;
  User? _user;
  bool _restored = false;

  String? get token => _token;
  User? get user => _user;
  bool get isAuthenticated => _token != null && _user != null;

  /// Loads any persisted session back into memory. Idempotent.
  Future<void> restore() async {
    if (_restored) return;
    _token = await _storage.readToken();
    _user = await _storage.readUser();
    _restored = true;
    notifyListeners();
  }

  Future<void> applySession({required String token, required User user}) async {
    _token = token;
    _user = user;
    await _storage.saveSession(token: token, user: user);
    notifyListeners();
  }

  Future<void> clear() async {
    _token = null;
    _user = null;
    await _storage.clear();
    notifyListeners();
  }
}
