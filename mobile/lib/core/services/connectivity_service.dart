import 'dart:async';

import 'package:connectivity_plus/connectivity_plus.dart';

/// Watches the device connectivity state and exposes an `online` stream.
///
/// Used by the sync engine (later phases) to know when queued inspections can
/// be replayed, and by the UI to show offline banners.
class ConnectivityService {
  ConnectivityService({Connectivity? connectivity})
    : _connectivity = connectivity ?? Connectivity();

  final Connectivity _connectivity;

  bool _online = true;
  bool get isOnline => _online;

  final StreamController<bool> _controller = StreamController<bool>.broadcast();
  Stream<bool> get onlineChanges => _controller.stream;

  /// Starts listening to connectivity changes. Call once at app startup.
  Future<void> start() async {
    _online = await _isConnected();
    await _connectivity.onConnectivityChanged.listen((results) {
      final bool online = results.any((r) => r != ConnectivityResult.none);
      if (online != _online) {
        _online = online;
        if (!_controller.isClosed) _controller.add(online);
      }
    }).asFuture<void>();
  }

  Future<bool> _isConnected() async {
    try {
      final List<ConnectivityResult> results = await _connectivity
          .checkConnectivity();
      return results.any((r) => r != ConnectivityResult.none);
    } catch (_) {
      return true; // Optimistically assume online when the probe fails.
    }
  }

  void dispose() {
    _controller.close();
  }
}
