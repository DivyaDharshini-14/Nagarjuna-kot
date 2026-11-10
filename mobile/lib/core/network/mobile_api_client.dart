import 'dart:async';
import 'dart:convert';

import 'package:http/http.dart' as http;

typedef TokenReader = Future<String?> Function();

class MobileApiClient {
  MobileApiClient({String? baseUrl, this.tokenReader, http.Client? httpClient})
    : _baseUrl = (baseUrl ?? const String.fromEnvironment('API_BASE_URL'))
          .trim()
          .replaceFirst(RegExp(r'/+$'), ''),
      _httpClient = httpClient ?? http.Client();

  final String _baseUrl;
  final TokenReader? tokenReader;
  final http.Client _httpClient;

  Uri? resolveAssetUri(String? path) {
    if (_baseUrl.isEmpty || path == null || path.trim().isEmpty) return null;
    final asset = Uri.tryParse(path);
    if (asset != null && asset.hasScheme) {
      if (asset.scheme != 'http' && asset.scheme != 'https') return null;
      if (const bool.fromEnvironment('dart.vm.product') &&
          asset.scheme != 'https') {
        return null;
      }
      return asset;
    }
    final base = Uri.parse(_baseUrl);
    return base.replace(
      path: path.startsWith('/') ? path : '/$path',
      query: null,
      fragment: null,
    );
  }

  Future<Map<String, dynamic>> get(String path) => _send('GET', path);

  Future<Map<String, dynamic>> post(
    String path, {
    Map<String, Object?> body = const {},
    bool authenticated = true,
    Map<String, String> requestHeaders = const {},
  }) => _send(
    'POST',
    path,
    body: body,
    authenticated: authenticated,
    requestHeaders: requestHeaders,
  );

  Future<Map<String, dynamic>> _send(
    String method,
    String path, {
    Map<String, Object?>? body,
    bool authenticated = true,
    Map<String, String> requestHeaders = const {},
  }) async {
    if (_baseUrl.isEmpty) {
      throw const ApiConfigurationException(
        'API address is missing. Set API_BASE_URL when launching the app.',
      );
    }

    final baseUri = Uri.tryParse(_baseUrl);
    if (baseUri == null ||
        !baseUri.hasAuthority ||
        (baseUri.scheme != 'http' && baseUri.scheme != 'https')) {
      throw const ApiConfigurationException(
        'API_BASE_URL must be an absolute HTTP or HTTPS URL.',
      );
    }
    if (const bool.fromEnvironment('dart.vm.product') &&
        baseUri.scheme != 'https') {
      throw const ApiConfigurationException(
        'Release builds require an HTTPS API address.',
      );
    }
    if (const bool.fromEnvironment('dart.vm.product')) {
      final path = baseUri.path.replaceFirst(RegExp(r'/+$'), '');
      final host = baseUri.host.toLowerCase();
      if (path != '/api/v1') {
        throw const ApiConfigurationException(
          'Release builds require an API address ending in /api/v1.',
        );
      }
      if (baseUri.hasQuery || baseUri.hasFragment || baseUri.userInfo.isNotEmpty) {
        throw const ApiConfigurationException(
          'Release API addresses cannot contain credentials, query parameters, or fragments.',
        );
      }
      if (const {'localhost', '127.0.0.1', '10.0.2.2', 'your_api_host'}
              .contains(host) ||
          host.endsWith('.example')) {
        throw const ApiConfigurationException(
          'Replace the local or placeholder API address before release.',
        );
      }
    }

    final uri = Uri.parse('$_baseUrl/${path.replaceFirst(RegExp(r'^/+'), '')}');
    final headers = <String, String>{
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      ...requestHeaders,
    };
    if (authenticated) {
      final token = await tokenReader?.call();
      if (token == null || token.isEmpty) {
        throw const ApiException(
          401,
          'Your session has expired. Sign in again.',
        );
      }
      headers['Authorization'] = 'Bearer $token';
    }

    late http.Response response;
    try {
      final request = http.Request(method, uri)..headers.addAll(headers);
      if (body != null) request.body = jsonEncode(body);
      final streamed = await _httpClient
          .send(request)
          .timeout(const Duration(seconds: 20));
      response = await http.Response.fromStream(streamed)
          .timeout(const Duration(seconds: 20));
    } on TimeoutException {
      throw const ApiTransportException(
        'The server took too long to respond. Check your connection and retry.',
      );
    } on http.ClientException {
      throw const ApiTransportException(
        'Could not reach the server. Check the API address and your connection.',
      );
    }

    final decoded = _decode(response.body);
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw ApiException(
        response.statusCode,
        _errorMessage(decoded) ?? 'The request could not be completed.',
      );
    }
    if (decoded is! Map<String, dynamic>) {
      throw const ApiException(
        502,
        'The server returned an unexpected response.',
      );
    }
    return decoded;
  }

  Object? _decode(String body) {
    if (body.isEmpty) return null;
    try {
      return jsonDecode(body);
    } on FormatException {
      return null;
    }
  }

  String? _errorMessage(Object? decoded) {
    if (decoded is! Map<String, dynamic>) return null;
    final errors = decoded['errors'];
    if (errors is Map<String, dynamic>) {
      for (final value in errors.values) {
        if (value is List && value.isNotEmpty && value.first is String) {
          return value.first as String;
        }
      }
    }
    final message = decoded['message'];
    if (message is String && message.trim().isNotEmpty) return message;
    return null;
  }
}

class ApiConfigurationException implements Exception {
  const ApiConfigurationException(this.message);
  final String message;
}

class ApiException implements Exception {
  const ApiException(this.statusCode, this.message);
  final int statusCode;
  final String message;

  bool get requiresSignIn => statusCode == 401;
}

class ApiTransportException implements Exception {
  const ApiTransportException(this.message);
  final String message;
}
