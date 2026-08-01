# Health & Safety Inspection System - Backend API

Laravel REST API for the Barangay 178 Health and Safety Inspection System.

## Confirmed Stack

- PHP 8.3 or newer
- Laravel 12
- Laravel Sanctum
- PostgreSQL, with Supabase recommended for hosted development
- Laravel Storage
- PHPUnit

## Quick Start

```bash
cd health-safety-system/backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

API base URL:

```text
http://localhost:8000/api/v1
```

Health check:

```text
GET http://localhost:8000/api/v1/health
```

## Environment

Copy `.env.example` to `.env`, then configure the database and frontend origin.

```env
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:5173

DB_CONNECTION=pgsql
DB_HOST=aws-1-ap-northeast-2.pooler.supabase.com
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres.YOUR_PROJECT_REF
DB_PASSWORD=your-supabase-password
DB_SSLMODE=require

SANCTUM_STATEFUL_DOMAINS=localhost,localhost:5173,127.0.0.1,127.0.0.1:5173
```

For local PostgreSQL, replace the Supabase host, username, and password with local credentials.

## API Conventions

- All application routes live under `/api/v1`.
- Responses use a consistent JSON envelope through shared API response helpers.
- Protected routes use Laravel Sanctum.
- Role-protected routes use the `role` route middleware alias.
- Public verification routes must expose only safe clearance status data.

## Development Commands

```bash
php artisan serve
php artisan route:list --path=api
php artisan test
composer validate
```

## Phase Boundary

Phase 3 initializes and verifies the Laravel backend foundation only. Database modeling, authentication workflow expansion, resident features, inspection requests, OCR, QR clearance issuance, email notifications, reports, and mobile sync are later phases.
