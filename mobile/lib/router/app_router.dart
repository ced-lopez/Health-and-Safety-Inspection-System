import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../features/authentication/models/auth_state.dart';
import '../features/authentication/presentation/login_controller.dart';
import '../features/authentication/providers/auth_providers.dart';
import '../features/authentication/presentation/login_screen.dart';
import '../features/dashboard/presentation/dashboard_screen.dart';
import '../features/inspections/presentation/assignment_detail_screen.dart';
import '../features/inspections/presentation/checklist_screen.dart';
import '../features/inspections/presentation/inspections_screen.dart';
import '../features/inspections/presentation/report_screen.dart';
import '../features/profile/presentation/profile_screen.dart';
import '../features/settings/presentation/settings_screen.dart';
import '../features/sync/presentation/sync_queue_screen.dart';
import '../features/violations/presentation/violation_detail_screen.dart';
import '../features/violations/presentation/violation_form_screen.dart';
import '../features/violations/presentation/violations_screen.dart';
import '../widgets/layout/app_shell.dart';
import 'app_routes.dart';

final GlobalKey<NavigatorState> _rootNavigatorKey = GlobalKey<NavigatorState>();

/// Provides the [GoRouter] instance. The auth controller is watched here so the
/// redirect guard always evaluates against the live authentication state.
final Provider<GoRouter> routerProvider = Provider<GoRouter>((ref) {
  final AuthController authController = ref.watch(
    authControllerProvider.notifier,
  );

  return GoRouter(
    navigatorKey: _rootNavigatorKey,
    initialLocation: AppRoutes.dashboard,
    refreshListenable: authController.authListenable,
    redirect: (BuildContext context, GoRouterState state) {
      final AsyncValue<AuthState> authValue = ref.read(authControllerProvider);
      final bool isLoading =
          authValue.isLoading || authValue.valueOrNull is AuthUnknown;
      final bool isAuthed = authValue.valueOrNull is AuthAuthenticated;
      final bool isAtLogin = state.matchedLocation == AppRoutes.login;

      if (isLoading) return null;

      if (!isAuthed && !isAtLogin) return AppRoutes.login;
      if (isAuthed && isAtLogin) return AppRoutes.dashboard;
      return null;
    },
    routes: [
      GoRoute(
        path: AppRoutes.login,
        builder: (context, state) => const LoginScreen(),
      ),
      GoRoute(
        path: AppRoutes.home,
        redirect: (context, state) => AppRoutes.dashboard,
      ),
      GoRoute(
        path: AppRoutes.settings,
        parentNavigatorKey: _rootNavigatorKey,
        builder: (context, state) => const SettingsScreen(),
      ),
      // Cross-shell routes (no bottom nav) — use root navigator so they can be
      // pushed from any branch (e.g., checklist → violation form, pending banner → sync queue).
      GoRoute(
        path: AppRoutes.violationNew,
        parentNavigatorKey: _rootNavigatorKey,
        builder: (context, state) {
          final Object? extra = state.extra;
          int? inspectionId;
          int? resultId;
          String? title;
          String? desc;
          if (extra is Map) {
            inspectionId = extra['inspectionId'] as int?;
            resultId = extra['inspectionResultId'] as int?;
            title = extra['title'] as String?;
            desc = extra['description'] as String?;
          }
          return ViolationFormScreen(
            initialInspectionId: inspectionId,
            initialInspectionResultId: resultId,
            initialTitle: title,
            initialDescription: desc,
          );
        },
      ),
      GoRoute(
        path: '/sync-queue',
        parentNavigatorKey: _rootNavigatorKey,
        builder: (context, state) => const SyncQueueScreen(),
      ),
      StatefulShellRoute.indexedStack(
        builder: (context, state, navigationShell) =>
            AppShell(navigationShell: navigationShell),
        branches: [
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: AppRoutes.dashboard,
                builder: (context, state) => const DashboardScreen(),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: AppRoutes.inspections,
                builder: (context, state) => const InspectionsScreen(),
                routes: [
                  GoRoute(
                    path: ':id',
                    builder: (context, state) {
                      final String id = state.pathParameters['id'] ?? '';
                      return AssignmentDetailScreen(assignmentId: id);
                    },
                    routes: [
                      GoRoute(
                        path: 'checklist',
                        builder: (context, state) {
                          final String id = state.pathParameters['id'] ?? '';
                          final Object? extra = state.extra;
                          final bool ro =
                              extra is Map && extra['readOnly'] == true;
                          return ChecklistScreen(
                            key: ValueKey('checklist-$id'),
                            assignmentId: id,
                            readOnly: ro,
                          );
                        },
                      ),
                      GoRoute(
                        path: 'report',
                        builder: (context, state) {
                          final String id = state.pathParameters['id'] ?? '';
                          final Object? extra = state.extra;
                          final bool ro =
                              extra is Map && extra['readOnly'] == true;
                          return ReportScreen(
                            key: ValueKey('report-$id'),
                            assignmentId: id,
                            readOnly: ro,
                          );
                        },
                      ),
                    ],
                  ),
                ],
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: AppRoutes.violations,
                builder: (context, state) => const ViolationsScreen(),
                routes: [
                  GoRoute(
                    path: ':id',
                    builder: (context, state) {
                      final String id = state.pathParameters['id'] ?? '';
                      return ViolationDetailScreen(violationId: id);
                    },
                  ),
                ],
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: AppRoutes.profile,
                builder: (context, state) => const ProfileScreen(),
                routes: [
                  GoRoute(
                    path: 'settings',
                    builder: (context, state) => const SettingsScreen(),
                  ),
                ],
              ),
            ],
          ),
        ],
      ),
    ],
  );
});
