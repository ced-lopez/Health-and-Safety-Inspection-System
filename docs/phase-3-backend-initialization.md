# Phase 3: Backend Initialization

Project: Barangay 178 Health and Safety Inspection System

Scope status: Phase 3 only. This phase confirms and documents the Laravel REST API foundation without expanding business workflows from later phases.

## 1. Backend Strategy

The existing `backend/` Laravel application is retained as the single REST API for:

- Resident Web Portal
- Barangay Staff/Admin Web System
- Future Inspector Mobile Application

All clients should communicate through versioned API routes under `/api/v1`.

## 2. Confirmed Stack

The backend foundation is aligned to:

- PHP 8.5+
- Laravel 13
- Laravel Sanctum
- PostgreSQL
- Supabase-compatible connection settings
- PHPUnit for backend tests

## 3. API Foundation

Confirmed backend structure:

- `routes/api.php` defines versioned `/api/v1` routes.
- `bootstrap/app.php` registers API routing with the `/api` prefix.
- `bootstrap/app.php` enables Sanctum stateful API middleware.
- `bootstrap/app.php` registers the `role` middleware alias.
- `app/Http/Controllers/Api/BaseApiController.php` centralizes API response helpers.
- `app/Http/Controllers/Api/HealthController.php` exposes the public API health check.

Health endpoint:

```text
GET /api/v1/health
```

## 4. Environment Configuration

The backend uses `.env.example` as the setup template.

Important values:

```env
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:5173
DB_CONNECTION=pgsql
DB_SSLMODE=require
SANCTUM_STATEFUL_DOMAINS=localhost,localhost:5173,127.0.0.1,127.0.0.1:5173
```

Supabase credentials should be supplied locally in `.env`, not committed.

## 5. Files Added or Updated

- `backend/composer.json`
  - Updates Laravel framework constraint to `^13.0` and PHP constraint to `^8.5`.

- `backend/app/Models/*`
  - Models use Eloquent `$fillable` and `$hidden` properties (valid in both Laravel 12 and 13).

- `backend/routes/api.php`
  - Confirms the existing auth registration controller is exposed at `POST /api/v1/auth/register`.

- `backend/README.md`
  - Replaces the previous backend README with project-specific Laravel 13 setup, environment, API conventions, and development commands.

- `docs/phase-3-backend-initialization.md`
  - Records Phase 3 scope, backend decisions, configuration, and verification notes.

## 6. Verification

Commands run from `backend/`:

```bash
composer validate
php artisan route:list --path=api
php artisan test
```

Results:

- `composer validate` passed.
- `php artisan route:list --path=api` passed and listed 37 API routes.
- `php artisan test` passed with 36 tests and 145 assertions.

## 7. Next Gate

Phase 4 should focus on Database Design only.

Before starting Phase 4, confirm:

- Whether existing migration names using `inspection_schedules` should be renamed to assignment-based terminology.
- Whether current migrations should be refactored into a clean 3NF schema or evolved incrementally.
- Whether seed data should include all four inspection categories: business establishments, piggery, poultry, and animal raising dogs.
