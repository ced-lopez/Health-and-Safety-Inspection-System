# Phase 4: Database Design

Project: Barangay 178 Health and Safety Inspection System

Scope status: Phase 4 only. This phase strengthens the normalized database foundation and documents the schema direction without implementing later authentication, resident portal, OCR, QR, notification, reporting, or mobile UI features.

## 1. Database Strategy

The existing Laravel migrations are retained because current backend tests and controllers already depend on them.

Phase 4 uses additive migrations to introduce missing normalized tables required by the capstone brief:

- Inspection categories
- Application types
- Document requirement rules
- Resident inspection requests
- Inspector assignment records
- Mobile sync records

This avoids breaking current implementation work while creating the correct database foundation for later phases.

## 2. New Migrations

- `backend/database/migrations/2026_07_12_050017_create_inspection_taxonomy_tables.php`
  - Creates `inspection_categories`, `application_types`, and `document_requirement_rules`.

- `backend/database/migrations/2026_07_12_050018_create_inspection_requests_table.php`
  - Creates `inspection_requests` for resident-submitted applications.

- `backend/database/migrations/2026_07_12_050019_create_inspection_assignments_table.php`
  - Creates `inspection_assignments` for assignment-based inspector work.

- `backend/database/migrations/2026_07_12_050020_create_mobile_sync_records_table.php`
  - Creates `mobile_sync_records` for offline mobile sync tracking and conflict handling.

## 3. Seed Data

- `backend/database/seeders/InspectionTaxonomySeeder.php`
  - Seeds four inspection categories:
    - Business Establishments
    - Piggery
    - Poultry
    - Animal Raising: Dogs
  - Seeds two application types:
    - New Application
    - Renewal
  - Seeds document rules:
    - New Application: Barangay ID
    - Renewal: Barangay ID and Business Permit

- `backend/database/seeders/DatabaseSeeder.php`
  - Adds `InspectionTaxonomySeeder` to the standard seeding flow.

## 4. Documentation

- `docs/database-design.md`
  - Documents table groups, relationships, key indexes, constraints, and deferred refinements.

- `docs/phase-4-database-design.md`
  - Records Phase 4 implementation scope and verification.

## 5. Verification

Commands run from `backend/`:

```bash
$env:APP_ENV='testing'; $env:DB_CONNECTION='sqlite'; $env:DB_DATABASE=':memory:'; php artisan migrate:fresh --seed
php artisan test
```

Results:

- Local migration and seeding smoke test passed using SQLite in memory.
- Backend test suite passed with 36 tests and 145 assertions.

## 6. Next Gate

Phase 5 should focus on Authentication & Role Management only.

Before starting Phase 5, confirm:

- Whether resident self-registration should use a dedicated `resident` role instead of the original default `staff` role. — DECIDED: Residents get `resident` role by default.
- Whether permissions should be table-driven or kept as role middleware during the capstone build.
