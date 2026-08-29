/// Report domain models for `GET/PUT /v1/inspection-assignments/{id}/report`.
///
/// Mirrors `InspectionReportResource` (`backend/app/Http/Resources/InspectionReportResource.php:10`).
library;

class InspectionReport {
  const InspectionReport({
    required this.id,
    this.inspectionScheduleId,
    this.inspectionDate,
    required this.status,
    this.overallAssessment,
    this.recommendations,
    this.establishmentName,
    this.inspectorName,
    this.summary,
    this.results = const [],
    this.violations = const [],
    this.generatedAt,
  });

  factory InspectionReport.fromJson(Map<String, dynamic> json) {
    String? estName;
    if (json['establishment'] is Map<String, dynamic>) {
      estName =
          (json['establishment'] as Map<String, dynamic>)['name'] as String?;
    }
    String? inspName;
    if (json['inspector'] is Map<String, dynamic>) {
      inspName = (json['inspector'] as Map<String, dynamic>)['name'] as String?;
    }
    return InspectionReport(
      id: (json['id'] as num).toInt(),
      inspectionScheduleId: (json['inspection_schedule_id'] as num?)?.toInt(),
      inspectionDate: json['inspection_date'] as String?,
      status: json['status'] as String? ?? '',
      overallAssessment: json['overall_assessment'] as String?,
      recommendations: json['recommendations'] as String?,
      establishmentName: estName,
      inspectorName: inspName,
      summary: json['summary'] is Map<String, dynamic>
          ? Map<String, dynamic>.from(json['summary'] as Map<String, dynamic>)
          : null,
      results: json['results'] is List
          ? (json['results'] as List)
                .whereType<Map<String, dynamic>>()
                .map(ReportResult.fromJson)
                .toList()
          : const [],
      violations: json['violations'] is List
          ? (json['violations'] as List)
                .whereType<Map<String, dynamic>>()
                .map(ReportViolation.fromJson)
                .toList()
          : const [],
      generatedAt: json['generated_at'] as String?,
    );
  }

  final int id;
  final int? inspectionScheduleId;
  final String? inspectionDate;
  final String status;
  final String? overallAssessment;
  final String? recommendations;
  final String? establishmentName;
  final String? inspectorName;
  final Map<String, dynamic>? summary;
  final List<ReportResult> results;
  final List<ReportViolation> violations;
  final String? generatedAt;
}

class ReportResult {
  const ReportResult({
    required this.id,
    required this.checklistItemId,
    this.checklistName,
    this.checklistCategory,
    this.itemCategory,
    this.itemTitle,
    required this.complianceStatus,
    this.remarks,
    this.evidencePaths = const [],
    this.assessorName,
  });

  factory ReportResult.fromJson(Map<String, dynamic> json) => ReportResult(
    id: (json['id'] as num).toInt(),
    checklistItemId: (json['checklist_item_id'] as num?)?.toInt() ?? 0,
    checklistName: json['checklist_name'] as String?,
    checklistCategory: json['checklist_category'] as String?,
    itemCategory: json['item_category'] as String?,
    itemTitle: json['item_title'] as String?,
    complianceStatus: json['compliance_status'] as String? ?? '',
    remarks: json['remarks'] as String?,
    evidencePaths: json['evidence_paths'] is List
        ? (json['evidence_paths'] as List).map((e) => e.toString()).toList()
        : const [],
    assessorName: json['assessor'] is Map<String, dynamic>
        ? (json['assessor'] as Map<String, dynamic>)['name'] as String?
        : null,
  );

  final int id;
  final int checklistItemId;
  final String? checklistName;
  final String? checklistCategory;
  final String? itemCategory;
  final String? itemTitle;
  final String complianceStatus;
  final String? remarks;
  final List<String> evidencePaths;
  final String? assessorName;
}

class ReportViolation {
  const ReportViolation({
    required this.id,
    required this.title,
    this.description,
    required this.severity,
    required this.status,
    this.correctionDeadline,
  });

  factory ReportViolation.fromJson(Map<String, dynamic> json) =>
      ReportViolation(
        id: (json['id'] as num).toInt(),
        title: json['title'] as String? ?? '',
        description: json['description'] as String?,
        severity: json['severity'] as String? ?? '',
        status: json['status'] as String? ?? '',
        correctionDeadline: json['correction_deadline'] as String?,
      );

  final int id;
  final String title;
  final String? description;
  final String severity;
  final String status;
  final String? correctionDeadline;
}
