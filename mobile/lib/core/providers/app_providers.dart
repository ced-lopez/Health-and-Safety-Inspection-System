import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../database/app_database.dart';
import '../services/app_dio.dart';
import '../services/connectivity_service.dart';
import '../services/session_manager.dart';
import '../services/token_storage.dart';

/// Wraps secure persistence for the Sanctum token + user.
final Provider<TokenStorage> tokenStorageProvider = Provider<TokenStorage>(
  (ref) => TokenStorage(),
);

/// In-memory session (token + user), backed by [tokenStorageProvider].
final Provider<SessionManager> sessionManagerProvider =
    Provider<SessionManager>(
      (ref) => SessionManager(ref.watch(tokenStorageProvider)),
    );

/// Monitors connectivity so features can react to offline/online transitions.
final Provider<ConnectivityService> connectivityServiceProvider =
    Provider<ConnectivityService>((ref) {
      final service = ConnectivityService();
      ref.onDispose(service.dispose);
      return service;
    });

/// Stream of connectivity state (`true` = online).
final StreamProvider<bool> onlineProvider = StreamProvider<bool>((ref) {
  final service = ref.watch(connectivityServiceProvider);
  return service.onlineChanges;
});

/// Transport gateway used by every data source.
final Provider<ApiGateway> apiGatewayProvider = Provider<ApiGateway>(
  (ref) => ApiGateway(AppDio.create(ref.watch(sessionManagerProvider))),
);

/// SQLite database singleton for offline cache + sync queue.
final Provider<AppDatabase> appDatabaseProvider = Provider<AppDatabase>(
  (ref) => AppDatabase.instance,
);

/// Pending sync count — watches the queue size; invalidated after enqueue/process.
/// Defined here to avoid circular deps; underlying queue lives in AppDatabase.
final FutureProvider<int> pendingSyncCountProvider = FutureProvider<int>((
  ref,
) async {
  final AppDatabase db = ref.watch(appDatabaseProvider);
  return db.pendingCount();
});

/// Active theme mode; persisted by the Settings feature in a later phase.
final StateProvider<ThemeMode> themeModeProvider = StateProvider<ThemeMode>(
  (ref) => ThemeMode.system,
);
