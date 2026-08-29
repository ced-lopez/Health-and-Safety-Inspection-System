import '../../../../core/constants/api_endpoints.dart';
import '../../../../core/services/app_dio.dart';
import '../models/assignment.dart';

/// Unwraps the standard `{success, data}` envelope and returns `data` as a
/// map, or an empty map when the shape is unexpected.
Map<String, dynamic> _unwrapData(dynamic response) {
  if (response is Map<String, dynamic>) {
    final dynamic data = response['data'];
    if (data is Map<String, dynamic>) return data;
    // Some endpoints return the resource directly under `data` as a map.
    if (data != null && data is Map) return Map<String, dynamic>.from(data);
  }
  return const {};
}

/// A page of assignments plus the raw JSON so the offline cache can store the
/// exact payload returned by the API.
class AssignmentsPage {
  const AssignmentsPage({
    required this.assignments,
    required this.raw,
    required this.total,
  });

  final List<InspectionAssignment> assignments;

  /// Raw assignment maps as returned by the server (used for caching).
  final List<Map<String, dynamic>> raw;
  final int total;
}

/// Network access for `GET /v1/inspection-assignments`.
class AssignmentsDataSource {
  AssignmentsDataSource(this._gateway);

  final ApiGateway _gateway;

  Future<AssignmentsPage> fetch({String? status}) async {
    final dynamic response = await _gateway.get(
      ApiEndpoints.assignments,
      query: {'per_page': 50, 'status': ?status},
    );

    final Map<String, dynamic> data = _unwrapData(response);

    final List<dynamic> rawList = data['assignments'] is List
        ? data['assignments'] as List
        : const [];
    final List<Map<String, dynamic>> raw = rawList
        .whereType<Map<String, dynamic>>()
        .toList();

    final dynamic meta = data['meta'];
    final int total = meta is Map<String, dynamic>
        ? (meta['total'] as num?)?.toInt() ?? raw.length
        : raw.length;

    return AssignmentsPage(
      assignments: raw.map(InspectionAssignment.fromJson).toList(),
      raw: raw,
      total: total,
    );
  }

  /// Fetches a single assignment by id: `GET /v1/inspection-assignments/{id}`.
  Future<InspectionAssignment> fetchOne(String id) async {
    final dynamic response = await _gateway.get(ApiEndpoints.assignment(id));
    final Map<String, dynamic> data = _unwrapData(response);
    // Api returns `{success, data: InspectionAssignmentResource}` — unwrap one level.
    // When `data` itself is the resource (has `id` + `status`), use it directly.
    final Map<String, dynamic> resource = data.containsKey('id')
        ? data
        : (data['assignment'] is Map<String, dynamic>
              ? data['assignment'] as Map<String, dynamic>
              : data);
    return InspectionAssignment.fromJson(resource);
  }

  /// Drives the inspection lifecycle: `PUT /v1/inspection-assignments/{id}/start`.
  Future<InspectionAssignment> start(String id) async {
    final dynamic response = await _gateway.put(
      ApiEndpoints.assignmentStart(id),
    );
    final Map<String, dynamic> data = _unwrapData(response);
    final Map<String, dynamic> resource = data.containsKey('id')
        ? data
        : (data['assignment'] is Map<String, dynamic>
              ? data['assignment'] as Map<String, dynamic>
              : data);
    return InspectionAssignment.fromJson(resource);
  }

  /// Submits the inspection: `PUT /v1/inspection-assignments/{id}/submit`.
  Future<InspectionAssignment> submit(String id, {String? notes}) async {
    final dynamic response = await _gateway.put(
      ApiEndpoints.assignmentSubmit(id),
      data: {
        if (notes != null && notes.trim().isNotEmpty) 'notes': notes.trim(),
      },
    );
    final Map<String, dynamic> data = _unwrapData(response);
    final Map<String, dynamic> resource = data.containsKey('id')
        ? data
        : (data['assignment'] is Map<String, dynamic>
              ? data['assignment'] as Map<String, dynamic>
              : data);
    return InspectionAssignment.fromJson(resource);
  }
}
