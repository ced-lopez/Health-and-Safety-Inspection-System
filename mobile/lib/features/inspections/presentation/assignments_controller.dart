import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/errors/api_exception.dart';
import '../data/assignments_repository.dart';
import '../models/assignment.dart';
import '../providers/assignments_providers.dart';

/// Loads assigned inspections for the signed-in inspector.
///
/// Strategy: fetch from the network first; when that fails, fall back to the
/// last successful fetch stored in the offline cache so the list still renders
/// without a connection. A status filter is applied server-side, and locally to
/// cached data.
class AssignmentsController extends AsyncNotifier<List<InspectionAssignment>> {
  String? _status;

  AssignmentsRepository get _repository =>
      ref.read(assignmentsRepositoryProvider);

  @override
  Future<List<InspectionAssignment>> build() async {
    try {
      final page = await _repository.refresh(status: _status);
      return page.assignments;
    } on ApiException {
      final cached = await _readFilteredCache();
      if (cached != null) return cached;
      rethrow;
    }
  }

  /// Re-fetches from the network (pull-to-refresh). Keeps showing cached data
  /// when the request fails but a cache exists.
  Future<void> refresh() async {
    try {
      final page = await _repository.refresh(status: _status);
      state = AsyncData(page.assignments);
    } on ApiException catch (error, stackTrace) {
      final cached = await _readFilteredCache();
      if (cached != null) {
        state = AsyncData(cached);
        return;
      }
      state = AsyncError(error, stackTrace);
    }
  }

  /// Switches the active status filter and reloads.
  Future<void> setStatus(String? status) async {
    if (_status == status) return;
    _status = status;
    await refresh();
  }

  Future<List<InspectionAssignment>?> _readFilteredCache() async {
    final List<InspectionAssignment>? cached = await _repository.readCached();
    if (cached == null) return null;
    final List<InspectionAssignment> filtered = _status == null
        ? cached
        : cached.where((a) => a.status == _status).toList();
    // Return empty list (not null) when filter legitimately matches zero items (P2-21).
    // Null is reserved for "no cache at all" which triggers rethrow/AsyncError.
    return filtered;
  }
}
