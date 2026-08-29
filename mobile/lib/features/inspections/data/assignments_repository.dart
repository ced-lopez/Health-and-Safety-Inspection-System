import '../../../../../core/database/app_database.dart';
import '../models/assignment.dart';
import 'assignment_cache.dart';
import 'assignments_datasource.dart';

/// Orchestrates assignments by bridging the network data source and the local
/// offline cache. The controller asks this repository to refresh (network, then
/// cache on failure) or read the cache directly.
class AssignmentsRepository {
  AssignmentsRepository(this._dataSource, this._cache, this._db);

  final AssignmentsDataSource _dataSource;
  final AssignmentCache _cache;
  final AppDatabase _db;

  /// Fetches from the network and updates the offline cache (SQLite source of truth).
  Future<AssignmentsPage> refresh({String? status}) async {
    final AssignmentsPage page = await _dataSource.fetch(status: status);
    await _db.cacheAssignments(page.raw);
    // SQLite is source of truth; SharedPreferences write removed (P2-17).
    // Old key is deleted lazily on next readCached migration.
    return page;
  }

  /// Returns the last cached assignments, or null when nothing is cached.
  /// SQLite is source of truth; SharedPreferences is read once for migration
  /// and then deleted. This makes SQLite authoritative after first migration.
  Future<List<InspectionAssignment>?> readCached() async {
    final List<Map<String, dynamic>> dbRaw = await _db.readAssignments();
    if (dbRaw.isNotEmpty) {
      return dbRaw.map(InspectionAssignment.fromJson).toList();
    }
    // One-time migration from legacy prefs.
    final List<Map<String, dynamic>>? prefRaw = await _cache.read();
    if (prefRaw != null && prefRaw.isNotEmpty) {
      await _db.cacheAssignments(prefRaw);
      // Delete legacy key so future reads don't hit prefs (P2-17).
      await _cache.clear();
      return prefRaw.map(InspectionAssignment.fromJson).toList();
    }
    await _db.migrateFromPrefsIfNeeded();
    // migrateFromPrefsIfNeeded also handles delete internally on success.
    final List<Map<String, dynamic>> migrated = await _db.readAssignments();
    if (migrated.isNotEmpty) {
      return migrated.map(InspectionAssignment.fromJson).toList();
    }
    return null;
  }

  /// Detail helpers — thin pass-through to the data source. Offline fallback
  /// for detail is handled at the controller/provider layer in a later phase.
  Future<InspectionAssignment> fetchOne(String id) => _dataSource.fetchOne(id);
  Future<InspectionAssignment> start(String id) => _dataSource.start(id);
  Future<InspectionAssignment> submit(String id, {String? notes}) =>
      _dataSource.submit(id, notes: notes);
}
