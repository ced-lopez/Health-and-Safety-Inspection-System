/// Checklist domain models for `GET /v1/inspection-assignments/{id}/checklist`
/// and `POST /v1/inspection-assignments/{id}/checklist`.
///
/// Mirrors `ChecklistResource`, `ChecklistItemResource`, `InspectionResultResource`
/// (`backend/app/Http/Resources/`).
library;

class Checklist {
  const Checklist({
    required this.id,
    required this.name,
    this.description,
    required this.category,
    this.version,
    this.isActive = true,
    required this.items,
  });

  factory Checklist.fromJson(Map<String, dynamic> json) {
    final List<dynamic> rawItems = json['items'] is List
        ? json['items'] as List
        : const [];
    return Checklist(
      id: (json['id'] as num).toInt(),
      name: json['name'] as String? ?? '',
      description: json['description'] as String?,
      category: json['category'] as String? ?? '',
      version: (json['version'] as num?)?.toInt(),
      isActive: json['is_active'] as bool? ?? true,
      items: rawItems
          .whereType<Map<String, dynamic>>()
          .map(ChecklistItem.fromJson)
          .toList(),
    );
  }

  final int id;
  final String name;
  final String? description;
  final String category;
  final int? version;
  final bool isActive;
  final List<ChecklistItem> items;
}

class ChecklistItem {
  const ChecklistItem({
    required this.id,
    required this.checklistId,
    this.category,
    required this.title,
    this.description,
    required this.sortOrder,
    required this.isRequired,
  });

  factory ChecklistItem.fromJson(Map<String, dynamic> json) => ChecklistItem(
    id: (json['id'] as num).toInt(),
    checklistId: (json['checklist_id'] as num?)?.toInt() ?? 0,
    category: json['category'] as String?,
    title: json['title'] as String? ?? '',
    description: json['description'] as String?,
    sortOrder: (json['sort_order'] as num?)?.toInt() ?? 0,
    isRequired: json['is_required'] as bool? ?? false,
  );

  final int id;
  final int checklistId;
  final String? category;
  final String title;
  final String? description;
  final int sortOrder;
  final bool isRequired;
}

class InspectionResult {
  const InspectionResult({
    required this.id,
    required this.inspectionId,
    required this.checklistItemId,
    required this.complianceStatus,
    this.remarks,
    this.evidencePaths = const [],
    this.assessedBy,
    this.checklistItem,
    this.updatedAt,
  });

  factory InspectionResult.fromJson(
    Map<String, dynamic> json,
  ) => InspectionResult(
    id: (json['id'] as num).toInt(),
    inspectionId: (json['inspection_id'] as num?)?.toInt() ?? 0,
    checklistItemId: (json['checklist_item_id'] as num?)?.toInt() ?? 0,
    complianceStatus: json['compliance_status'] as String? ?? '',
    remarks: json['remarks'] as String?,
    evidencePaths: (json['evidence_paths'] is List)
        ? (json['evidence_paths'] as List).map((e) => e.toString()).toList()
        : const [],
    assessedBy: (json['assessed_by'] as num?)?.toInt(),
    checklistItem: json['checklist_item'] is Map<String, dynamic>
        ? ChecklistItem.fromJson(json['checklist_item'] as Map<String, dynamic>)
        : json['checklistItem'] is Map<String, dynamic>
        ? ChecklistItem.fromJson(json['checklistItem'] as Map<String, dynamic>)
        : null,
    updatedAt: json['updated_at'] is String
        ? json['updated_at'] as String
        : null,
  );

  final int id;
  final int inspectionId;
  final int checklistItemId;
  final String complianceStatus; // compliant | non_compliant | needs_correction
  final String? remarks;
  final List<String> evidencePaths;
  final int? assessedBy;
  final ChecklistItem? checklistItem;
  final String? updatedAt;
}

class ChecklistBundle {
  const ChecklistBundle({
    required this.inspection,
    required this.checklists,
    required this.results,
  });

  factory ChecklistBundle.fromJson(Map<String, dynamic> json) {
    final Map<String, dynamic> inspection =
        json['inspection'] is Map<String, dynamic>
        ? json['inspection'] as Map<String, dynamic>
        : const {};
    final List<dynamic> rawChecklists = json['checklists'] is List
        ? json['checklists'] as List
        : const [];
    final List<dynamic> rawResults = json['results'] is List
        ? json['results'] as List
        : const [];

    return ChecklistBundle(
      inspection: inspection,
      checklists: rawChecklists
          .whereType<Map<String, dynamic>>()
          .map(Checklist.fromJson)
          .toList(),
      results: rawResults
          .whereType<Map<String, dynamic>>()
          .map(InspectionResult.fromJson)
          .toList(),
    );
  }

  final Map<String, dynamic> inspection;
  final List<Checklist> checklists;
  final List<InspectionResult> results;

  /// Flattened items across all checklists, sorted by category then sort_order.
  List<ChecklistItem> get allItems =>
      checklists.expand((c) => c.items).toList();

  /// Quick lookup for existing results by checklist_item_id.
  Map<int, InspectionResult> get resultsByItemId => {
    for (final r in results) r.checklistItemId: r,
  };
}

/// Input for saving a single item result.
class ChecklistResultInput {
  const ChecklistResultInput({
    required this.checklistItemId,
    required this.complianceStatus,
    this.remarks,
  });

  final int checklistItemId;
  final String complianceStatus; // compliant | non_compliant | needs_correction
  final String? remarks;
}
