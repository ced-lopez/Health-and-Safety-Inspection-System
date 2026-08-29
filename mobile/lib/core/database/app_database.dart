import 'dart:convert';

import 'package:path/path.dart' as p;
import 'package:shared_preferences/shared_preferences.dart';
import 'package:sqflite/sqflite.dart';

import '../constants/storage_keys.dart';

/// SQLite database for offline-first inspector app.
///
/// Replaces `AssignmentCache` (SharedPreferences) with a proper SQLite store
/// and adds a `sync_queue` for replaying failed operations when the device
/// regains connectivity. Fulfils `UPDATED PROMPT.txt:23` — SQLite + secure
/// storage + automatic sync.
///
/// Tables:
/// * `assignments` — cached `InspectionAssignment` JSON + derived columns for filtering.
/// * `sync_queue` — pending operations with payload + file paths, processed FIFO.
class AppDatabase {
  AppDatabase._();

  static final AppDatabase instance = AppDatabase._();

  static const int _version = 1;
  static const String _dbName = 'inspector.db';

  Database? _db;

  Future<Database> get database async {
    if (_db != null) return _db!;
    _db = await _open();
    return _db!;
  }

  Future<Database> _open() async {
    final String databasesPath = await getDatabasesPath();
    final String path = p.join(databasesPath, _dbName);
    return openDatabase(
      path,
      version: _version,
      onCreate: (Database db, int version) async {
        await _createAssignments(db);
        await _createSyncQueue(db);
      },
      onUpgrade: (Database db, int oldVersion, int newVersion) async {
        // Future migrations.
      },
    );
  }

  Future<void> _createAssignments(Database db) async {
    await db.execute('''
      CREATE TABLE assignments (
        id INTEGER PRIMARY KEY,
        status TEXT NOT NULL,
        server_version INTEGER NOT NULL DEFAULT 0,
        raw_json TEXT NOT NULL,
        updated_at TEXT NOT NULL
      )
    ''');
    await db.execute(
      'CREATE INDEX idx_assignments_status ON assignments(status)',
    );
  }

  Future<void> _createSyncQueue(Database db) async {
    await db.execute('''
      CREATE TABLE sync_queue (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        operation TEXT NOT NULL,
        assignment_id TEXT,
        violation_id TEXT,
        payload_json TEXT,
        file_paths_json TEXT,
        attempts INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL,
        last_error TEXT
      )
    ''');
    await db.execute(
      'CREATE INDEX idx_sync_queue_operation ON sync_queue(operation)',
    );
  }

  // ---------------------------------------------------------------------------
  // Assignments
  // ---------------------------------------------------------------------------

  Future<void> cacheAssignments(List<Map<String, dynamic>> rawList) async {
    final Database db = await database;
    final Batch batch = db.batch();
    batch.delete('assignments');
    final String now = DateTime.now().toIso8601String();
    for (final Map<String, dynamic> raw in rawList) {
      final int id = (raw['id'] as num).toInt();
      final String status = raw['status'] as String? ?? 'assigned';
      final int version = (raw['server_version'] as num?)?.toInt() ?? 0;
      batch.insert('assignments', {
        'id': id,
        'status': status,
        'server_version': version,
        'raw_json': jsonEncode(raw),
        'updated_at': now,
      }, conflictAlgorithm: ConflictAlgorithm.replace);
    }
    await batch.commit(noResult: true);
  }

  Future<List<Map<String, dynamic>>> readAssignments() async {
    final Database db = await database;
    final List<Map<String, dynamic>> rows = await db.query(
      'assignments',
      orderBy: 'updated_at DESC',
    );
    return rows.map((r) {
      final String raw = r['raw_json'] as String;
      return jsonDecode(raw) as Map<String, dynamic>;
    }).toList();
  }

  Future<void> clearAssignments() async {
    final Database db = await database;
    await db.delete('assignments');
  }

  /// Migrates SharedPreferences cache once into SQLite, then deletes the legacy key.
  /// After this, SQLite is source of truth (P2-17).
  Future<void> migrateFromPrefsIfNeeded() async {
    final SharedPreferences prefs = await SharedPreferences.getInstance();
    final String? raw = prefs.getString(StorageKeys.assignmentsCache);
    if (raw == null || raw.isEmpty) return;
    try {
      final dynamic decoded = jsonDecode(raw);
      if (decoded is List) {
        final List<Map<String, dynamic>> list = decoded
            .whereType<Map<String, dynamic>>()
            .toList();
        if (list.isNotEmpty) {
          final Database db = await database;
          final int count =
              Sqflite.firstIntValue(
                await db.rawQuery('SELECT COUNT(*) FROM assignments'),
              ) ??
              0;
          if (count == 0) {
            await cacheAssignments(list);
          }
          await prefs.remove(StorageKeys.assignmentsCache);
        } else {
          await prefs.remove(StorageKeys.assignmentsCache);
        }
      } else {
        await prefs.remove(StorageKeys.assignmentsCache);
      }
    } catch (_) {
      try {
        await prefs.remove(StorageKeys.assignmentsCache);
      } catch (_) {}
    }
  }

  // ---------------------------------------------------------------------------
  // Sync queue
  // ---------------------------------------------------------------------------

  Future<int> enqueue({
    required String operation,
    String? assignmentId,
    String? violationId,
    Map<String, dynamic>? payload,
    List<String>? filePaths,
  }) async {
    final Database db = await database;
    return db.insert('sync_queue', {
      'operation': operation,
      'assignment_id': assignmentId,
      'violation_id': violationId,
      'payload_json': payload != null ? jsonEncode(payload) : null,
      'file_paths_json': filePaths != null ? jsonEncode(filePaths) : null,
      'attempts': 0,
      'created_at': DateTime.now().toIso8601String(),
      'last_error': null,
    });
  }

  Future<List<Map<String, dynamic>>> peekQueue({int limit = 20}) async {
    final Database db = await database;
    return db.query('sync_queue', orderBy: 'created_at ASC', limit: limit);
  }

  Future<void> deleteQueueItem(int id) async {
    final Database db = await database;
    await db.delete('sync_queue', where: 'id = ?', whereArgs: [id]);
  }

  Future<void> incrementAttempts(int id, String? error) async {
    final Database db = await database;
    await db.rawUpdate(
      'UPDATE sync_queue SET attempts = attempts + 1, last_error = ? WHERE id = ?',
      [error, id],
    );
  }

  Future<int> pendingCount() async {
    final Database db = await database;
    final int count =
        Sqflite.firstIntValue(
          await db.rawQuery('SELECT COUNT(*) FROM sync_queue'),
        ) ??
        0;
    return count;
  }

  Future<void> clearQueue() async {
    final Database db = await database;
    await db.delete('sync_queue');
  }

  // For testing / debug.
  Future<void> close() async {
    if (_db != null) {
      await _db!.close();
      _db = null;
    }
  }
}
