import 'dart:convert';
import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:image_picker/image_picker.dart';
import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';

import '../database/app_database.dart';
import '../../features/inspections/data/assignments_repository.dart';
import '../../features/inspections/data/checklist_datasource.dart';
import '../../features/inspections/data/report_datasource.dart';
import '../../features/inspections/models/checklist.dart';
import '../../features/violations/data/violations_datasource.dart';
import '../errors/api_exception.dart';

/// Handles offline queuing and replay for inspector operations.
///
/// Watches connectivity via `onlineProvider` (outside) and calls
/// `processQueue()` when the device comes online. Each operation is
/// replayed via the same datasource that the UI uses online, so
/// validation and server merging remain identical.
class SyncService {
  SyncService(
    this._db,
    this._assignmentsRepo,
    this._checklistDs,
    this._reportDs,
    this._violationsDs,
  );

  final AppDatabase _db;
  final AssignmentsRepository _assignmentsRepo;
  final ChecklistDataSource _checklistDs;
  final ReportDataSource _reportDs;
  final ViolationsDataSource _violationsDs;

  bool _processing = false;

  // ---------------------------------------------------------------------------
  // Enqueue helpers — called from UI catch(isNetwork) blocks.
  // ---------------------------------------------------------------------------

  Future<void> enqueueStart(String assignmentId) async {
    await _db.enqueue(
      operation: 'assignment_start',
      assignmentId: assignmentId,
    );
    debugPrint('[sync] queued start $assignmentId');
  }

  Future<void> enqueueSubmit(String assignmentId, {String? notes}) async {
    await _db.enqueue(
      operation: 'assignment_submit',
      assignmentId: assignmentId,
      payload: {if (notes != null && notes.isNotEmpty) 'notes': notes},
    );
    debugPrint('[sync] queued submit $assignmentId');
  }

  Future<void> enqueueChecklistSave({
    required String assignmentId,
    required List<ChecklistResultInput> results,
    Map<int, List<XFile>> evidenceFiles = const {},
  }) async {
    // Persist evidence files to app documents so OS cache purge doesn't lose them.
    final Map<String, dynamic> evidenceMap = {};
    for (final entry in evidenceFiles.entries) {
      final List<String> persisted = [];
      for (final XFile f in entry.value) {
        final String? copied = await _persistXFile(f);
        persisted.add(copied ?? f.path);
      }
      evidenceMap['${entry.key}'] = persisted;
    }
    await _db.enqueue(
      operation: 'checklist_save',
      assignmentId: assignmentId,
      payload: {
        'results': results
            .map(
              (r) => {
                'checklist_item_id': r.checklistItemId,
                'compliance_status': r.complianceStatus,
                if (r.remarks != null && r.remarks!.trim().isNotEmpty)
                  'remarks': r.remarks!.trim(),
              },
            )
            .toList(),
        'evidenceMap': evidenceMap,
      },
    );
    debugPrint('[sync] queued checklist $assignmentId ${results.length} items');
  }

  Future<void> enqueueReportUpdate({
    required String assignmentId,
    String? overallAssessment,
    String? recommendations,
    String? notes,
  }) async {
    await _db.enqueue(
      operation: 'report_update',
      assignmentId: assignmentId,
      payload: {
        'overall_assessment': ?overallAssessment,
        'recommendations': ?recommendations,
        'notes': ?notes,
      },
    );
    debugPrint('[sync] queued report $assignmentId');
  }

  Future<void> enqueueViolationCreate({
    required int inspectionId,
    int? inspectionResultId,
    int? assignedTo,
    required String title,
    required String description,
    required String severity,
    required String status,
    String? correctionDeadline,
    List<XFile> evidenceFiles = const [],
  }) async {
    final List<String> persistedPaths = [];
    for (final XFile f in evidenceFiles) {
      final String? copied = await _persistXFile(f);
      persistedPaths.add(copied ?? f.path);
    }
    await _db.enqueue(
      operation: 'violation_create',
      payload: {
        'inspection_id': inspectionId,
        'inspection_result_id': ?inspectionResultId,
        'assigned_to': ?assignedTo,
        'title': title,
        'description': description,
        'severity': severity,
        'status': status,
        if (correctionDeadline != null && correctionDeadline.isNotEmpty)
          'correction_deadline': correctionDeadline,
        'evidencePaths': persistedPaths,
        'evidenceType': 'initial',
      },
    );
    debugPrint('[sync] queued violation $title');
  }

