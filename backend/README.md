# Health & Safety Inspection System - Backend API

The **Health & Safety Inspection System Backend API** is a RESTful API built with **Laravel 13** and **PHP 8.5+** for **Barangay 178, North Caloocan City**.

It serves as the centralized backend for the Health & Safety Inspection System, supporting:

- Barangay Management System (React Web)
- Resident Portal (React Web)
- Inspector Mobile Application (Flutter)

The API manages authentication, inspection requests, AI-assisted document processing, compliance checklists, inspection reports, violation management, payment recording, QR-coded clearance verification, notifications, audit logs, and printable government documents.

---

# System Architecture

```
                    Laravel REST API
                           │
                    PostgreSQL Database
                     (Supabase / Local)
                           │
        ┌──────────────────┼──────────────────┐
        │                  │                  │
        ▼                  ▼                  ▼
 Barangay Web       Resident Portal     Inspector Mobile
   (React)             (React)             (Flutter)
```

All applications communicate through one centralized REST API.

---

# Technology Stack

## Backend

- PHP 8.5+
- Laravel 13
- Laravel Sanctum
- RESTful API
- Laravel Storage
- Laravel Queues
- PHPUnit

## Database

- PostgreSQL
- Supabase

## AI Integration

- Tesseract OCR

## Development

- Composer
- Git
- GitHub
- Docker (Optional)

---

# Supported Inspection Categories

The system supports inspections for:

- Business Establishments
- Piggery
- Poultry
- Animal Raising (Dogs)

---

# Core Features

## Authentication

- Login
- Logout
- Register
- Forgot Password
- Email Verification
- Laravel Sanctum Authentication
- Role-Based Access Control

### Supported Roles

- Super Admin
- Admin
- Barangay Staff
- Inspector
- Resident

---

## Resident Portal API

- Resident Registration
- Resident Profile Management
- Inspection Request Submission
- Document Upload
- Application Tracking
- Follow-up Inspection Request
- Clearance Download
- Notification Retrieval

---

## Barangay Management API

- Dashboard
- User Management
- Resident Management
- Inspection Request Review
- Inspector Assignment
- Compliance Checklist Management
- Inspection Reporting
- Violation Management
- Clearance Management
- Payment Recording
- Reports & Analytics
- Audit Logs

---

## Inspector Mobile API

- Inspector Login
- Assigned Inspections
- Digital Inspection Checklist
- Photo Upload
- Violation Recording
- Offline Synchronization
- Inspection Submission

---

## AI-Assisted Document Processing

Uses **Tesseract OCR**.

Features:

- OCR Text Extraction
- Image Preprocessing
- Document Classification
- Automated Data Extraction
- Expiration Date Detection
- Missing Requirement Detection

Supported Documents:

- Barangay ID
- Business Permit
- Safety Certificates

> AI assists document verification only. Final approval is always performed by authorized Barangay Staff.

---

## Clearance Management

Features

- Clearance Approval
- Clearance Renewal
- QR Code Generation
- Public QR Verification
- Clearance Revocation
- One-Year Validity Monitoring

The QR code verifies only the final approved Health & Safety Clearance.

---

## Payment Management

Supports over-the-counter payment recording before clearance issuance.

Features

- Record Payment
- Verify Payment
- Payment History
- Receipt Generation

---

## Notification System

Notifications include:

- Registration Successful
- Application Submitted
- Missing Requirements
- Inspector Assigned
- Inspection Completed
- Violation Notice
- Follow-up Inspection Required
- Clearance Approved
- Renewal Reminder
- Clearance Expired

---

## Reports

Generate printable PDF reports for:

- Inspection Reports
- Violation Reports
- Follow-up Reports
- Clearance Reports
- Monthly Reports
- Annual Reports

---

## Audit Logs

Records important system activities.

Examples:

- Login
- Logout
- Register
- Created
- Updated
- Deleted
- Approved
- Rejected
- Assigned
- Generated
- Printed
- Downloaded
- QR Verified
- Payment Recorded

Audit logs are immutable and cannot be deleted through the application.

---

# Project Structure

```
backend/

app/
├── Http/
│   ├── Controllers/
│   ├── Middleware/
│   ├── Requests/
│   └── Resources/
│
├── Models/
├── Services/
├── Policies/
├── Jobs/
├── Notifications/
└── Helpers/

bootstrap/

config/

database/
├── migrations/
├── seeders/
└── factories/

public/

resources/

routes/
└── api.php

storage/

tests/
```

---

# Quick Start

Clone the repository.

```bash
cd health-safety-system/backend
```

Install dependencies.

```bash
composer install
```

Copy the environment file.

```bash
cp .env.example .env
```

Generate the application key.

```bash
php artisan key:generate
```

Configure the PostgreSQL database.

Run database migrations.

```bash
php artisan migrate
```

(Optional) Seed the database.

```bash
php artisan db:seed
```

Start the development server.

```bash
php artisan serve
```

---

# API Base URL

```
http://localhost:8000/api/v1
```

Health Check

```
GET /api/v1/health
```

---

# Environment Configuration

```env
APP_URL=http://localhost:8000

FRONTEND_URL=http://localhost:5173

DB_CONNECTION=pgsql
DB_HOST=aws-1-ap-northeast-2.pooler.supabase.com
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres.YOUR_PROJECT_REF
DB_PASSWORD=YOUR_PASSWORD
DB_SSLMODE=require

SANCTUM_STATEFUL_DOMAINS=localhost,localhost:5173,127.0.0.1,127.0.0.1:5173
```

---

# API Standards

- RESTful API
- API Versioning (`/api/v1`)
- JSON Responses
- Resource Controllers
- Form Request Validation
- Consistent Response Format
- Proper HTTP Status Codes
- Pagination
- Rate Limiting

---

# Security

- Laravel Sanctum Authentication
- Role-Based Authorization
- CSRF Protection
- Request Validation
- Secure File Uploads
- Protected Storage
- Audit Logging
- Input Sanitization

---

# Development Commands

Start the development server.

```bash
php artisan serve
```

Run database migrations.

```bash
php artisan migrate
```

Run database seeders.

```bash
php artisan db:seed
```

Run fresh migrations with seeders.

```bash
php artisan migrate:fresh --seed
```

View API routes.

```bash
php artisan route:list --path=api
```

Run automated tests.

```bash
php artisan test
```

Run Laravel Pint.

```bash
./vendor/bin/pint
```

Clear caches.

```bash
php artisan optimize:clear
```

Validate Composer configuration.

```bash
composer validate
```

---

# Current Development Status

## Completed

- Laravel 13 Backend Initialization
- REST API Structure
- API Versioning
- Laravel Sanctum Configuration
- PostgreSQL Configuration
- Health Check Endpoint
- Base Project Architecture

## In Progress

- Database Design
- Resident Portal API
- Inspection Request Workflow
- AI OCR Integration
- Inspector Mobile API
- QR Code Verification
- Payment Management
- Notification System
- Reports & Analytics

---

# License

This project was developed as an undergraduate capstone project for **Barangay 178, North Caloocan City**.

It is intended for academic purposes and future deployment within the barangay.
