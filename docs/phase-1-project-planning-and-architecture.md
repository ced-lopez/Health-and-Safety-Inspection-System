# Phase 1: Project Planning and Architecture

Project: Health and Safety Inspection System for Barangay 178, North Caloocan City

Prepared for: Capstone development

Scope status: Phase 1 only. This document defines the architecture, boundaries, risks, and next-step gates before continuing to implementation phases.

## 1. Project Goal

The system will digitize health and safety inspection workflows for Barangay 178. It will support resident inspection requests, barangay staff review, inspector field work, compliance tracking, violation management, OCR-assisted document verification, and QR-coded clearance verification.

The platform must be built as three independent applications connected to one Laravel REST API and one shared PostgreSQL database:

- Resident Web Portal
- Barangay Staff/Admin Web System
- Inspector Mobile Application

## 2. Core Users

### Super Administrator

Owns system-level control, user management, reports, settings, and administrative oversight.

### Barangay Staff

Reviews applications and documents, validates OCR output, assigns inspectors, reviews reports, approves clearances, manages violations, and generates operational reports.

### Inspector

Uses the mobile app to receive assignments, download inspection details, complete dynamic checklists, capture photos, record violations, submit reports, and sync offline work.

### Resident

Registers, submits inspection requests, uploads documents, tracks application status, receives email updates, requests follow-up inspections, renews clearances, and downloads QR-coded clearances.

## 3. Application Boundaries

### Backend API

Technology:

- Laravel
- Laravel Sanctum
- PostgreSQL through Supabase
- Laravel Storage
- Tesseract OCR integration

Responsibilities:

- Authentication and authorization
- Role-based access control
- Inspection request workflow
- Document upload and verification records
- OCR processing orchestration
- Dynamic checklist retrieval
- Inspection report submission
- Violation lifecycle management
- Clearance issuance
- QR verification endpoint
- Email notifications
- Audit logging

The backend must expose REST endpoints under a versioned API path, preferably `/api/v1`.

### Web Frontend

Technology:

- React
- Vite
- Tailwind CSS
- shadcn/ui
- React Router
- Axios
- React Hook Form
- Zod
- TanStack Query
- Lucide icons

Responsibilities:

- Resident portal workflows
- Staff/admin dashboard workflows
- Document upload interfaces
- Application status tracking
- Inspection assignment management
- Clearance and QR management
- Reports and dashboard views

### Mobile App

Technology:

- Flutter
- SQLite for offline storage
- Same Laravel REST API as the web apps

Responsibilities:

- Inspector login
- Assignment download
- Offline inspection data capture
- Dynamic checklist completion
- Photo capture
- Violation recording
- Background/automatic synchronization
- Conflict-aware sync behavior

## 4. Recommended Project Structure

The project should remain separated by application:

```text
health-safety-system/
  backend/
  frontend/
  mobile/
  docs/
```

Rules:

- Do not merge backend and frontend concerns.
- Do not make the mobile app depend on web frontend code.
- Keep shared contracts documented in `docs/`.
- Use REST API contracts as the integration boundary.

## 5. Domain Modules

The system should be organized around feature modules:

- Authentication and Role Management
- Resident Profile Management
- Inspection Requests
- Document Requirements
- OCR Document Processing
- Inspector Assignment
- Dynamic Compliance Checklists
- Inspection Reports
- Violations and Follow-up Inspections
- Clearances and QR Verification
- Email Notifications
- Dashboard and Reports
- Audit Logs
- Offline Mobile Synchronization

## 6. Inspection Categories

Supported categories:

- Business Establishments
- Piggery
- Poultry
- Animal Raising: Dogs

The checklist model must support category-specific checklist items. A checklist should not be hardcoded in the UI because the mobile app and web system must consume the same backend-defined checklist structure.

## 7. Application Types and Requirements

### New Application

Required document:

- Barangay ID

Business Permit is not required for new applications because City Hall issues it after the initial barangay inspection.

### Renewal

Required documents:

- Barangay ID
- Business Permit

The requirements engine should determine missing documents based on application type and inspection category.

## 8. High-Level Workflow

1. Resident registers and completes profile.
2. Resident submits inspection request.
3. Resident selects inspection category and application type.
4. System determines required documents.
5. Resident uploads documents.
6. OCR extracts candidate fields and flags missing or suspicious data.
7. Barangay Staff reviews the application, documents, and OCR output.
8. Staff approves request for inspection and assigns an inspector.
9. Inspector downloads assignment to mobile app.
10. Inspector conducts inspection using dynamic checklist.
11. Inspector records findings, photos, remarks, and violations.
12. Mobile app syncs data when internet is available.
13. If violations exist, system generates violation notice and starts seven-day compliance period.
14. Resident requests follow-up inspection after compliance.
15. If compliant, staff approves QR-coded clearance.
16. System sends renewal reminders before expiration.

## 9. Clearance Lifecycle

Clearance statuses:

- Active
- Expired
- Revoked
- Invalid

Validity:

- One year from issue date

Renewal reminders:

- 30 days before expiration
- 7 days before expiration
- On expiration day

The QR code should open a public verification page that shows only safe verification data, not private uploaded documents.

## 10. OCR Architecture

OCR should assist staff review but must never be the final decision-maker.

Processing steps:

1. Store uploaded document.
2. Preprocess image for OCR.
3. Classify document type.
4. Run Tesseract OCR.
5. Extract fields based on document type.
6. Detect expiration dates.
7. Detect missing required documents.
8. Save extraction results and confidence values.
9. Present results to staff for final approval or correction.

