import 'dart:io';

import 'package:dio/dio.dart';

/// Unified error surfaced to the UI, translated from Dio/Laravel responses so
/// screens never deal with raw network details.
class ApiException implements Exception {
  ApiException({
    required this.message,
    this.statusCode,
    this.errors = const {},
    this.isNetwork = false,
  });

  /// Builds a domain-level [ApiException] from a [DioException].
  factory ApiException.fromDio(DioException e) {
    if (e.type == DioExceptionType.connectionError ||
        e.type == DioExceptionType.connectionTimeout ||
        e.type == DioExceptionType.sendTimeout ||
        e.type == DioExceptionType.receiveTimeout) {
      return ApiException(
        message:
            'Unable to reach the server. Check your connection and try again.',
        isNetwork: true,
      );
    }

    // Wrapped SocketException / HandshakeException can surface as unknown/badResponse.
    final Object? underlying = e.error;
    if (underlying is SocketException ||
        underlying is HandshakeException ||
        underlying is HttpException ||
        underlying.toString().contains('SocketException') ||
        underlying.toString().contains('HandshakeException')) {
      return ApiException(
        message:
            'Unable to reach the server. Check your connection and try again.',
        isNetwork: true,
      );
    }

    if (e.type == DioExceptionType.cancel) {
      return ApiException(message: 'Request was cancelled.');
    }

    final Response<dynamic>? response = e.response;
    final int? status = response?.statusCode;

    if (response != null) {
      final dynamic body = response.data;
      String message = _messageOf(body);
      Map<String, List<String>> errors = _errorsOf(body);

      // Laravel throws a 404 when a resource is missing; keep the server text.
      if (message.isEmpty) {
        message = switch (status) {
          400 => 'The request was invalid.',
          401 => 'Your session has expired. Please sign in again.',
          403 => 'You do not have permission to perform this action.',
          404 => 'The requested resource was not found.',
          422 => 'Please review the highlighted fields.',
          429 => 'Too many requests. Please try again shortly.',
          _ => 'Something went wrong (error $status).',
        };
      }

      return ApiException(
        message: message,
        statusCode: status,
        errors: errors,
        isNetwork: false,
      );
    }

    return ApiException(
      message: 'Something went wrong. Please try again.',
      statusCode: status,
    );
  }

  final String message;
  final int? statusCode;

  /// Field -> list of validation messages.
  final Map<String, List<String>> errors;

  /// True when the failure was caused by a connectivity problem.
  final bool isNetwork;

  bool get isUnauthorized => statusCode == 401;

  /// _message_ surfaced by the server, e.g. "These credentials do not match."
  static String _messageOf(dynamic body) {
    if (body is Map<String, dynamic>) {
      final dynamic message = body['message'];
      if (message is String) return message;
    }
    return '';
  }

  /// Parses the Laravel validation `errors` object, normalising each value to a
  /// list of strings.
  static Map<String, List<String>> _errorsOf(dynamic body) {
    final Map<String, List<String>> result = {};
    if (body is Map<String, dynamic>) {
      final dynamic errors = body['errors'];
      if (errors is Map<String, dynamic>) {
        errors.forEach((key, value) {
          if (value is List) {
            result[key] = value.map((e) => e.toString()).toList();
          } else if (value is String) {
            result[key] = [value];
          }
        });
      }
    }
    return result;
  }
}
