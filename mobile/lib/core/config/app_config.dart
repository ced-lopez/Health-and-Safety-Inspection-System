/// Application-wide configuration. Values are compile-time constants that can
/// be overridden at build time via `--dart-define`.
abstract final class AppConfig {
  AppConfig._();

  static const String appName = 'Barangay 178 Inspector';
  static const String appVersion = '1.0.0';

  /// Base URL of the shared Laravel REST API.
  ///
  /// The default targets the Android emulator's host loopback (10.0.2.2). For a
  /// physical device or production, override at build time:
  ///   flutter run --dart-define=API_BASE_URL=https://api.example.com/api
  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8000/api',
  );

  static const Duration connectTimeout = Duration(seconds: 20);
  static const Duration receiveTimeout = Duration(seconds: 30);
  static const Duration sendTimeout = Duration(seconds: 30);
}
