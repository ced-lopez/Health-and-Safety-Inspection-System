# Health & Safety Inspection System

A comprehensive **Health & Safety Inspection System** developed for **Barangay 178, North Caloocan City** as an undergraduate capstone project.

The system digitizes the barangay's health and safety inspection process by providing a centralized platform for residents, barangay personnel, administrators, and inspectors. It streamlines inspection requests, document verification, compliance evaluations, violation management, clearance issuance, and reporting.

The project consists of three integrated applications sharing a single Laravel REST API and PostgreSQL database.

---

# System Overview

The Health & Safety Inspection System consists of:

- **Barangay Management System (React Web)**
- **Resident Portal (React Web)**
- **Inspector Mobile Application (Flutter)**
- **Laravel REST API**
- **PostgreSQL Database (Supabase)**

All applications communicate through a centralized REST API.

```
                           PostgreSQL
                      (Supabase / Local)
                               ▲
                               │
                     Laravel 13 REST API
                               │
        ┌──────────────────────┼──────────────────────┐
        │                      │                      │
        ▼                      ▼                      ▼
Barangay Management      Resident Portal      Inspector Mobile
      (React)                (React)              (Flutter)
```

---

# Project Objectives

The system aims to:

- Digitize health and safety inspections
- Simplify inspection request processing
- Reduce manual paperwork
- Improve inspection tracking
- Assist document verification using OCR
- Monitor violations and corrective actions
- Generate QR-coded Health & Safety Clearances
- Improve communication between residents and barangay personnel
- Generate printable government documents and reports

---

# Applications

## Barangay Management System (Web)

Used by:

- Super Admin
- Admin
- Barangay Staff

Main modules include:

- Dashboard
- User & Role Management
- Resident Management
- Inspection Request Review
- Inspector Assignment
- AI Document Processing
- Compliance Checklist Management
- Inspection Reports
- Violation Management
- Payment Management
- Clearance Management
- QR Code Management
- Reports & Analytics
- Audit Logs

---

## Resident Portal (Web)

Residents can:

- Register an account
- Login securely
- Submit inspection requests
- Upload required documents
- Track application status
- Request follow-up inspections
- Download Health & Safety Clearances
- View QR Codes
- View payment status
- Receive notifications
- Update profile information

Supported inspection categories:

- Business
- Piggery
- Poultry
- Animal Raising (Dogs)

---

## Inspector Mobile Application

Developed using Flutter.

Inspectors can:

- Login securely
- View assigned inspections
- Complete digital inspection checklists
- Capture inspection photos
- Record violations
- Add inspection remarks
- Work offline
- Synchronize completed inspections automatically

---

# Core Features

## Inspection Request Management

Residents may submit inspection requests for:

- New Applications
- Renewal Applications

The system supports:

- Requirement verification
- Staff approval
- Inspector assignment
- Inspection tracking

---

## AI-Assisted Document Processing

Uses **Tesseract OCR**.

Features:

- OCR Text Extraction
- Document Classification
- Data Extraction
- Expiration Date Detection
- Missing Requirement Detection

Supported documents:

- Barangay ID
- Business Permit
- Safety Certificates

AI assists verification only.

Final approval remains the responsibility of authorized barangay personnel.

---

## Compliance Inspection

Supports digital inspection checklists for:

- Business Establishments
- Piggery
- Poultry
- Animal Raising

Checklist categories include:

- Health & Sanitation
- Fire Safety
- Workplace Safety
- Animal Welfare

---

## Violation Management

Supports:

- Violation recording
- Severity classification
- Corrective actions
- Seven-day compliance period
- Follow-up inspections
- Resolution tracking

---

## Clearance Management

The system manages:

- Clearance approval
- Clearance renewal
- One-year validity monitoring
- QR Code generation
- Public QR verification

QR codes verify only the final approved Health & Safety Clearance.

---

## Payment Management

Supports recording over-the-counter payments before clearance issuance.

Features include:

- Payment recording
- Payment verification
- Payment history
- Receipt generation

---

## Notification System

Residents receive notifications for:

- Registration
- Application submission
- Missing requirements
- Inspector assignment
- Inspection completion
- Violation notices
- Follow-up inspection schedules
- Clearance approval
- Renewal reminders

---

## Reports

Generate printable PDF reports including:

- Inspection Reports
- Violation Reports
- Follow-up Reports
- Health & Safety Clearances
- Payment Receipts
- Monthly Reports
- Annual Reports

---

## Audit Logs

The system records important activities including:

- Login
- Logout
- Registration
- Create
- Update
- Delete
- Approval
- Rejection
- Assignment
- Clearance Generation
- Payment Recording
- QR Verification
- Report Printing

Audit logs provide accountability and are intended to be immutable.

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
- TanStack Query
- React Hook Form
- Zod
- Lucide React

---

## Backend

- PHP 8.5+
- Laravel 13
- Laravel Sanctum
- RESTful API
- Laravel Storage
- PHPUnit

---

## Mobile

- Flutter
- Dart

---

## Database

- PostgreSQL
- Supabase

---

## AI

- Tesseract OCR

---

## Development Tools

- Git
- GitHub
- Docker (Optional)
- Composer
- npm

---

# Repository Structure

```
health-safety-system/

├── frontend/          # React Web Application
│   ├── src/
│   ├── public/
│   └── README.md
│
├── backend/           # Laravel REST API
│   ├── app/
│   ├── database/
│   ├── routes/
│   └── README.md
│
├── mobile/            # Flutter Mobile Application
│   ├── lib/
│   ├── android/
│   ├── ios/
│   └── README.md
│
├── docs/              # Project Documentation
│
└── README.md
```

---

# Development Workflow

1. Develop the Laravel REST API.
2. Develop the React Web Application.
3. Develop the Flutter Mobile Application.
4. Integrate all applications through the REST API.
5. Perform testing and validation.
6. Deploy the system.

---

# Getting Started

Each application contains its own setup instructions.

| Project        | Documentation        |
| -------------- | -------------------- |
| Backend API    | `backend/README.md`  |
| React Frontend | `frontend/README.md` |
| Flutter Mobile | `mobile/README.md`   |

Follow the setup guide in each directory before running the application.

---

# Development Principles

The project follows:

- Clean Architecture
- RESTful API Design
- Component-Based Development
- Role-Based Access Control
- Responsive Design
- Mobile-First Principles
- Reusable Components
- Secure Authentication
- Scalable Project Structure

---

# Current Project Status

### Backend

- Laravel 13 Initialization
- REST API
- Sanctum Authentication
- PostgreSQL Configuration
- API Versioning

### Frontend

- React 19 + Vite
- Tailwind CSS
- shadcn/ui
- Routing
- Theme Configuration

### Mobile

- Flutter project initialization _(planned/in development)_

### Upcoming Features

- Resident Portal
- Inspection Workflow
- AI OCR Integration
- Inspector Mobile Workflow
- QR Code Verification
- Payment Management
- Notifications
- Reports & Analytics
- Printable Government Documents

---

# License

This project was developed as an undergraduate capstone project for **Barangay 178, North Caloocan City**.

It is intended for academic purposes and future deployment within the barangay.
