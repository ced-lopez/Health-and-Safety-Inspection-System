/// Domain models for inspection assignments served by `GET /v1/inspection-assignments`.
library;

import '../../../core/utils/formatters.dart';

DateTime? _date(dynamic value) {
  if (value is! String || value.isEmpty) return null;
  return DateTime.tryParse(value);
}

/// Inspection taxonomy category (e.g. piggery, poultry, business establishment).
class InspectionCategory {
  const InspectionCategory({
    required this.id,
    required this.name,
    required this.slug,
    this.description,
  });

  factory InspectionCategory.fromJson(Map<String, dynamic> json) =>
      InspectionCategory(
        id: (json['id'] as num).toInt(),
        name: json['name'] as String? ?? '',
        slug: json['slug'] as String? ?? '',
        description: json['description'] as String?,
      );

  final int id;
  final String name;
  final String slug;
  final String? description;
}

/// Application type taxonomy (e.g. new application, renewal).
class ApplicationType {
  const ApplicationType({
    required this.id,
    required this.name,
    required this.slug,
    this.description,
  });

  factory ApplicationType.fromJson(Map<String, dynamic> json) =>
      ApplicationType(
        id: (json['id'] as num).toInt(),
        name: json['name'] as String? ?? '',
        slug: json['slug'] as String? ?? '',
        description: json['description'] as String?,
      );

  final int id;
  final String name;
  final String slug;
  final String? description;
}

/// Lightweight establishment reference embedded in an inspection request.
class EstablishmentSummary {
  const EstablishmentSummary({
    required this.id,
    required this.name,
    this.businessType,
    this.address,
    this.barangay,
  });

  factory EstablishmentSummary.fromJson(Map<String, dynamic> json) =>
      EstablishmentSummary(
        id: (json['id'] as num).toInt(),
        name: json['name'] as String? ?? '',
        businessType: json['business_type'] as String?,
        address: json['address'] as String?,
        barangay: json['barangay'] as String?,
      );

  final int id;
  final String name;
  final String? businessType;
  final String? address;
  final String? barangay;
}

/// Resident/applicant reference embedded in an inspection request.
class ResidentSummary {
  const ResidentSummary({required this.id, required this.name});

  factory ResidentSummary.fromJson(Map<String, dynamic> json) =>
      ResidentSummary(
        id: (json['id'] as num).toInt(),
        name: json['name'] as String? ?? '',
      );

  final int id;
  final String name;
}

/// Nested `inspection_request` payload (InspectionRequestResource subset).
class InspectionRequestSummary {
  const InspectionRequestSummary({
    required this.id,
    required this.requestNumber,
    this.applicantName,
    this.applicantAddress,
    this.contactNumber,
    this.businessName,
    this.status,
    this.inspectionCategory,
    this.applicationType,
    this.establishment,
    this.resident,
  });

  factory InspectionRequestSummary.fromJson(Map<String, dynamic> json) =>
      InspectionRequestSummary(
        id: (json['id'] as num).toInt(),
        requestNumber: json['request_number'] as String? ?? '',
        applicantName: json['applicant_name'] as String?,
        applicantAddress: json['applicant_address'] as String?,
        contactNumber: json['contact_number'] as String?,
        businessName: json['business_name'] as String?,
        status: json['status'] as String?,
        inspectionCategory: json['inspection_category'] is Map<String, dynamic>
            ? InspectionCategory.fromJson(
                json['inspection_category'] as Map<String, dynamic>,
              )
            : null,
        applicationType: json['application_type'] is Map<String, dynamic>
            ? ApplicationType.fromJson(
                json['application_type'] as Map<String, dynamic>,
              )
            : null,
        establishment: json['establishment'] is Map<String, dynamic>
            ? EstablishmentSummary.fromJson(
                json['establishment'] as Map<String, dynamic>,
              )
            : null,
        resident: json['resident'] is Map<String, dynamic>
            ? ResidentSummary.fromJson(json['resident'] as Map<String, dynamic>)
            : null,
      );

  final int id;
  final String requestNumber;
  final String? applicantName;
  final String? applicantAddress;
  final String? contactNumber;
  final String? businessName;
  final String? status;
  final InspectionCategory? inspectionCategory;
  final ApplicationType? applicationType;
  final EstablishmentSummary? establishment;
  final ResidentSummary? resident;

  /// Display name for the card: the business name when present, otherwise the
  /// applicant's name.
  String get displayName {
    final String business = businessName?.trim() ?? '';
    if (business.isNotEmpty) return business;
    final String applicant = applicantName?.trim() ?? '';
    return applicant.isEmpty ? requestNumber : applicant;
  }

  String get addressLabel {
    final String? address = establishment?.address ?? applicantAddress;
    return address?.trim().isNotEmpty == true
        ? address!.trim()
        : 'Address not on file';
  }
}

/// A single assigned inspection (InspectionAssignmentResource).
class InspectionAssignment {
  const InspectionAssignment({
    required this.id,
    required this.status,
    this.assignedAt,
    this.downloadedAt,
    this.submittedAt,
    this.scheduledAt,
    this.serverVersion = 0,
    this.notes,
    this.inspectionRequest,
  });

  factory InspectionAssignment.fromJson(Map<String, dynamic> json) =>
      InspectionAssignment(
        id: (json['id'] as num).toInt(),
        status: json['status'] as String? ?? 'assigned',
        assignedAt: _date(json['assigned_at']),
        downloadedAt: _date(json['downloaded_at']),
        submittedAt: _date(json['submitted_at']),
        scheduledAt: _date(json['scheduled_at']),
        serverVersion: (json['server_version'] as num?)?.toInt() ?? 0,
        notes: json['notes'] as String?,
        inspectionRequest: json['inspection_request'] is Map<String, dynamic>
            ? InspectionRequestSummary.fromJson(
                json['inspection_request'] as Map<String, dynamic>,
              )
            : null,
      );

  final int id;
  final String status;
  final DateTime? assignedAt;
  final DateTime? downloadedAt;
  final DateTime? submittedAt;
  final DateTime? scheduledAt;
  final int serverVersion;
  final String? notes;
  final InspectionRequestSummary? inspectionRequest;

  String get statusLabel => Formatters.humanize(status);

  /// Friendly scheduling label: `"Assigned <date>"`, `"Scheduled <date>"`, or `"—"`.
  String get scheduleLabel {
    if (scheduledAt != null) {
      return 'Scheduled ${Formatters.date(scheduledAt!.toIso8601String())}';
    }
    if (assignedAt != null) {
      return 'Assigned ${Formatters.date(assignedAt!.toIso8601String())}';
    }
    return 'No schedule yet';
  }
}
