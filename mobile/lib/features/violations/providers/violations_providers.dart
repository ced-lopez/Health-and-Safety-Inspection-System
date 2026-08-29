import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers/app_providers.dart';
import '../data/violations_datasource.dart';
import '../models/violation.dart';

final Provider<ViolationsDataSource> violationsDataSourceProvider =
    Provider<ViolationsDataSource>(
      (ref) => ViolationsDataSource(ref.watch(apiGatewayProvider)),
    );

/// Paginated violations list: `GET /v1/violations` with filters.
class ViolationsParams {
  const ViolationsParams({
    this.status,
    this.severity,
    this.search,
    this.page = 1,
    this.perPage = 20,
  });

  final String? status; // open|under_review|resolved|all|null
  final String? severity; // minor|moderate|major|all|null
  final String? search;
  final int page;
  final int perPage;

  ViolationsParams copyWith({
    String? status,
    String? severity,
    String? search,
    int? page,
    int? perPage,
  }) => ViolationsParams(
    status: status ?? this.status,
    severity: severity ?? this.severity,
    search: search ?? this.search,
    page: page ?? this.page,
    perPage: perPage ?? this.perPage,
  );
}

final StateProvider<ViolationsParams> violationsParamsProvider =
    StateProvider<ViolationsParams>((ref) => const ViolationsParams());

final FutureProvider<ViolationsPage> violationsPageProvider =
    FutureProvider<ViolationsPage>((ref) async {
      final params = ref.watch(violationsParamsProvider);
      final ds = ref.watch(violationsDataSourceProvider);
      return ds.fetch(
        status: params.status,
        severity: params.severity,
        search: params.search,
        perPage: params.perPage,
        page: params.page,
      );
    });

/// Options for violation form: `GET /v1/violations/options`.
final FutureProvider<ViolationOptions> violationOptionsProvider =
    FutureProvider<ViolationOptions>((ref) async {
      final ds = ref.watch(violationsDataSourceProvider);
      return ds.fetchOptions();
    });

/// Single violation detail.
final AutoDisposeFutureProviderFamily<Violation, String>
violationDetailProvider = FutureProvider.autoDispose.family<Violation, String>((
  ref,
  String id,
) async {
  final ds = ref.watch(violationsDataSourceProvider);
  return ds.fetchOne(id);
});
