import '../../../../core/constants/api_endpoints.dart';
import '../../../../core/errors/api_exception.dart';
import '../../../../core/models/user.dart';
import '../../../../core/services/app_dio.dart';
import '../models/auth_state.dart';

/// Network access for authentication against the existing Laravel API.
///
/// Inspectors sign in through the same `POST /v1/auth/login` endpoint as the
/// web app but must pass `portal: 'staff'` to satisfy the role gateway.
class AuthDataSource {
  AuthDataSource(this._gateway);

  final ApiGateway _gateway;

  /// Logs in and returns a Sanctum token + user. Throws [ApiException] when the
  /// account is unverified or belongs to a non-inspector role.
  Future<AuthSession> login({
    required String email,
    required String password,
  }) async {
    final dynamic response = await _gateway.post(
      ApiEndpoints.login,
      data: {'email': email, 'password': password, 'portal': 'staff'},
    );

    final Map<String, dynamic> data =
        (response is Map<String, dynamic> ? response['data'] : null)
            as Map<String, dynamic>? ??
        const {};

    final String? token = data['token'] as String?;
    if (token == null || token.isEmpty) {
      final String message =
          (response is Map<String, dynamic> ? response['message'] : null)
              as String? ??
          'Unable to sign in. Verify your account or contact an administrator.';
      throw ApiException(message: message, statusCode: 422);
    }

    return AuthSession(
      token: token,
      user: User.fromJson(data['user'] as Map<String, dynamic>),
    );
  }

  /// Returns the current authenticated user from `GET /v1/auth/me`.
  Future<User> me() async {
    final dynamic response = await _gateway.get(ApiEndpoints.me);
    final dynamic raw = response is Map<String, dynamic>
        ? response['data']
        : null;
    final Map<String, dynamic> data = raw is Map<String, dynamic>
        ? raw
        : const {};
    return User.fromJson(data);
  }

  Future<void> logout() => _gateway.post(ApiEndpoints.logout);
}
