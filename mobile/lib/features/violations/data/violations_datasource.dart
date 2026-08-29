import 'package:dio/dio.dart';
import 'package:image_picker/image_picker.dart';

import '../../../core/constants/api_endpoints.dart';
import '../../../core/services/app_dio.dart';
import '../models/violation.dart';

Map<String, dynamic> _unwrapViolationsData(dynamic response) {
  if (response is Map<String, dynamic>) {
    final dynamic data = response['data'];
    if (data is Map<String, dynamic>) return data;
  }
  return const {};
}

class ViolationsDataSource {
  ViolationsDataSource(this._gateway);

  final ApiGateway _gateway;

  /// `GET /v1/violations` — paginated, filtered by `status, severity, search, per_page`
  Future<ViolationsPage> fetch({
    String? status,
    String? severity,
    String? search,
    int perPage = 20,
    int page = 1,
  }) async {
    final dynamic res = await _gateway.get(
      ApiEndpoints.violations,
      query: {
        'per_page': perPage,
        'page': page,
        if (status != null && status != 'all') 'status': status,
        if (severity != null && severity != 'all') 'severity': severity,
        if (search != null && search.trim().isNotEmpty) 'search': search.trim(),
      },
    );
    final Map<String, dynamic> data = _unwrapViolationsData(res);
    final List<dynamic> rawList = data['violations'] is List
        ? data['violations'] as List
        : const [];
    final List<Violation> violations = rawList
        .whereType<Map<String, dynamic>>()
        .map(Violation.fromJson)
        .toList();

    final dynamic meta = data['meta'];
    final int total = meta is Map<String, dynamic>
        ? (meta['total'] as num?)?.toInt() ?? violations.length
        : violations.length;
    final int currentPage = meta is Map<String, dynamic>
        ? (meta['current_page'] as num?)?.toInt() ?? page
        : page;
    final int lastPage = meta is Map<String, dynamic>
        ? (meta['last_page'] as num?)?.toInt() ?? 1
        : 1;

    return ViolationsPage(
      violations: violations,
      currentPage: currentPage,
      lastPage: lastPage,
      total: total,
    );
  }

  /// `GET /v1/violations/options` — inspections + results for form picker.
  Future<ViolationOptions> fetchOptions() async {
    final dynamic res = await _gateway.get(ApiEndpoints.violationsOptions);
    final Map<String, dynamic> data = _unwrapViolationsData(res);
    return ViolationOptions.fromJson(data);
  }

  /// `GET /v1/violations/{id}`
  Future<Violation> fetchOne(String id) async {
    final dynamic res = await _gateway.get('${ApiEndpoints.violations}/$id');
    final Map<String, dynamic> data = _unwrapViolationsData(res);
    // Api may return `data: ViolationResource` directly or under `violation`
    final Map<String, dynamic> resource = data.containsKey('id')
        ? data
        : (data['violation'] is Map<String, dynamic>
              ? data['violation'] as Map<String, dynamic>
              : data);
    return Violation.fromJson(resource);
  }

  /// `POST /v1/violations` — creates a violation.
  /// Returns created `Violation`.
  Future<Violation> create({
    required int inspectionId,
    int? inspectionResultId,
    int? assignedTo,
    required String title,
    required String description,
    required String severity, // minor|moderate|major
    required String status, // open|under_review|resolved
    String? correctionDeadline, // YYYY-MM-DD
  }) async {
    final Map<String, dynamic> body = {
      'inspection_id': inspectionId,
      'inspection_result_id': ?inspectionResultId,
      'assigned_to': ?assignedTo,
      'title': title,
      'description': description,
      'severity': severity,
      'status': status,
      if (correctionDeadline != null && correctionDeadline.isNotEmpty)
        'correction_deadline': correctionDeadline,
    };

    final dynamic res = await _gateway.post(
      ApiEndpoints.violations,
      data: body,
    );
    final Map<String, dynamic> data = _unwrapViolationsData(res);
    final Map<String, dynamic> resource = data.containsKey('id')
        ? data
        : (data['violation'] is Map<String, dynamic>
              ? data['violation'] as Map<String, dynamic>
              : data);
    return Violation.fromJson(resource);
  }

  /// `POST /v1/violations/{id}/evidence` — multipart `files[]`, `evidence_type`, `description?`
  Future<List<ViolationEvidence>> uploadEvidence({
    required String violationId,
    required String evidenceType, // initial | corrective
    String? description,
    required List<XFile> files,
  }) async {
    final FormData formData = FormData();
    formData.fields.add(MapEntry('evidence_type', evidenceType));
    if (description != null && description.trim().isNotEmpty) {
      formData.fields.add(MapEntry('description', description.trim()));
    }
    for (final XFile f in files) {
      final MultipartFile mf = await MultipartFile.fromFile(
        f.path,
        filename: f.name,
      );
      formData.files.add(MapEntry('files[]', mf));
    }

    final dynamic res = await _gateway.post(
      '${ApiEndpoints.violations}/$violationId/evidence',
      data: formData,
      multipart: true,
    );
    // Api returns `data: ViolationEvidenceResource[]` collection.
    List<dynamic> list;
    if (res is Map<String, dynamic>) {
      final dynamic data = res['data'];
      if (data is List) {
        list = data;
      } else if (data is Map<String, dynamic> && data['evidence'] is List) {
        list = data['evidence'] as List;
      } else {
        list = const [];
      }
    } else {
      list = const [];
    }
    return list
        .whereType<Map<String, dynamic>>()
        .map(ViolationEvidence.fromJson)
        .toList();
  }
}
