# Health & Safety Inspections System — Backend API

Laravel REST API for Barangay 178 North Caloocan City.

## Requirements

- PHP 8.3+
- Composer
- PostgreSQL 14+ (Supabase recommended)
- PHP extension: `pdo_pgsql` (required for PostgreSQL)

## Quick Start

```bash
cd health-safety-system/backend
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

API base URL: `http://localhost:8000/api/v1`

Health check: `GET /api/v1/health`

## PostgreSQL / Supabase Setup

1. Enable the PostgreSQL extension in `php.ini`:
   ```ini
   extension=pdo_pgsql
   extension=pgsql
   ```

2. Create a Supabase project or local PostgreSQL database named `health_safety_b178`.

3. Update `.env`:
   ```env
   DB_CONNECTION=pgsql
   DB_HOST=db.YOUR_PROJECT.supabase.co
   DB_PORT=5432
   DB_DATABASE=postgres
   DB_USERNAME=postgres
   DB_PASSWORD=your-supabase-password
   DB_SSLMODE=require
   ```

4. Run migrations:
   ```bash
   php artisan migrate
   ```

## Authentication (Sanctum)

- SPA cookie-based auth for the React frontend (`localhost:5173`)
- Bearer token auth for API clients
- CSRF cookie route: `GET /sanctum/csrf-cookie`

## API Structure

```
/api/v1/
├── health              GET   Public health check
├── auth/               POST  Login, register (Phase 4)
├── user                GET   Authenticated user
├── establishments/       CRUD (Phase 6)
├── inspections/        CRUD (Phases 7–9)
├── violations/         CRUD (Phase 10)
├── certifications/     CRUD (Phase 11)
└── documents/          AI processing (Phase 12)
```

## Development

```bash
php artisan serve          # Start API server on :8000
php artisan migrate        # Run migrations
php artisan route:list     # List all routes
php artisan test           # Run tests
```

## Frontend Integration

The React frontend expects:

```env
VITE_API_URL=http://localhost:8000/api
```

Ensure `FRONTEND_URL` in backend `.env` matches the Vite dev server URL for CORS.
