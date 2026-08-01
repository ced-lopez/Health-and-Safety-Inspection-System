# Barangay 178 Health & Safety Frontend

React web application for the Barangay 178 Health and Safety Inspection System.

This frontend will serve role-based web workflows for residents, barangay staff, and administrators. It communicates with the Laravel REST API through Axios and uses TanStack Query for server-state management.

## Stack

- React 19
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

## Local Setup

Install dependencies:

```bash
npm install
```

Create a local environment file:

```bash
cp .env.example .env
```

Default API setting:

```env
VITE_API_URL=http://localhost:8000/api
```

The API services append the versioned `/v1` path per request.

Start the development server:

```bash
npm run dev
```

Build for production:

```bash
npm run build
```

Run linting:

```bash
npm run lint
```

## Project Conventions

- Keep route-level pages under `src/pages`.
- Keep reusable layout components under `src/components/layout`.
- Keep reusable UI primitives under `src/components/ui`.
- Keep API clients under `src/services`.
- Keep app constants and permission helpers under `src/utils`.
- Use `@/` imports for files inside `src`.
- Use React Hook Form and Zod for forms.
- Use TanStack Query for API-backed data fetching and mutations.
- Use the Barangay Daylight theme defined in `src/index.css`.

## Phase 2 Notes

The frontend project already existed before this phase. Phase 2 preserves the current React/Vite app and standardizes initialization pieces needed before feature work continues.
