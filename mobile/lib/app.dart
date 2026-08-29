import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import 'core/config/app_config.dart';
import 'core/database/app_database.dart';
import 'core/providers/app_providers.dart';
import 'core/theme/theme.dart';
import 'features/authentication/providers/auth_providers.dart';
import 'features/inspections/providers/assignments_providers.dart';
import 'router/app_router.dart';

/// Root widget of the Inspector Mobile Application.
class InspectionApp extends ConsumerStatefulWidget {
  const InspectionApp({super.key});

  @override
  ConsumerState<InspectionApp> createState() => _InspectionAppState();
}

class _InspectionAppState extends ConsumerState<InspectionApp>
    with WidgetsBindingObserver {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.detached) {
      // P2-18: avoid leaking openDatabase handles across hot restarts / termination.
      // ignore: discarded_futures
      AppDatabase.instance.close();
    }
  }

  @override
  Widget build(BuildContext context) {
    // Keep the auth provider alive so the persisted session is restored on
    // startup and the router guard evaluates immediately.
    ref.watch(authControllerProvider);
    // Keep the offline sync watcher alive (replays queue on reconnect, debounced).
    ref.watch(syncOnReconnectProvider);

    final GoRouter router = ref.watch(routerProvider);
    final ThemeMode themeMode = ref.watch(themeModeProvider);

    // Re-run the redirect guard on every auth state transition so navigation
    // always settles (login success, logout, expired restore, …).
    ref.listen(authControllerProvider, (previous, next) {
      router.refresh();
    });

    return MaterialApp.router(
      title: AppConfig.appName,
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light(),
      darkTheme: AppTheme.dark(),
      themeMode: themeMode,
      routerConfig: router,
      localizationsDelegates: const [
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      supportedLocales: const [Locale('en', 'PH')],
    );
  }
}
