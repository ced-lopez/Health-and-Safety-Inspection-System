/// Input validation helpers shared by forms (login, applicant details, etc.).
library;

abstract final class Validators {
  static String? required(
    String? value, {
    String message = 'This field is required.',
  }) {
    if (value == null || value.trim().isEmpty) return message;
    return null;
  }

  static String? email(String? value, {String? message}) {
    if (value == null || value.trim().isEmpty) return 'Email is required.';
    final bool valid = RegExp(
      r'^[^@\s]+@[^@\s]+\.[^@\s]+$',
    ).hasMatch(value.trim());
    if (!valid) return message ?? 'Enter a valid email address.';
    return null;
  }

  static String? password(String? value, {int minLength = 8, String? message}) {
    if (value == null || value.isEmpty) return 'Password is required.';
    if (value.length < minLength) {
      return message ?? 'Password must be at least $minLength characters.';
    }
    return null;
  }

  static String? requiredWithLength(
    String? value,
    int maxLength, {
    String? message,
  }) {
    final String? base = _required(value);
    if (base != null) return base;
    if (value!.length > maxLength) {
      return message ?? 'Must be $maxLength characters or fewer.';
    }
    return null;
  }

  static String? _required(String? value) =>
      (value == null || value.trim().isEmpty)
      ? 'This field is required.'
      : null;
}