  // ---------------------------------------------------------------------------
  // Processing
  // ---------------------------------------------------------------------------

  /// Processes the queue FIFO. Stops on first network failure to preserve order.
  /// Non-network failures (422 validation, 403) are dropped after incrementing
  /// attempts (so operator can see last_error).
  Future<void> processQueue() async {
    if (_processing) return;
    _processing = true;
    try {
      final List<Map<String, dynamic>> items = await _db.peekQueue(limit: 20);
      for (final Map<String, dynamic> row in items) {
        final int id = row['id'] as int;
        final String op = row['operation'] as String;
        final String? assignmentId = row['assignment_id'] as String?;
        final String? payloadJson = row['payload_json'] as String?;
        final int attempts = row['attempts'] as int? ?? 0;

        if (attempts >= 5) {
          debugPrint(
            '[sync] dead-letter $op id=$id after $attempts attempts — needs manual retry',
          );
          continue;
        }
        // Exponential backoff: 2^attempts seconds since creation before retry.
        if (attempts > 0) {
          try {
            final DateTime createdAt =
                DateTime.tryParse(row['created_at'] as String? ?? '') ??
                DateTime.now();
            final int backoffSeconds = 1 << attempts; // 2,4,8,16,32
            if (DateTime.now().difference(createdAt).inSeconds <
                backoffSeconds) {
              debugPrint(
                '[sync] backoff $op id=$id attempts=$attempts wait ${backoffSeconds}s',
              );
              continue;
            }
          } catch (_) {
            // If parsing fails, proceed without backoff.
          }
        }

        final Map<String, dynamic> payload =
            payloadJson != null && payloadJson.isNotEmpty
            ? (jsonDecode(payloadJson) as Map<String, dynamic>)
            : const {};

        try {
          await _replay(op, assignmentId, payload);
          await _deletePayloadFiles(op, payload);
          await _db.deleteQueueItem(id);
          debugPrint('[sync] ✔ $op id=$id');
        } on ApiException catch (e) {
          if (e.isNetwork) {
            await _db.incrementAttempts(id, e.message);
            debugPrint('[sync] network fail $op id=$id: ${e.message}');
            break;
          } else if (e.statusCode == 409) {
            await _db.incrementAttempts(id, e.message);
            debugPrint(
              '[sync] conflict 409 $op id=$id: ${e.message} — re-fetching',
            );
            try {
              if (assignmentId != null) {
                await _assignmentsRepo.fetchOne(assignmentId);
              }
            } catch (_) {
              // Re-fetch may fail if still offline; keep for retry.
            }
            continue;
          } else if (e.statusCode == 401 || e.statusCode == 403) {
            await _db.incrementAttempts(id, e.message);
            debugPrint('[sync] auth fail $op id=$id: ${e.message}');
            break;
          } else {
            await _db.incrementAttempts(id, e.message);
            debugPrint(
              '[sync] server fail $op id=$id [${e.statusCode}]: ${e.message}',
            );
            continue;
          }
        } catch (e) {
          await _db.incrementAttempts(id, e.toString());
          debugPrint('[sync] unexpected fail $op id=$id: $e');
          continue;
        }
      }
    } finally {
      _processing = false;
    }
  }

