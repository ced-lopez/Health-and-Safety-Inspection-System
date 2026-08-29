import '../../../core/constants/api_endpoints.dart';
import '../../../core/services/app_dio.dart';
import '../models/dashboard.dart';

Map<String, dynamic> _unwrapDashboardData(dynamic response) {
  if (response is Map<String, dynamic>) {
    final dynamic data = response['data'];
    if (data is Map<String, dynamic>) return data;
  }
  return const {};
}

class DashboardDataSource {
  DashboardDataSource(this._gateway);

  final ApiGateway _gateway;

  /// `GET /v1/dashboard` — inspector branch returns `stats`, `assigned_inspections`,
  /// `inspection_history`, `follow_ups`, `sync`.
  Future<InspectorDashboard> fetchInspector() async {
    final dynamic res = await _gateway.get(ApiEndpoints.dashboard);
    final Map<String, dynamic> data = _unwrapDashboardData(res);
    return InspectorDashboard.fromJson(data);
  }
}
