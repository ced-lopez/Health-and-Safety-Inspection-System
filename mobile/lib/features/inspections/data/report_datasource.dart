import '../../../core/constants/api_endpoints.dart';
import '../../../core/services/app_dio.dart';
import '../models/report.dart';

Map<String, dynamic> _unwrapReportData(dynamic response) {
  if (response is Map<String, dynamic>) {
    final dynamic data = response['data'];
    if (data is Map<String, dynamic>) return data;
  }
  return const {};
}

class ReportDataSource {
  ReportDataSource(this._gateway);

  final ApiGateway _gateway;

  /// `GET /v1/inspection-assignments/{id}/report`
  Future<InspectionReport> fetch(String assignmentId) async {
    final dynamic res = await _gateway.get(
      ApiEndpoints.assignmentReport(assignmentId),
    );
    final Map<String, dynamic> data = _unwrapReportData(res);
    // `data` is already the InspectionReportResource payload.
    return InspectionReport.fromJson(data);
  }

  /// `PUT /v1/inspection-assignments/{id}/report`
  /// All fields nullable; `notes` is saved to the assignment, not the inspection.
  Future<InspectionReport> update({
    required String assignmentId,
    String? overallAssessment,
    String? recommendations,
    String? notes,
  }) async {
    final Map<String, dynamic> body = {
      'overall_assessment': ?overallAssessment,
      'recommendations': ?recommendations,
      'notes': ?notes,
    };
    // Ensure at least an empty body is sent (backend validates nullable).
    final dynamic res = await _gateway.put(
      ApiEndpoints.assignmentUpdateReport(assignmentId),
      data: body,
    );
    final Map<String, dynamic> data = _unwrapReportData(res);
    return InspectionReport.fromJson(data);
  }
}
