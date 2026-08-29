import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers/app_providers.dart';
import '../../../core/services/session_manager.dart';
import '../../inspections/providers/assignments_providers.dart';
import '../data/auth_repository.dart';
import '../models/auth_state.dart';
import '../providers/auth_providers.dart';

/// Drives authentication lifecycle: restoring a persisted session, logging in,
/// and logging out.
class AuthController extends AsyncNotifier<AuthState> {
  /// Fires whenever the authenticated flag flips so GoRouter can re-evaluate
  /// its redirect guard.
  final ValueNotifier<bool> _authListenable = ValueNotifier<bool>(false);
  ValueListenable<bool> get authListenable => _authListenable;

  bool _isAuthenticated = false;
  bool get isAuthenticated => _isAuthenticated;

  /// Restores a persisted session on cold start. Returns [AuthUnknown] while
  /// loading synchronously; [AuthAuthenticated] or [AuthUnauthenticated] after
  /// the storage lookup completes.
  @override
  Future<AuthState> build() async {
    final SessionManager session = ref.watch(sessionManagerProvider);
    await session.restore();

    if (session.isAuthenticated) {
      _setAuthenticated(true);
      return AuthAuthenticated(session.user!);
    }
    return const AuthUnauthenticated();
  }

  Future<void> login({
    required String email,
    required String password,
    bool rememberMe = false,
  }) async {
    state = const AsyncLoading();
    try {
      final AuthRepository repo = ref.read(authRepositoryProvider);
      final AuthSession session = await repo.login(
        email: email,
        password: password,
        rememberMe: rememberMe,
      );

      final SessionManager manager = ref.read(sessionManagerProvider);
      await manager.applySession(token: session.token, user: session.user);

      state = AsyncData(AuthAuthenticated(session.user));
      // Flip the auth flag only after the state lands so GoRouter's redirect
      // guard observes the new authenticated state when it re-evaluates.
      _setAuthenticated(true);
    } catch (error, stackTrace) {
      state = AsyncError(error, stackTrace);
    }
  }

  Future<void> logout() async {
    try {
      await ref.read(authRepositoryProvider).logout();
    } catch (_) {
      // The token may already be invalid; clear locally regardless.
    }
    await ref.read(sessionManagerProvider).clear();
    // Never leave one inspector's cached assignments behind for the next user.
    await ref.read(assignmentCacheProvider).clear();
    try {
      final db = ref.read(appDatabaseProvider);
      await db.clearAssignments();
      await db.clearQueue();
      // Ensure next login sees 0 pending (P2-20).
      ref.invalidate(pendingSyncCountProvider);
    } catch (_) {
      // DB may not be initialized yet — ignore.
    }
    state = const AsyncData(AuthUnauthenticated());
    _setAuthenticated(false);
  }

  void _setAuthenticated(bool value) {
    if (_isAuthenticated == value) return;
    _isAuthenticated = value;
    _authListenable.value = !_authListenable.value;
  }
}
