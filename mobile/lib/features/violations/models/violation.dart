/// Violation domain models for `GET/POST /v1/violations` and
/// `POST /v1/violations/{id}/evidence`.
///
/// Mirrors `ViolationResource` and `ViolationEvidenceResource`
/// (`backend/app/Http/Resources/`).
library;

class Violation {
  const Violation({
    required this.id,
    required this.inspectionId,
    this.establishmentId,
    this.inspectionResultId,
    required this.title,
    required this.description,
    required this.severity, // minor | moderate | major
    required this.status, // open | under_review | resolved
    this.correctionDeadline,
    this.resolvedAt,
    this.evidenceCount = 0,
    this.establishmentName,
    this.createdAt,
    this.updatedAt,
    this.evidence = const [],
  });

  factory Violation.fromJson(Map<String, dynamic> json) {
    String? establishmentName;
    if (json['establishment'] is Map<String, dynamic>) {
      establishmentName =
          (json['establishment'] as Map<String, dynamic>)['name'] as String?;
    }
    // Fallback to inspection.establishment.name when establishment not loaded.
    if (establishmentName == null &&
        json['inspection'] is Map<String, dynamic>) {
      final Map<String, dynamic> insp =
          json['inspection'] as Map<String, dynamic>;
      if (insp['establishment'] is Map<String, dynamic>) {
        establishmentName =
            (insp['establishment'] as Map<String, dynamic>)['name'] as String?;
      }
    }

    return Violation(
      id: (json['id'] as num).toInt(),
      inspectionId: (json['inspection_id'] as num?)?.toInt() ?? 0,
      establishmentId: (json['establishment_id'] as num?)?.toInt(),
      inspectionResultId: (json['inspection_result_id'] as num?)?.toInt(),
      title: json['title'] as String? ?? '',
      description: json['description'] as String? ?? '',
      severity: json['severity'] as String? ?? 'minor',
      status: json['status'] as String? ?? 'open',
      correctionDeadline: json['correction_deadline'] as String?,
      resolvedAt: json['resolved_at'] as String?,
      evidenceCount:
          (json['evidence_count'] as num?)?.toInt() ??
          (json['evidence'] is List ? (json['evidence'] as List).length : 0),
      establishmentName: establishmentName,
      createdAt: json['created_at'] as String?,
      updatedAt: json['updated_at'] as String?,
      evidence: json['evidence'] is List
          ? (json['evidence'] as List)
                .whereType<Map<String, dynamic>>()
                .map(ViolationEvidence.fromJson)
                .toList()
          : const [],
    );
  }

  final int id;
  final int inspectionId;
  final int? establishmentId;
  final int? inspectionResultId;
  final String title;
  final String description;
  final String severity;
  final String status;
  final String? correctionDeadline;
  final String? resolvedAt;
  final int evidenceCount;
  final String? establishmentName;
  final String? createdAt;
  final String? updatedAt;
  final List<ViolationEvidence> evidence;
}

class ViolationEvidence {
  const ViolationEvidence({
    required this.id,
    required this.violationId,
    required this.filePath,
    this.fileUrl,
    this.fileName,
    this.mimeType,
    this.evidenceType,
    this.description,
    this.createdAt,
  });

  factory ViolationEvidence.fromJson(Map<String, dynamic> json) =>
      ViolationEvidence(
        id: (json['id'] as num).toInt(),
        violationId: (json['violation_id'] as num?)?.toInt() ?? 0,
        filePath: json['file_path'] as String? ?? '',
        fileUrl: json['file_url'] as String?,
        fileName: json['file_name'] as String?,
        mimeType: json['mime_type'] as String?,
        evidenceType: json['evidence_type'] as String?,
        description: json['description'] as String?,
        createdAt: json['created_at'] as String?,
      );

  final int id;
  final int violationId;
  final String filePath;
  final String? fileUrl;
  final String? fileName;
  final String? mimeType;
  final String? evidenceType;
  final String? description;
  final String? createdAt;
}

/// Paginated violations response: `{violations: [], meta: {}}`
class ViolationsPage {
  const ViolationsPage({
    required this.violations,
    required this.currentPage,
    required this.lastPage,
    required this.total,
  });

  final List<Violation> violations;
  final int currentPage;
  final int lastPage;
  final int total;
}

/// Options for violation form: inspections with results + assignees.
/// `GET /v1/violations/options` returns `{inspections: [{id, inspection_date, status, establishment, results[]}], assignees: UserResource[]}`
class ViolationOptions {
  const ViolationOptions({required this.inspections, required this.assignees});

  factory ViolationOptions.fromJson(Map<String, dynamic> json) =>
      ViolationOptions(
        inspections:
            (json['inspections'] is List
                    ? json['inspections'] as List
                    : const [])
                .whereType<Map<String, dynamic>>()
                .map(ViolationInspectionOption.fromJson)
                .toList(),
        assignees: const [], // users parsed elsewhere if needed
      );

  final List<ViolationInspectionOption> inspections;
  final List<dynamic> assignees;
}

class ViolationInspectionOption {
  const ViolationInspectionOption({
    required this.id,
    this.inspectionDate,
    required this.status,
    this.establishmentName,
    this.establishmentRegistration,
    required this.results,
  });

  factory ViolationInspectionOption.fromJson(Map<String, dynamic> json) {
    final Map<String, dynamic>? est =
        json['establishment'] is Map<String, dynamic>
        ? json['establishment'] as Map<String, dynamic>
        : null;
    return ViolationInspectionOption(
      id: (json['id'] as num).toInt(),
      inspectionDate: json['inspection_date'] as String?,
      status: json['status'] as String? ?? '',
      establishmentName: est?['name'] as String?,
      establishmentRegistration: est?['registration_number'] as String?,
      results: (json['results'] is List ? json['results'] as List : const [])
          .whereType<Map<String, dynamic>>()
          .map((e) => ViolationResultOption.fromJson(e))
          .toList(),
    );
  }

  final int id;
  final String? inspectionDate;
  final String status;
  final String? establishmentName;
  final String? establishmentRegistration;
  final List<ViolationResultOption> results;
}

class ViolationResultOption {
  const ViolationResultOption({
    required this.id,
    required this.checklistItemId,
    this.itemTitle,
    this.itemCategory,
    this.complianceStatus,
  });

  factory ViolationResultOption.fromJson(Map<String, dynamic> json) =>
      ViolationResultOption(
        id: (json['id'] as num).toInt(),
        checklistItemId: (json['checklist_item_id'] as num?)?.toInt() ?? 0,
        itemTitle: json['item_title'] as String?,
        itemCategory: json['item_category'] as String?,
        complianceStatus: json['compliance_status'] as String?,
      );

  final int id;
  final int checklistItemId;
  final String? itemTitle;
  final String? itemCategory;
  final String? complianceStatus;
}