  Future<void> _replay(
    String op,
    String? assignmentId,
    Map<String, dynamic> payload,
  ) async {
    switch (op) {
      case 'assignment_start':
        if (assignmentId == null) throw Exception('missing assignmentId');
        await _assignmentsRepo.start(assignmentId);
        break;
      case 'assignment_submit':
        if (assignmentId == null) throw Exception('missing assignmentId');
        await _assignmentsRepo.submit(
          assignmentId,
          notes: payload['notes'] as String?,
        );
        break;
      case 'checklist_save':
        if (assignmentId == null) throw Exception('missing assignmentId');
        final List<dynamic> rawResults = payload['results'] is List
            ? payload['results'] as List
            : const [];
        final List<ChecklistResultInput> results = rawResults
            .whereType<Map<String, dynamic>>()
            .map(
              (m) => ChecklistResultInput(
                checklistItemId: (m['checklist_item_id'] as num).toInt(),
                complianceStatus: m['compliance_status'] as String,
                remarks: m['remarks'] as String?,
              ),
            )
            .toList();
        final Map<String, dynamic> evidenceMap =
            payload['evidenceMap'] is Map<String, dynamic>
            ? payload['evidenceMap'] as Map<String, dynamic>
            : const {};
        final Map<int, List<XFile>> evidenceFiles = {
          for (final e in evidenceMap.entries)
            int.parse(e.key): (e.value is List
                ? (e.value as List).map((p) => XFile(p.toString())).toList()
                : <XFile>[]),
        };
        await _checklistDs.save(
          assignmentId: assignmentId,
          results: results,
          evidenceFiles: evidenceFiles,
        );
        break;
      case 'report_update':
        if (assignmentId == null) throw Exception('missing assignmentId');
        await _reportDs.update(
          assignmentId: assignmentId,
          overallAssessment: payload['overall_assessment'] as String?,
          recommendations: payload['recommendations'] as String?,
          notes: payload['notes'] as String?,
        );
        break;
      case 'violation_create':
        final List<dynamic> rawEvidence = payload['evidencePaths'] is List
            ? payload['evidencePaths'] as List
            : const [];
        final List<XFile> files = rawEvidence
            .map((p) => XFile(p.toString()))
            .toList();
        final int inspectionId = (payload['inspection_id'] as num).toInt();
        final created = await _violationsDs.create(
          inspectionId: inspectionId,
          inspectionResultId: (payload['inspection_result_id'] as num?)
              ?.toInt(),
          assignedTo: (payload['assigned_to'] as num?)?.toInt(),
          title: payload['title'] as String,
          description: payload['description'] as String,
          severity: payload['severity'] as String,
          status: payload['status'] as String,
          correctionDeadline: payload['correction_deadline'] as String?,
        );
        if (files.isNotEmpty) {
          await _violationsDs.uploadEvidence(
            violationId: '${created.id}',
            evidenceType: payload['evidenceType'] as String? ?? 'initial',
            files: files,
          );
        }
        break;
      default:
        throw Exception('unknown operation $op');
    }
  }

  Future<int> pendingCount() => _db.pendingCount();

  Future<List<Map<String, dynamic>>> peekQueue() => _db.peekQueue();

  // ---------------------------------------------------------------------------
  // File persistence helpers for P0-1.
  // ---------------------------------------------------------------------------

  Future<String?> _persistXFile(XFile file) async {
    try {
      final Directory docs = await getApplicationDocumentsDirectory();
      final Directory pendingDir = Directory(p.join(docs.path, 'pending'));
      if (!await pendingDir.exists()) {
        await pendingDir.create(recursive: true);
      }
      final String base = p.basename(file.path);
      final String target = p.join(
        pendingDir.path,
        '${DateTime.now().microsecondsSinceEpoch}_$base',
      );
      final File src = File(file.path);
      if (!await src.exists()) return null;
      final File copied = await src.copy(target);
      return copied.path;
    } catch (_) {
      return null;
    }
  }

  Future<void> _deletePayloadFiles(
    String op,
    Map<String, dynamic> payload,
  ) async {
    try {
      final List<String> paths = [];
      if (op == 'checklist_save') {
        final Map<String, dynamic> evidenceMap =
            payload['evidenceMap'] is Map<String, dynamic>
            ? payload['evidenceMap'] as Map<String, dynamic>
            : const {};
        for (final entry in evidenceMap.values) {
          if (entry is List) {
            for (final p in entry) {
              if (p is String) paths.add(p);
            }
          }
        }
      } else if (op == 'violation_create') {
        final List<dynamic> raw = payload['evidencePaths'] is List
            ? payload['evidencePaths'] as List
            : const [];
        for (final p in raw) {
          if (p is String) paths.add(p);
        }
      }
      for (final String path in paths) {
        // Only delete files that live in our pending dir (avoid deleting user originals).
        if (path.contains('/pending/')) {
          final File f = File(path);
          if (await f.exists()) {
            await f.delete();
          }
        }
      }
    } catch (_) {
      // Best-effort cleanup.
    }
  }
}
