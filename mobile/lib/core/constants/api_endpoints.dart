/// REST endpoint paths, relative to [AppConfig.apiBaseUrl]. These mirror the
/// existing Laravel routes (backend/routes/api.php). Never invent endpoints.
library;

import '../config/app_config.dart';

abstract final class ApiEndpoints {
  // ---------------------------------------------------------------------------
  // Authentication
  // ---------------------------------------------------------------------------
  static const String login = '/v1/auth/login';
  static const String logout = '/v1/auth/logout';
  static const String me = '/v1/auth/me';
  static const String updateProfile = '/v1/auth/profile';
  static const String changePassword = '/v1/auth/password';

  // ---------------------------------------------------------------------------
  // Inspector modules
  // ---------------------------------------------------------------------------
  static const String dashboard = '/v1/dashboard';
  static const String assignments = '/v1/inspection-assignments';
  static const String followUps = '/v1/follow-up';
  static const String notifications = '/v1/notifications';
  static const String violations = '/v1/violations';
  static const String violationsOptions = '/v1/violations/options';
  static const String establishments = '/v1/establishments';

  static String assignment(String id) => '$assignments/$id';
  static String assignmentStart(String id) => '$assignments/$id/start';
  static String assignmentChecklist(String id) => '$assignments/$id/checklist';
  static String assignmentReport(String id) => '$assignments/$id/report';
  static String assignmentUpdateReport(String id) => '$assignments/$id/report';
  static String assignmentSubmit(String id) => '$assignments/$id/submit';

  /// Params for detail screen helpers.
  static String assignmentDetail(String id) => assignment(id);
}
