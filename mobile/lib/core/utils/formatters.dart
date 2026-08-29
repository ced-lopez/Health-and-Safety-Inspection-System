/// Formatting helpers built on `intl`.
library;

import 'package:intl/intl.dart';

abstract final class Formatters {
  static final DateFormat _date = DateFormat('MMM d, yyyy');
  static final DateFormat _dateTime = DateFormat('MMM d, yyyy · h:mm a');

  /// "2024-01-31T08:00:00+08:00" / ISO family -> "Jan 31, 2024".
  static String date(String? iso) {
    if (iso == null || iso.isEmpty) return '—';
    final DateTime? t = DateTime.tryParse(iso);
    return t == null ? iso : _date.format(t.toLocal());
  }

  /// ISO -> "Jan 31, 2024 · 8:00 AM".
  static String dateTime(String? iso) {
    if (iso == null || iso.isEmpty) return '—';
    final DateTime? t = DateTime.tryParse(iso);
    return t == null ? iso : _dateTime.format(t.toLocal());
  }

  /// Capitalises a snake_case status into a friendly label.
  static String humanize(String? value) {
    if (value == null || value.isEmpty) return '';
    final List<String> parts = value.split('_');
    return parts
        .map((p) => p.isEmpty ? p : p[0].toUpperCase() + p.substring(1))
        .join(' ');
  }
}
