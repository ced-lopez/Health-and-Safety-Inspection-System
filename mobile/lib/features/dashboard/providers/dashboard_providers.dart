import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers/app_providers.dart';
import '../data/dashboard_datasource.dart';
import '../models/dashboard.dart';

final Provider<DashboardDataSource> dashboardDataSourceProvider =
    Provider<DashboardDataSource>(
      (ref) => DashboardDataSource(ref.watch(apiGatewayProvider)),
    );

/// Inspector dashboard — `GET /v1/dashboard`.
final FutureProvider<InspectorDashboard> inspectorDashboardProvider =
    FutureProvider<InspectorDashboard>((ref) async {
      final ds = ref.watch(dashboardDataSourceProvider);
      return ds.fetchInspector();
    });
