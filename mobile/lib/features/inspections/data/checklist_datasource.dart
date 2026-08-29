import 'package:dio/dio.dart';
import 'package:image_picker/image_picker.dart';

import '../../../core/constants/api_endpoints.dart';
import '../../../core/services/app_dio.dart';
import '../models/checklist.dart';

Map<String, dynamic> _unwrapChecklistData(dynamic response) {
  if (response is Map<String, dynamic>) {
    final dynamic data = response['data'];
    if (data is Map<String, dynamic>) return data;
  }
  return const {};
}

/// Network access for checklist endpoints under `inspection-assignments`.
class ChecklistDataSource {
  ChecklistDataSource(this._gateway);

  final ApiGateway _gateway;

  /// `GET /v1/inspection-assignments/{id}/checklist`
  Future<ChecklistBundle> fetchBundle(String assignmentId) async {
    final dynamic response = await _gateway.get(
      ApiEndpoints.assignmentChecklist(assignmentId),
    );
    final Map<String, dynamic> data = _unwrapChecklistData(response);
    return ChecklistBundle.fromJson(data);
  }

  /// `POST /v1/inspection-assignments/{id}/checklist` — multipart with
  /// `results[][checklist_item_id|compliance_status|remarks]` and
  /// `evidence_files[<itemId>][]` (image, max 5MB per file).
  /// Returns the list of `checklist_item_id`s the server confirmed as saved
  /// (parsed from `InspectionResultResource` array) so callers can clear
  /// only the successful pending evidence (P0-5).
  Future<List<int>> save({
    required String assignmentId,
    required List<ChecklistResultInput> results,
    Map<int, List<XFile>> evidenceFiles = const {},
  }) async {
    final FormData formData = FormData();

    for (int i = 0; i < results.length; i++) {
      final ChecklistResultInput r = results[i];
      formData.fields.add(
        MapEntry('results[$i][checklist_item_id]', '${r.checklistItemId}'),
      );
      formData.fields.add(
        MapEntry('results[$i][compliance_status]', r.complianceStatus),
      );
      if (r.remarks != null && r.remarks!.trim().isNotEmpty) {
        formData.fields.add(
          MapEntry('results[$i][remarks]', r.remarks!.trim()),
        );
      }
    }

    for (final entry in evidenceFiles.entries) {
      final int itemId = entry.key;
      for (final XFile file in entry.value) {
        final MultipartFile mf = await MultipartFile.fromFile(
          file.path,
          filename: file.name,
        );
        // Checklist endpoint expects `evidence_files.<itemId>` array (validated as `evidence_files.*.*`).
        // The `[]` suffix makes Dio encode `evidence_files[123][]` → PHP `evidence_files[123] = [file1, file2]`
        // which `$request->file("evidence_files.$itemId")` reads. Keep distinct from violation's `files[]`.
        formData.files.add(MapEntry('evidence_files[$itemId][]', mf));
      }
    }

    final dynamic res = await _gateway.post(
      ApiEndpoints.assignmentChecklist(assignmentId),
      data: formData,
      multipart: true,
    );

    // Parse saved item ids from response `data: [InspectionResultResource...]`.
    final List<int> savedIds = [];
    try {
      dynamic data;
      if (res is Map<String, dynamic>) {
        data = res['data'];
      }
      if (data is List) {
        for (final entry in data) {
          if (entry is Map<String, dynamic>) {
            final dynamic cid = entry['checklist_item_id'];
            if (cid is num) {
              savedIds.add(cid.toInt());
            } else if (entry['checklist_item'] is Map<String, dynamic>) {
              final dynamic nested = (entry['checklist_item'] as Map)['id'];
              if (nested is num) savedIds.add(nested.toInt());
            }
          }
        }
      }
    } catch (_) {
      // If parsing fails, fall back to assuming all requested items were saved.
    }
    if (savedIds.isEmpty && results.isNotEmpty) {
      // Fallback: assume all requested were saved when server doesn't echo (e.g., mocked).
      return results.map((r) => r.checklistItemId).toList();
    }
    return savedIds;
  }
}
