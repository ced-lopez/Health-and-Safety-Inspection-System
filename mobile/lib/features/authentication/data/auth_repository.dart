import '../../../../core/models/user.dart';
import '../../../../core/services/token_storage.dart';
import '../models/auth_state.dart';
import 'auth_datasource.dart';

/// Orchestrates authentication by bridging the [AuthDataSource] (network) and
/// [TokenStorage] (persistence).
class AuthRepository {
  AuthRepository(this._dataSource, this._tokenStorage);

  final AuthDataSource _dataSource;
  final TokenStorage _tokenStorage;

  Future<AuthSession> login({
    required String email,
    required String password,
    bool rememberMe = false,
  }) async {
    final AuthSession session = await _dataSource.login(
      email: email,
      password: password,
    );
    if (rememberMe) {
      await _tokenStorage.setRememberMe(true);
    }
    return session;
  }

  Future<User> fetchMe() => _dataSource.me();

  Future<void> logout() => _dataSource.logout();
}
