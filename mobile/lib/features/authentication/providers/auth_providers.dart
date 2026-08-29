import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers/app_providers.dart';
import '../data/auth_datasource.dart';
import '../data/auth_repository.dart';
import '../models/auth_state.dart';
import '../presentation/login_controller.dart';

final Provider<AuthDataSource> authDataSourceProvider =
    Provider<AuthDataSource>(
      (ref) => AuthDataSource(ref.watch(apiGatewayProvider)),
    );

final Provider<AuthRepository> authRepositoryProvider =
    Provider<AuthRepository>(
      (ref) => AuthRepository(
        ref.watch(authDataSourceProvider),
        ref.watch(tokenStorageProvider),
      ),
    );

/// Owns the authentication state machine. Read it to gate navigation and call
/// its [AuthController.login]/[logout] from the UI.
final AsyncNotifierProvider<AuthController, AuthState> authControllerProvider =
    AsyncNotifierProvider<AuthController, AuthState>(AuthController.new);
