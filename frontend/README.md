# Health & Safety Inspection System - Frontend

The **Health & Safety Inspection System Frontend** is a React 19 web application developed for **Barangay 178, North Caloocan City**.

This application serves as a unified web platform for **Residents**, **Barangay Staff**, **Administrators**, **Super Administrators**, and **Inspectors** through role-based authentication and authorization.

The frontend communicates with the Laravel 13 REST API using Axios and TanStack Query to provide a responsive, secure, and modern user experience.

---

# System Architecture

```
                    Laravel REST API
                           │
                       MySQL Database
                           (Local)
                           │
                           ▼
                 React Web Application
        (Staff/Admin · Resident · Inspector)
```

Staff, resident, and inspector experiences are part of the same React application and use role-based routing.

---

# Technology Stack

## Frontend

- React 19
- Vite
- JavaScript
- Tailwind CSS
- shadcn/ui
- React Router DOM
- Axios
- React Hook Form
- Zod
- TanStack Query
- Lucide React

---

# User Roles

The frontend supports the following roles:

- Super Admin
- Admin
- Barangay Staff
- Inspector (Web Portal — assignments, checklist, violations, report submission)
- Resident

Each role is redirected to its own dashboard after authentication.

---

# Major Modules

## Shared Modules

- Authentication
- Profile Management
- Notifications
- Settings
- Error Pages

---

## Resident Portal

Residents can:

- Register
- Login
- Verify Email
- Submit Inspection Requests
- Upload Documents
- Track Application Status
- Request Follow-up Inspection
- Download Health & Safety Clearance
- View QR Code
- View Payment Status
- Receive Notifications
- Manage Profile

Supported inspection categories:

- Business
- Piggery
- Poultry
- Animal Raising (Dogs)

---

## Barangay Management Portal

Barangay Staff and Administrators can:

- Dashboard
- User Management
- Resident Management
- Inspection Request Review
- Inspector Assignment
- AI Document Verification
- Compliance Checklist Management
- Inspection Reports
- Violation Management
- Payment Management
- Clearance Management
- QR Code Management
- Reports & Analytics
- Audit Logs

---

# AI Document Processing

The frontend integrates with the backend OCR service.

Supported documents:

- Barangay ID
- Business Permit
- Safety Certificates

Displays:

- OCR Results
- Extracted Data
- Missing Requirements
- Expiration Warnings

---

# QR Code Verification

Residents receive a QR-coded Health & Safety Clearance.

The QR code opens a public verification page displaying:

- Verification Status
- Clearance Number
- Applicant Name
- Inspection Category
- Issue Date
- Expiration Date

---

# Printable Documents

The frontend supports previewing and downloading printable PDF documents.

Supported documents include:

- Inspection Request Form
- Inspection Assignment Form
- Inspection Report
- Violation Notice
- Follow-up Inspection Report
- Health & Safety Clearance
- Monthly Reports
- Annual Reports
- Payment Receipt

---

# Folder Structure

```
src/

components/
├── layout/
├── ui/
├── forms/
├── tables/

pages/
├── auth/
├── resident/
├── staff/
├── admin/
├── dashboard/
├── inspections/
├── violations/
├── clearances/
├── reports/
├── users/
├── settings/

services/

hooks/

utils/

routes/

assets/
```

---

# Local Setup

Install dependencies.

```bash
npm install
```

Create the environment file.

```bash
cp .env.example .env
```

Example configuration.

```env
VITE_API_URL=http://localhost:8000/api
```

API requests automatically append:

```
/v1
```

Start the development server.

```bash
npm run dev
```

Build for production.

```bash
npm run build
```

Run ESLint.

```bash
npm run lint
```

Preview the production build.

```bash
npm run preview
```

---

# Development Conventions

- Keep route-level pages under `src/pages`
- Keep reusable UI components under `src/components/ui`
- Keep layouts under `src/components/layout`
- Keep forms under `src/components/forms`
- Keep tables under `src/components/tables`
- Keep API services under `src/services`
- Keep reusable hooks under `src/hooks`
- Keep utilities under `src/utils`
- Use `@/` imports for files inside `src`
- Use React Hook Form with Zod for form validation
- Use TanStack Query for server-state management
- Follow the Barangay Daylight Design System
- Create reusable and accessible components
- Use role-based routing and protected routes
- Avoid duplicate code and follow clean architecture principles

---

# Current Development Status

## Completed

- React 19 + Vite Initialization
- Tailwind CSS Configuration
- shadcn/ui Setup
- Routing Configuration
- Axios Configuration
- TanStack Query Setup
- Barangay Daylight Theme
- Base Project Structure

## In Progress

- Role-Based Authentication
- Resident Portal
- Barangay Management Portal
- Inspection Request Workflow
- AI OCR Integration
- Payment Management
- QR Code Verification
- Reports & Analytics
- Audit Logs

---

# Design Principles

The frontend follows:

- Responsive Design
- Mobile-First Approach
- Government Dashboard Style
- Accessibility Best Practices
- Component-Based Architecture
- Clean Architecture
- Reusable Components
- Consistent Design System
- Secure Authentication Flow

---

# License

This project was developed as an undergraduate capstone project for **Barangay 178, North Caloocan City**.

It is intended for academic purposes and future deployment within the barangay.
