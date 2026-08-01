# Database Design

Project: Barangay 178 Health and Safety Inspection System

This document records the normalized database direction for the Laravel API. The schema is designed for PostgreSQL on Supabase while remaining testable with SQLite in automated tests.

## Design Principles

- Keep lookup values that affect business rules in reference tables.
- Store workflow records separately from uploaded files, OCR extraction results, and clearance output.
- Use foreign keys for ownership and workflow relationships.
- Use unique constraints for externally visible numbers and duplicate-prevention rules.
- Use indexes for status, owner, category, inspector, and QR verification lookups.
- Keep uploaded documents private by default and expose only safe verification data publicly.
- Preserve auditability with soft deletes on business records and audit logs for staff actions.

## Core Tables

### Users And Roles

- `roles`
  - Stores system roles such as administrator, barangay_staff, inspector, and resident.

- `users`
  - Stores authentication identity, role, contact details, status, and soft-delete metadata.

### Inspection Taxonomy

- `inspection_categories`
  - Stores dynamic inspection categories:
    - Business Establishments
    - Piggery
    - Poultry
    - Animal Raising: Dogs

- `application_types`
  - Stores supported request types:
    - New Application
    - Renewal

- `document_requirement_rules`
  - Maps inspection category + application type to required documents.
  - New applications require Barangay ID.
  - Renewals require Barangay ID and Business Permit.

### Resident Requests

- `inspection_requests`
  - Stores resident-submitted inspection applications.
  - Links resident, inspection category, application type, optional establishment, reviewer, and workflow status.
  - Stores applicant snapshot fields so a submitted request remains historically understandable even if a profile changes later.

### Assignment And Inspection Work

- `inspection_assignments`
  - Stores assignment records for inspectors without assuming fixed barangay schedules.
  - Tracks assignment, download, progress, submission, review, cancellation, and server version for sync safety.

- `inspection_schedules`
  - Existing table retained for backward compatibility with current controllers/tests.
  - Should be renamed or migrated to assignment terminology in a later backend workflow phase.

- `inspections`
  - Stores actual inspection execution records and completion timestamps.

- `checklists` and `checklist_items`
  - Store checklist templates and ordered checklist items.
  - Later phases should associate checklist templates directly with `inspection_categories`.

- `inspection_results`
  - Stores checklist item responses per inspection.

### Documents And OCR

- `documents`
  - Stores private uploaded document metadata using a polymorphic owner.

- `document_extractions`
  - Stores OCR classification, extracted fields, confidence, missing requirement data, and staff review metadata.

### Violations

- `violations`
  - Stores non-compliance findings, severity, status, seven-day correction deadline, assignee, and resolver.

- `violation_evidence`
  - Stores initial and corrective evidence metadata.

### Clearance And Verification

- `clearances`
  - Stores clearance number, issue date, expiration date, status, and issuer.

- `certifications`
  - Existing certificate records retained for current feature compatibility.

- `qr_codes`
  - Stores public verification codes linked polymorphically to clearances or certifications.

### Mobile Sync

- `mobile_sync_records`
  - Stores inspector mobile sync queue acknowledgements, client UUIDs, payload snapshots, conflicts, and processing errors.

### Audit Logs

- `audit_logs`
  - Stores auditable staff/system events with optional old/new value snapshots.

## Important Indexes And Constraints

- `inspection_categories.slug` unique.
- `application_types.slug` unique.
- `document_requirement_rules` unique on category, application type, and document type.
- `inspection_requests.request_number` unique.
- `inspection_requests` indexed by resident/status and status/submission date.
- `inspection_assignments` indexed by inspector/status and request/status.
- `mobile_sync_records.client_uuid` unique.
- `qr_codes.code` unique.
- `clearances.clearance_number` unique.
- `certifications.certificate_number` unique.

## Deferred Refinements

These are intentionally deferred to later phases:

- Replace `inspection_schedules` API terminology with assignment-based workflow names.
- Add resident profile tables if applicant data grows beyond the current user/profile fields.
- Link checklist templates directly to inspection categories.
- Add notification logs during the email notification phase.
- Add OCR processing job tables if asynchronous OCR queues need richer observability.
