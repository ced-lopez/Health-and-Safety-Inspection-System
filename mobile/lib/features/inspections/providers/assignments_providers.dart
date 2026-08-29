import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'dart:async';

import '../../../core/database/app_database.dart';
import '../../../core/providers/app_providers.dart';
import '../../../core/services/sync_service.dart';
import '../../violations/data/violations_datasource.dart';
import '../../violations/providers/violations_providers.dart';
import '../data/assignment_cache.dart';
import '../data/assignments_datasource.dart';
import '../data/assignments_repository.dart';
import '../data/checklist_datasource.dart';
import '../data/report_datasource.dart';
import '../models/assignment.dart';
import '../models/checklist.dart';
import '../models/report.dart';
import '../presentation/assignments_controller.dart';

final Provider<AssignmentCache> assignmentCacheProvider =
    Provider<AssignmentCache>((ref) => AssignmentCache());

final Provider<AssignmentsDataSource> assignmentsDataSourceProvider =
    Provider<AssignmentsDataSource>(
      (ref) => AssignmentsDataSource(ref.watch(apiGatewayProvider)),
    );

final Provider<AssignmentsRepository> assignmentsRepositoryProvider =
    Provider<AssignmentsRepository>(
      (ref) => AssignmentsRepository(
        ref.watch(assignmentsDataSourceProvider),
        ref.watch(assignmentCacheProvider),
        ref.watch(appDatabaseProvider),
      ),
    );

/// Owns the assigned-inspections list, falling back to the offline cache when
/// the network is unavailable.
final AsyncNotifierProvider<AssignmentsController, List<InspectionAssignment>>
assignmentsControllerProvider =
    AsyncNotifierProvider<AssignmentsController, List<InspectionAssignment>>(
      AssignmentsController.new,
    );

/// Detail provider for `GET /v1/inspection-assignments/{id}`.
/// Auto-dispose so navigating away drops the cached detail; a refresh
/// re-fetches on next visit.
final AutoDisposeFutureProviderFamily<InspectionAssignment, String>
assignmentDetailProvider = FutureProvider.autoDispose
    .family<InspectionAssignment, String>((ref, String id) async {
      final AssignmentsRepository repo = ref.watch(
        assignmentsRepositoryProvider,
      );
      return repo.fetchOne(id);
    });

final Provider<ChecklistDataSource> checklistDataSourceProvider =
    Provider<ChecklistDataSource>(
      (ref) => ChecklistDataSource(ref.watch(apiGatewayProvider)),
    );

/// `GET /v1/inspection-assignments/{id}/checklist` bundle.
final AutoDisposeFutureProviderFamily<ChecklistBundle, String>
checklistBundleProvider = FutureProvider.autoDispose
    .family<ChecklistBundle, String>((ref, String assignmentId) async {
      final ds = ref.watch(checklistDataSourceProvider);
      return ds.fetchBundle(assignmentId);
    });

final Provider<ReportDataSource> reportDataSourceProvider =
    Provider<ReportDataSource>(
      (ref) => ReportDataSource(ref.watch(apiGatewayProvider)),
    );

/// `GET /v1/inspection-assignments/{id}/report`
final AutoDisposeFutureProviderFamily<InspectionReport, String> reportProvider =
    FutureProvider.autoDispose.family<InspectionReport, String>((
      ref,
      String assignmentId,
    ) async {
      final ds = ref.watch(reportDataSourceProvider);
      return ds.fetch(assignmentId);
    });

// ---------------------------------------------------------------------------
// Sync — offline queue that replays on reconnect.
// Placed here to avoid circular import with core/providers/app_providers.
// ---------------------------------------------------------------------------

final Provider<SyncService> syncServiceProvider = Provider<SyncService>((ref) {
  final AppDatabase db = ref.watch(appDatabaseProvider);
  final AssignmentsRepository assignmentsRepo = ref.watch(
    assignmentsRepositoryProvider,
  );
  final ChecklistDataSource checklistDs = ref.watch(
    checklistDataSourceProvider,
  );
  final ReportDataSource reportDs = ref.watch(reportDataSourceProvider);
  final ViolationsDataSource violationsDs = ref.watch(
    violationsDataSourceProvider,
  );
  return SyncService(db, assignmentsRepo, checklistDs, reportDs, violationsDs);
});

/// Watches `onlineProvider` and triggers `SyncService.processQueue()` on reconnect, debounced.
final Provider<void> syncOnReconnectProvider = Provider<void>((ref) {
  Timer? debounce;
  ref.onDispose(() => debounce?.cancel());
  ref.listen(onlineProvider, (previous, next) {
    next.whenData((bool online) {
      if (online) {
        debounce?.cancel();
        debounce = Timer(const Duration(milliseconds: 800), () async {
          await ref.read(syncServiceProvider).processQueue();
          ref.invalidate(pendingSyncCountProvider);
          ref.invalidate(assignmentsControllerProvider);
          ref.invalidate(violationsPageProvider);
        });
      } else {
        debounce?.cancel();
      }
    });
  });
});
