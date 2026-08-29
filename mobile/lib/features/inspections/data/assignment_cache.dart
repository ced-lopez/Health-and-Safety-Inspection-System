import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

import '../../../../core/constants/storage_keys.dart';

/// Persists the last successful assignments fetch so the list renders without
/// a connection. Stores the raw API payload to stay forward-compatible with
/// fields the backend may add later.
class AssignmentCache {
  Future<List<Map<String, dynamic>>?> read() async {
    final SharedPreferences prefs = await SharedPreferences.getInstance();
    final String? raw = prefs.getString(StorageKeys.assignmentsCache);
    if (raw == null || raw.isEmpty) return null;
    try {
      final dynamic decoded = jsonDecode(raw);
      if (decoded is List) {
        return decoded.whereType<Map<String, dynamic>>().toList();
      }
    } catch (_) {
      // Corrupt cache — treat as empty.
    }
    return null;
  }

  Future<void> write(List<Map<String, dynamic>> raw) async {
    final SharedPreferences prefs = await SharedPreferences.getInstance();
    await prefs.setString(StorageKeys.assignmentsCache, jsonEncode(raw));
  }

  Future<void> clear() async {
    final SharedPreferences prefs = await SharedPreferences.getInstance();
    await prefs.remove(StorageKeys.assignmentsCache);
  }
}