Supported extracted fields:

- Barangay ID: name, address, ID number
- Business Permit: business name, owner, permit number, issue date, expiration date, issuing authority
- Certificates: certificate number, certificate name, establishment, issue date, expiration date

## 11. Offline Mobile Sync Strategy

The mobile app should use SQLite as the local source of truth while offline.

Minimum sync requirements:

- Download assigned inspections before field work.
- Save checklist answers locally with timestamps.
- Save photos and violation records locally until uploaded.
- Queue create/update actions in a local sync queue.
- Mark each queued action with a unique client-generated UUID.
- Send queued actions to the API when online.
- Use server timestamps and status/version fields to detect conflicts.
- Never silently overwrite staff-reviewed or finalized records.

Recommended conflict behavior:

- If the server record is still open, accept inspector updates.
- If the server record has been finalized, reject mobile update and return a conflict response.
- If photo upload succeeds but report sync fails, preserve the local queue item until retry.

## 12. Database Direction

The PostgreSQL database should follow Third Normal Form where practical.

Required data groups:

- Users
- Roles and permissions
- Resident profiles
- Inspection categories
- Application types
- Inspection requests
- Required document rules
- Uploaded documents
- OCR extraction results
- Inspectors and assignments
- Checklist templates
- Checklist items
- Inspection reports
- Checklist responses
- Photos and evidence
- Violations
- Follow-up requests
- Clearances
- QR codes
- Email notification logs
- Audit logs
- Mobile sync records

Database rules:

- Use primary keys and foreign keys consistently.
- Add indexes for status, owner, category, date, and verification-code lookups.
- Use soft deletes for business records that should remain auditable.
- Use audit logs for staff/admin actions.
- Use constraints or enums carefully; prefer lookup tables when values may change.

## 13. API Principles

API conventions:

- Use `/api/v1` prefix.
- Use Laravel Form Requests for validation.
- Use API Resources for response formatting.
- Return consistent JSON envelopes.
- Use Sanctum for authenticated endpoints.
- Use role middleware for protected workflows.
- Keep public QR verification read-only.
- Avoid exposing internal OCR files or private document URLs publicly.

Suggested response envelope:

```json
{
  "success": true,
  "message": "Request completed successfully.",
  "data": {}
}
```

Suggested error envelope:

```json
{
  "success": false,
  "message": "Validation failed.",
  "errors": {}
}
```

## 14. Security and Privacy

Security requirements:

- Validate every request.
- Authorize every protected action.
- Store passwords using Laravel defaults.
- Do not expose uploaded documents through public URLs unless explicitly intended.
- Use signed or private storage access for sensitive files.
- Log staff/admin actions.
- Keep OCR output editable/reviewable by staff.
- Rate-limit authentication and public verification endpoints.
- Never show private applicant documents on public QR verification pages.

## 15. Existing Codebase Observations

The current repository already contains implementation work:

- `backend/` exists and appears to be a Laravel API.
- `frontend/` exists and appears to be a React/Vite app.
- Existing backend routes include dashboard, establishments, inspections, violations, certifications, documents, and admin users.
- Existing migrations include establishments, inspections, violations, clearances, documents, OCR extraction records, QR codes, roles, and audit logs.
- Existing frontend pages include dashboard, establishments, inspections, violations, certifications, users, login, and register.

Items to resolve before continuing:

- The project brief requested Laravel 12, but `backend/composer.json` requires `laravel/framework` version `^13.8`. The refactoring plan (refactoring-plan.md, "Confirmed Decisions") confirms proceeding with Laravel 13 — no downgrade required.
- The project brief says Barangay 178 does not use fixed inspection schedules, but the current backend contains `inspection_schedules` routes and tables.
- The project brief requires inspection categories including piggery, poultry, and animal raising, but the current visible frontend constants still use establishment-oriented inspection states.
- The project brief requires a resident portal and inspector mobile app, but the current visible structure only includes `backend` and `frontend`; no `mobile` folder exists yet.
- The frontend README is still the default Vite README and should be replaced in a later phase.
- Some README/API text appears to contain encoding artifacts and should be cleaned later.

These are not fixed in Phase 1. They are recorded so Phase 2 and Phase 3 can proceed deliberately.

## 16. Phase 1 Deliverables

Completed in this phase:

- Project architecture definition
- Application boundaries
- User and role definition
- Domain module map
- Workflow definition
- OCR processing architecture
- Offline sync strategy
- Database design direction
- API design principles
- Security and privacy requirements
- Existing codebase observations

## 17. Approval Gate Before Phase 2

Before moving to Phase 2, confirm the following decisions:

- Keep the existing `frontend/` project and refactor it, or recreate it cleanly.
- Use one React app for both Resident Portal and Staff/Admin Web System, or split them into two React apps.
- Align backend with Laravel 12 as requested, or continue with the currently installed Laravel 13 project. — DECIDED: Continue with Laravel 13.
- Replace fixed inspection schedule language with assignment-based inspection workflow.
- Add `mobile/` later during the Flutter phase.

Recommended decisions:

- Keep one React app with role-based routes for resident and staff/admin web workflows.
- Keep backend and frontend folders independent.
- Rename schedule concepts to inspection assignments during backend cleanup.
- Confirm whether Laravel 12 is mandatory for school compliance before changing framework version. — DECIDED: Laravel 13 is final; no downgrade needed.

No Phase 2 work should begin until these decisions are confirmed.
