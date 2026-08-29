import '../../../core/models/user.dart';

/// A successful login response: a Sanctum token paired with the user.
class AuthSession {
  const AuthSession({required this.token, required this.user});

  final String token;
  final User user;
}

/// Authentication state machine consumed by the router guard.
sealed class AuthState {
  const AuthState();
}

/// Initial state before the persisted session is restored.
class AuthUnknown extends AuthState {
  const AuthUnknown();
}

/// No valid session — the user must sign in.
class AuthUnauthenticated extends AuthState {
  const AuthUnauthenticated();
}

/// A signed-in user (inspector).
class AuthAuthenticated extends AuthState {
  const AuthAuthenticated(this.user);

  final User user;
}
