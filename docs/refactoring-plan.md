# Refactoring Plan — Barangay Inspection & Clearance Management System

Status: Reviewed and approved. Confirmed decisions incorporated.

---

## Confirmed Decisions

| Question | Decision |
|----------|----------|
| Role for `health_officer` | Renamed to `barangay_staff` |
| Resident role | New `resident` role added |
| InspectionSchedule vs Assignment | Both coexist; new assignments complement existing schedules |
| Resident portal layout | Same sidebar/layout as staff, with role-filtered nav items |
| Laravel version | Upgraded to `^13.0` (now running v13.23.0) |
| PHP version | Upgraded to `^8.5` |

---

## 1. Analysis of Existing Architecture

### Current Structure
```
health-safety-system/
  backend/     — Laravel 13 REST API (Sanctum auth)
  frontend/    — React 19 + Vite 8 app (shadcn/ui, Tailwind v4, TanStack Query, React Router v7)
  docs/        — 6 design documents
  mobile/      — DOES NOT EXIST YET
```

### Current Backend Modules (Working)
| Module | Status |
|--------|--------|
| Auth (register/login/logout/me) | Complete — assigns `resident` by default |
| User/Role Management | Complete — 4 roles (administrator, barangay_staff, inspector, resident) |
| Establishment CRUD | Complete |
| Inspection Schedules | Complete — legacy pattern retained |
| Compliance Checklists | Complete |
| Inspection Reports | Complete |
| Violation Tracking | Complete |
| Certifications & Clearances | Complete — QR codes + public verification |
| Document Upload | Scaffold — model exists, controller missing |
| Audit Logging | Complete |
| Dashboard Stats | Complete |
| Inspection Taxonomy Tables | Exist — models missing |
| Inspection Requests Table | Exists — model missing |
| Inspection Assignments Table | Exists — model missing |
| Mobile Sync Records Table | Exists — model missing |

### Current Frontend Modules (Working)
| Module | Status |
|--------|--------|
| Login | Complete |
| Register Page | Exists but route disconnected |
| Dashboard | Complete |
| Establishments | Complete |
| Inspections | Complete |
| Violations | Complete |
| Certifications | Complete |
| Users | Complete |
| Sidebar/Nav | Updated for resident + staff nav |
| UI Components | All 18 shadcn/ui primitives ready |

---

## 2. Implementation Order

| Phase | What | Preserves Existing? |
|-------|------|---------------------|
| 1 | Add missing Eloquent models (6 models) | ✅ Yes |
| 2 | Add resident role, create notification migrations, user profile columns | ✅ Yes |
| 3 | Build backend: InspectionRequest + Assignment + Document controllers | ✅ Yes |
| 4 | Build backend: FollowUp, Notification, Report, AuditLog controllers | ✅ Yes |
| 5 | Build backend: Email notification Mail/Notification classes | ✅ Yes |
| 6 | Frontend: Register resident route + permissions + sidebar | ✅ Yes |
| 7 | Frontend: Resident portal pages (8 pages) | ✅ Yes |
| 8 | Frontend: Staff portal pages (new modules) | ✅ Yes |
| 9 | Frontend: Connect inspection request flow end-to-end | ✅ Yes |
| 10 | Mobile: Initialize Flutter project + auth | ✅ New project |
| 11 | Mobile: Offline inspection + sync | ✅ New project |
| 12 | OCR processing integration (Tesseract) | ✅ Additive |
| 13 | Reports/PDF generation | ✅ Additive |
