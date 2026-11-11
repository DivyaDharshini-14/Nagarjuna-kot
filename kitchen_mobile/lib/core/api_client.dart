import 'dart:async';
import 'dart:convert';

import 'package:http/http.dart' as http;

typedef TokenReader = Future<String?> Function();

class KitchenApiClient {
  KitchenApiClient({
    String? baseUrl,
    required this.readToken,
    http.Client? client,
  }) : _baseUrl = (baseUrl ?? const String.fromEnvironment('API_BASE_URL'))
           .trim()
           .replaceFirst(RegExp(r'/+$'), ''),
       _client = client ?? http.Client();

  final String _baseUrl;
  final TokenReader readToken;
  final http.Client _client;

  Future<Map<String, dynamic>> get(String path) => _send('GET', path);

  Future<Map<String, dynamic>> post(
    String path, {
    Map<String, Object?> body = const {},
    bool authenticated = true,
  }) => _send('POST', path, body: body, authenticated: authenticated);

  Future<Map<String, dynamic>> patch(
    String path, {
    required Map<String, Object?> body,
  }) => _send('PATCH', path, body: body);

  Future<Map<String, dynamic>> delete(
    String path, {
    Map<String, Object?> body = const {},
  }) => _send('DELETE', path, body: body);

  Future<Map<String, dynamic>> _send(
    String method,
    String path, {
    Map<String, Object?>? body,
    bool authenticated = true,
  }) async {
    if (_baseUrl.isEmpty) {
      throw const KitchenApiConfigurationException(
        'API address is missing. Set API_BASE_URL when launching the app.',
      );
    }
    final base = Uri.tryParse(_baseUrl);
    if (base == null ||
        !base.hasAuthority ||
        (base.scheme != 'http' && base.scheme != 'https')) {
      throw const KitchenApiConfigurationException(
        'API_BASE_URL must be an absolute HTTP or HTTPS address.',
      );
    }
    if (const bool.fromEnvironment('dart.vm.product') &&
        base.scheme != 'https') {
      throw const KitchenApiConfigurationException(
        'Release builds require an HTTPS API address.',
      );
    }
    if (const bool.fromEnvironment('dart.vm.product')) {
      final path = base.path.replaceFirst(RegExp(r'/+$'), '');
      final host = base.host.toLowerCase();
      if (path != '/api/v1') {
        throw const KitchenApiConfigurationException(
          'Release builds require an API address ending in /api/v1.',
        );
      }
      if (base.hasQuery || base.hasFragment || base.userInfo.isNotEmpty) {
        throw const KitchenApiConfigurationException(
          'Release API addresses cannot contain credentials, query parameters, or fragments.',
        );
      }
      if (const {
            'localhost',
            '127.0.0.1',
            '10.0.2.2',
            'your_api_host',
          }.contains(host) ||
          host.endsWith('.example')) {
        throw const KitchenApiConfigurationException(
          'Replace the local or placeholder API address before release.',
        );
      }
    }

    final uri = Uri.parse('$_baseUrl/${path.replaceFirst(RegExp(r'^/+'), '')}');
    final headers = <String, String>{
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    };
    if (authenticated) {
      final token = await readToken();
      if (token == null || token.isEmpty) {
        throw const KitchenApiException(401, 'Sign in to continue.');
      }
      headers['Authorization'] = 'Bearer $token';
    }

    late http.Response response;
    try {
      final request = http.Request(method, uri)..headers.addAll(headers);
      if (body != null) request.body = jsonEncode(body);
      final streamed = await _client
          .send(request)
          .timeout(const Duration(seconds: 20));
      response = await http.Response.fromStream(streamed)
          .timeout(const Duration(seconds: 20));
    } on TimeoutException {
      throw const KitchenApiTransportException(
        'The server took too long to respond. Check the connection and retry.',
      );
    } on http.ClientException {
      throw const KitchenApiTransportException(
        'Could not reach the server. Check the API address and network.',
      );
    }

    Object? decoded;
    try {
      decoded = response.body.isEmpty ? null : jsonDecode(response.body);
    } on FormatException {
      decoded = null;
    }
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw KitchenApiException(
        response.statusCode,
        _errorMessage(decoded) ?? 'The request could not be completed.',
      );
    }
    if (decoded is! Map<String, dynamic>) {
      throw const KitchenApiException(
        502,
        'The server returned an unexpected response.',
      );
    }
    return decoded;
  }

  String? _errorMessage(Object? body) {
    if (body is! Map<String, dynamic>) return null;
    final errors = body['errors'];
    if (errors is Map<String, dynamic>) {
      for (final value in errors.values) {
        if (value is List && value.isNotEmpty && value.first is String) {
          return value.first as String;
        }
      }
    }
    final message = body['message'];
    return message is String && message.trim().isNotEmpty ? message : null;
  }
}

class KitchenApiConfigurationException implements Exception {
  const KitchenApiConfigurationException(this.message);
  final String message;
}

class KitchenApiException implements Exception {
  const KitchenApiException(this.statusCode, this.message);
  final int statusCode;
  final String message;
  bool get requiresSignIn => statusCode == 401;
}

class KitchenApiTransportException implements Exception {
  const KitchenApiTransportException(this.message);
  final String message;
}
