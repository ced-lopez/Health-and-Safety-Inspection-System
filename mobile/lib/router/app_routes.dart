/// Central route path registry for the app.
library;

abstract final class AppRoutes {
  static const String login = '/login';

  static const String home = '/';
  static const String dashboard = '/dashboard';
  static const String inspections = '/inspections';
  static const String violations = '/violations';
  static const String profile = '/profile';
  static const String settings = '/settings';

  /// Detail for a single inspection assignment.
  static const String inspectionDetail = '/inspections/:id';
  static String inspectionDetailPath(String id) => '/inspections/$id';

  /// Violations.
  static const String violationNew = '/violations/new';
  static String violationDetailPath(String id) => '/violations/$id';
}
