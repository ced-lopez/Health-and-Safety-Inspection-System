# Barangay 178 Inspector — Flutter Mobile App

Inspector-only Flutter app sharing the Laravel `v1` API (`backend/routes/api.php:37`). See full plan and progress in `docs/mobile-completion-plan.md:1` and phase docs `docs/mobile-phase1-completion.md:1`.

## Status

* **Phase 1 ✅ DONE (2026-08-21):** Inspection detail + lifecycle — `GET /v1/inspection-assignments/{id}`, `PUT /{id}/start`, `PUT /{id}/submit` (`mobile/lib/features/inspections/presentation/assignment_detail_screen.dart:14`). `flutter analyze: No issues found`. List card now navigates to detail (`mobile/lib/features/inspections/presentation/inspections_screen.dart:273`).
* **Phase 2 ✅ DONE (2026-08-21):** Dynamic checklist + photo evidence — `GET/POST /v1/inspection-assignments/{id}/checklist` (`mobile/lib/features/inspections/presentation/checklist_screen.dart:18`, `mobile/lib/features/inspections/models/checklist.dart:1`, `mobile/lib/features/inspections/data/checklist_datasource.dart:1`). Grouped checklists, `compliant|non_compliant|needs_correction` radios, remarks, `evidence_files` multipart (camera/gallery, 5MB guard). Detail CTA → `/inspections/:id/checklist` (`mobile/lib/router/app_router.dart:54`).
* **Phase 3 ✅ DONE (2026-08-21):** Violations — `GET /v1/violations` list (filters, search, pagination), `GET /v1/violations/options` picker, `POST /v1/violations` + `POST /{id}/evidence` (`mobile/lib/features/violations/presentation/violations_screen.dart:12`, `mobile/lib/features/violations/presentation/violation_form_screen.dart:1`, `mobile/lib/features/violations/presentation/violation_detail_screen.dart:1`). FAB + checklist `Non-compliant → Record violation` → pre-filled form, evidence `initial` multipart (10MB guard). Detail shows badges/evidence.
* **Phase 4 ✅ DONE (2026-08-21):** Report — `GET/PUT /v1/inspection-assignments/{id}/report` (`mobile/lib/features/inspections/presentation/report_screen.dart:14`, `mobile/lib/features/inspections/models/report.dart:1`, `mobile/lib/features/inspections/data/report_datasource.dart:1`). Summary chips, `overall_assessment`/`recommendations`/`notes` (notes → assignment), read-only when `submitted`. Detail CTA → `/inspections/:id/report` (`mobile/lib/router/app_router.dart:16`).
* **Phase 5 ✅ DONE (2026-08-21):** Offline-first & sync — SQLite `sqflite` `inspector.db` (`mobile/lib/core/database/app_database.dart:1`) + `SyncService` `mobile/lib/core/services/sync_service.dart:1` (queue `assignment_start/submit`, `checklist_save`, `report_update`, `violation_create` with `XFile` paths), `AssignmentsRepository` now SQLite-primary with `SharedPrefs` migration, `syncOnReconnect` watcher (`mobile/lib/app.dart:14`), pending banner in Inspections (`mobile/lib/features/inspections/presentation/inspections_screen.dart:40`), logout clears DB queue, `pendingSyncCountProvider` (`mobile/lib/core/providers/app_providers.dart:1`).
* **Phase 6 ✅ DONE (2026-08-21):** Dashboard/history — `GET /v1/dashboard` inspector (`mobile/lib/features/dashboard/presentation/dashboard_screen.dart:13`, `mobile/lib/features/dashboard/models/dashboard.dart:1`, `mobile/lib/features/dashboard/data/dashboard_datasource.dart:1`). Stats `assigned/inProgress/completed/followUps/pendingSync` + local queue, assigned horizontal cards → detail, history + follow-ups lists, sync footer `lastSynced·server pending·local queue`, pull-to-refresh, `GoRouter` navigation.
* **Phase 7 ✅ DONE (2026-08-21):** QA — `mobile/android/app/src/main/AndroidManifest.xml:1` (CAMERA/READ_MEDIA_* + `usesCleartextTraffic`), `mobile/ios/Runner/Info.plist:1` (NSCamera/NSPhotoLibrary/NSMicrophone + ATS), `flutter analyze --fatal-infos: No issues found`, `dart format lib: 65 files`, `flutter test: 3 passed`, `flutter build apk --debug` ✔, `grep "coming in the next phase": 0`.

## Tech

`Flutter 3.12` / Dart / Riverpod `2.6.1` / GoRouter `17.4.0` / Dio `5.11.0` / `flutter_secure_storage` / `shared_preferences` / `connectivity_plus` / `camera` / `image_picker` / `file_picker` / `permission_handler` / `sqflite` / `path_provider` / `path` (`mobile/pubspec.yaml:44`).

## Quick start

```powershell
flutter pub get
flutter analyze
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api
# or production: --dart-define=API_BASE_URL=https://api.example.com/api
```

`API_BASE_URL` defaults to `http://10.0.2.2:8000/api` (`mobile/lib/core/config/app_config.dart:14`).

## Project docs

* Plan & progress: `docs/mobile-completion-plan.md:1`
* Phase 1 detail: `docs/mobile-phase1-completion.md:1`
* Phase 2 detail: `docs/mobile-phase2-completion.md:1`
* Phase 3 detail: `docs/mobile-phase3-completion.md:1`
* Phase 4 detail: `docs/mobile-phase4-completion.md:1`
* Phase 5 detail: `docs/mobile-phase5-completion.md:1`
* Phase 6 detail: `docs/mobile-phase6-completion.md:1`
* Phase 7 detail: `docs/mobile-phase7-completion.md:1`
* API: `docs/api-reference.md:689`
* Prompt: `UPDATED PROMPT.txt:1`
