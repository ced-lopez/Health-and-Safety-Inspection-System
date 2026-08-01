# Phase 2: Frontend Initialization

Project: Barangay 178 Health and Safety Inspection System

Scope status: Phase 2 only. This phase standardizes the existing React/Vite frontend setup without adding new business features.

## 1. Frontend Strategy

The existing `frontend/` project is retained.

Decision:

- Use one React application for both Resident Portal and Barangay Staff/Admin workflows.
- Control access through authentication, role-based routes, and permission-aware navigation.
- Keep the Inspector application separate and build it later in Flutter during the mobile phase.

## 2. Confirmed Stack

The frontend is initialized with:

- React
- Vite
- JavaScript
- Tailwind CSS
- shadcn/ui-style components
- React Router
- Axios
- React Hook Form
- Zod
- TanStack Query
- Lucide Icons

## 3. Environment

The frontend uses:

```env
VITE_API_URL=http://localhost:8000/api
```

Service files append `/v1` to API calls, so the environment value should stop at `/api`.

## 4. Server State

TanStack Query has been added and configured through a shared query client.

Default query behavior:

- Retry failed queries once.
- Do not refetch automatically on window focus.
- Treat query data as fresh for 30 seconds.
- Do not retry mutations automatically.

This gives the app a consistent foundation for future API-backed pages.

## 5. Files Added or Updated

- `frontend/src/lib/queryClient.js`
  - Centralizes TanStack Query client defaults.

- `frontend/src/App.jsx`
  - Wraps the app in `QueryClientProvider`.

- `frontend/README.md`
  - Replaces the default Vite README with project-specific setup, stack, and conventions.

- `frontend/package.json`
  - Adds the required `@tanstack/react-query` dependency.

- `frontend/package-lock.json`
  - Updates the lockfile after dependency installation.

## 6. Verification

Commands run:

```bash
npm.cmd run build
npm.cmd run lint
```

Results:

- Production build passed.
- Lint completed with warnings only.

Current lint warnings are pre-existing cleanup items in UI primitives, auth context, login page, and users page. They do not block Phase 2.

## 7. Next Gate

Phase 3 should focus on Backend Initialization only.

Before starting Phase 3, confirm:

- Whether Laravel 12 is mandatory for school/project compliance. — DECIDED: No; Laravel 13 is retained.
- Whether the existing Laravel 13 project should be downgraded, recreated, or retained. — DECIDED: Retained as Laravel 13.
- Whether backend terminology should be corrected from fixed schedules to inspection assignments.
