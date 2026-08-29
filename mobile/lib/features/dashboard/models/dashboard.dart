/// Inspector dashboard models for `GET /v1/dashboard` (inspector branch).
///
/// Mirrors `DashboardController::inspectorData` (`backend/app/Http/Controllers/Api/DashboardController.php:308`).
library;

class InspectorDashboard {
  const InspectorDashboard({
    required this.stats,
    required this.assignedInspections,
    required this.inspectionHistory,
    required this.followUps,
    required this.sync,
  });

  factory InspectorDashboard.fromJson(Map<String, dynamic> json) {
    final Map<String, dynamic> stats = json['stats'] is Map<String, dynamic>
        ? json['stats'] as Map<String, dynamic>
        : const {};
    final List<dynamic> rawAssigned = json['assigned_inspections'] is List
        ? json['assigned_inspections'] as List
        : const [];
    final List<dynamic> rawHistory = json['inspection_history'] is List
        ? json['inspection_history'] as List
        : const [];
    final List<dynamic> rawFollowUps = json['follow_ups'] is List
        ? json['follow_ups'] as List
        : const [];
    final Map<String, dynamic> sync = json['sync'] is Map<String, dynamic>
        ? json['sync'] as Map<String, dynamic>
        : const {};

    return InspectorDashboard(
      stats: DashboardStats.fromJson(stats),
      assignedInspections: rawAssigned
          .whereType<Map<String, dynamic>>()
          .map(DashboardAssignmentCard.fromJson)
          .toList(),
      inspectionHistory: rawHistory
          .whereType<Map<String, dynamic>>()
          .map(InspectionHistoryItem.fromJson)
          .toList(),
      followUps: rawFollowUps
          .whereType<Map<String, dynamic>>()
          .map(FollowUpItem.fromJson)
          .toList(),
      sync: DashboardSync.fromJson(sync),
    );
  }

  final DashboardStats stats;
  final List<DashboardAssignmentCard> assignedInspections;
  final List<InspectionHistoryItem> inspectionHistory;
  final List<FollowUpItem> followUps;
  final DashboardSync sync;
}

class DashboardStats {
  const DashboardStats({
    required this.assigned,
    required this.inProgress,
    required this.completed,
    required this.followUps,
    required this.pendingSync,
  });

  factory DashboardStats.fromJson(Map<String, dynamic> json) => DashboardStats(
    assigned: (json['assigned'] as num?)?.toInt() ?? 0,
    inProgress: (json['in_progress'] as num?)?.toInt() ?? 0,
    completed: (json['completed'] as num?)?.toInt() ?? 0,
    followUps: (json['follow_ups'] as num?)?.toInt() ?? 0,
    pendingSync: (json['pending_sync'] as num?)?.toInt() ?? 0,
  );

  final int assigned;
  final int inProgress;
  final int completed;
  final int followUps;
  final int pendingSync;
}

class DashboardSync {
  const DashboardSync({
    this.lastSyncedAt,
    required this.pendingCount,
    required this.unsyncedAssignments,
  });

  factory DashboardSync.fromJson(Map<String, dynamic> json) => DashboardSync(
    lastSyncedAt: json['last_synced_at'] as String?,
    pendingCount: (json['pending_count'] as num?)?.toInt() ?? 0,
    unsyncedAssignments: (json['unsynced_assignments'] as num?)?.toInt() ?? 0,
  );

  final String? lastSyncedAt;
  final int pendingCount;
  final int unsyncedAssignments;
}

class DashboardAssignmentCard {
  const DashboardAssignmentCard({
    required this.id,
    required this.status,
    this.assignedAt,
    this.assignedDate,
    this.requestNumber,
    this.applicantName,
    this.applicantAddress,
    this.contactNumber,
    this.businessName,
    this.category,
    this.applicationType,
    this.establishmentName,
    this.establishmentAddress,
    this.barangay,
    this.businessType,
    this.notes,
    this.scheduledAt,
  });

  factory DashboardAssignmentCard.fromJson(Map<String, dynamic> json) =>
      DashboardAssignmentCard(
        id: (json['id'] as num).toInt(),
        status: json['status'] as String? ?? '',
        assignedAt: json['assigned_at'] as String?,
        assignedDate: json['assigned_date'] as String?,
        requestNumber: json['request_number'] as String?,
        applicantName: json['applicant_name'] as String?,
        applicantAddress: json['applicant_address'] as String?,
        contactNumber: json['contact_number'] as String?,
        businessName: json['business_name'] as String?,
        category: json['category'] as String?,
        applicationType: json['application_type'] as String?,
        establishmentName: json['establishment_name'] as String?,
        establishmentAddress: json['establishment_address'] as String?,
        barangay: json['barangay'] as String?,
        businessType: json['business_type'] as String?,
        notes: json['notes'] as String?,
        scheduledAt: json['scheduled_at'] as String?,
      );

  final int id;
  final String status;
  final String? assignedAt;
  final String? assignedDate;
  final String? requestNumber;
  final String? applicantName;
  final String? applicantAddress;
  final String? contactNumber;
  final String? businessName;
  final String? category;
  final String? applicationType;
  final String? establishmentName;
  final String? establishmentAddress;
  final String? barangay;
  final String? businessType;
  final String? notes;
  final String? scheduledAt;

  String get displayName {
    final String biz = businessName?.trim() ?? '';
    if (biz.isNotEmpty) return biz;
    final String appl = applicantName?.trim() ?? '';
    if (appl.isNotEmpty) return appl;
    return requestNumber ?? 'Inspection #$id';
  }
}

class InspectionHistoryItem {
  const InspectionHistoryItem({
    required this.id,
    required this.establishmentName,
    this.businessType,
    required this.date,
    required this.status,
    this.overallAssessment,
  });

  factory InspectionHistoryItem.fromJson(Map<String, dynamic> json) =>
      InspectionHistoryItem(
        id: (json['id'] as num).toInt(),
        establishmentName: json['establishment_name'] as String? ?? 'Unknown',
        businessType: json['business_type'] as String?,
        date: json['date'] as String? ?? 'N/A',
        status: json['status'] as String? ?? '',
        overallAssessment: json['overall_assessment'] as String?,
      );

  final int id;
  final String establishmentName;
  final String? businessType;
  final String date;
  final String status;
  final String? overallAssessment;
}

class FollowUpItem {
  const FollowUpItem({
    required this.id,
    required this.requestNumber,
    required this.businessName,
    required this.category,
    required this.status,
    this.reason,
    this.assignmentId,
    required this.updatedAt,
  });

  factory FollowUpItem.fromJson(Map<String, dynamic> json) => FollowUpItem(
    id: (json['id'] as num).toInt(),
    requestNumber: json['request_number'] as String? ?? '',
    businessName: json['business_name'] as String? ?? '',
    category: json['category'] as String? ?? 'Inspection',
    status: json['status'] as String? ?? '',
    reason: json['reason'] as String?,
    assignmentId: (json['assignment_id'] as num?)?.toInt(),
    updatedAt: json['updated_at'] as String? ?? 'N/A',
  );

  final int id;
  final String requestNumber;
  final String businessName;
  final String category;
  final String status;
  final String? reason;
  final int? assignmentId;
  final String updatedAt;
}
