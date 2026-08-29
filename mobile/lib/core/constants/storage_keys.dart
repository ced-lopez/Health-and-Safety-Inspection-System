/// Keys for persisted application data (secure storage and local prefs).
library;

abstract final class StorageKeys {
  static const String authToken = 'auth_token';
  static const String authUser = 'auth_user';
  static const String rememberMe = 'remember_me';
  static const String themeMode = 'theme_mode';
  static const String apiBaseUrl = 'api_base_url';
  static const String assignmentsCache = 'inspections:assignments_cache';
}
