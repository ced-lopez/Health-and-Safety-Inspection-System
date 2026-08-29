import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';

import '../errors/api_exception.dart';
import '../config/app_config.dart';
import 'session_manager.dart';

/// Builds the shared [Dio] instance used across every feature.
///
/// Responsibilities:
///  - point at the Laravel API ([AppConfig.apiBaseUrl]);
///  - attach the Sanctum `Authorization: Bearer <token>` header when present;
///  - normalise errors into [ApiException] so downstream code stays clean.
abstract final class AppDio {
  /// Creates a configured [Dio] bound to a [SessionManager] for the token.
  static Dio create(SessionManager session) {
    final Dio dio = Dio(
      BaseOptions(
        baseUrl: AppConfig.apiBaseUrl,
        connectTimeout: AppConfig.connectTimeout,
        receiveTimeout: AppConfig.receiveTimeout,
        sendTimeout: AppConfig.sendTimeout,
        headers: const {'Accept': 'application/json'},
        contentType: Headers.jsonContentType,
      ),
    );

    dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (RequestOptions options, RequestInterceptorHandler handler) {
          final String? token = session.token;
          if (token != null && token.isNotEmpty) {
            options.headers['Authorization'] = 'Bearer $token';
          }
          handler.next(options);
        },
        onError: (DioException err, ErrorInterceptorHandler handler) {
          handler.next(err); // Mapping happens at the data-source layer.
        },
      ),
    );

    if (kDebugMode) {
      dio.interceptors.add(
        LogInterceptor(
          requestBody: false,
          responseBody: false,
          logPrint: (Object entry) => debugPrint('[api] $entry'),
        ),
      );
    }

    return dio;
  }
}

/// Convenience wrapper that translates [DioException] into [ApiException].
/// Callers wrap network calls with this to keep UI/repository layers free of
/// concrete Dio types.
class ApiGateway {
  ApiGateway(this._dio);

  final Dio _dio;

  Future<dynamic> get(String path, {Map<String, dynamic>? query}) async {
    try {
      final Response<dynamic> res = await _dio.get(
        path,
        queryParameters: query,
      );
      return res.data;
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<dynamic> post(
    String path, {
    Object? data,
    bool multipart = false,
  }) async {
    try {
      final Response<dynamic> res = await _dio.post(
        path,
        data: data,
        options: multipart
            ? Options(contentType: Headers.multipartFormDataContentType)
            : null,
      );
      return res.data;
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<dynamic> put(String path, {Object? data}) async {
    try {
      final Response<dynamic> res = await _dio.put(path, data: data);
      return res.data;
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<dynamic> delete(String path) async {
    try {
      final Response<dynamic> res = await _dio.delete(path);
      return res.data;
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }
}
