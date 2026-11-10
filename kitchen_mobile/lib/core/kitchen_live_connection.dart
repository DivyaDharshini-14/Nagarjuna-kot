import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:http/http.dart' as http;

class KitchenLiveConnection {
  KitchenLiveConnection({
    required this.apiBaseUrl,
    required this.appKey,
    required this.host,
    required this.port,
    required this.secure,
    required this.readToken,
    http.Client? httpClient,
  }) : _httpClient = httpClient ?? http.Client();

  final String apiBaseUrl;
  final String appKey;
  final String host;
  final int port;
  final bool secure;
  final Future<String?> Function() readToken;
  final http.Client _httpClient;

  WebSocket? _socket;
  StreamSubscription<dynamic>? _subscription;
  Timer? _reconnectTimer;
  int _generation = 0;
  int _retryCount = 0;
  int? _kitchenId;
  bool _disposed = false;
  bool _connected = false;
  bool _reconnectEnabled = false;
  void Function(bool connected)? _onConnectionChanged;
  VoidCallback? _onTicketChanged;

  bool get isConfigured =>
      apiBaseUrl.trim().isNotEmpty &&
      appKey.trim().isNotEmpty &&
      host.trim().isNotEmpty;
  bool get isConnected => _connected;

  void connect({
    required int kitchenId,
    required void Function(bool connected) onConnectionChanged,
    required VoidCallback onTicketChanged,
  }) {
    disconnect();
    _disposed = false;
    _reconnectEnabled = true;
    _generation++;
    _kitchenId = kitchenId;
    _retryCount = 0;
    _onConnectionChanged = onConnectionChanged;
    _onTicketChanged = onTicketChanged;
    if (isConfigured) _open(_generation);
  }

  Future<void> _open(int generation) async {
    if (_disposed || generation != _generation || !isConfigured) return;
    try {
      final scheme = secure ? 'wss' : 'ws';
      final uri = Uri(
        scheme: scheme,
        host: host,
        port: port,
        path: '/app/$appKey',
        queryParameters: const {
          'protocol': '7',
          'client': 'flutter',
          'version': '1.0.0',
          'flash': 'false',
        },
      );
      final socket = await WebSocket.connect(uri.toString())
          .timeout(const Duration(seconds: 12));
      if (_disposed || generation != _generation) {
        await socket.close();
        return;
      }
      _socket = socket;
      _subscription = socket.listen(
        (message) => _handleMessage(message, generation),
        onDone: () => _handleDisconnect(generation),
        onError: (_) => _handleDisconnect(generation),
        cancelOnError: true,
      );
    } on Object {
      _handleDisconnect(generation);
    }
  }

  Future<void> _handleMessage(dynamic rawMessage, int generation) async {
    if (_disposed || generation != _generation) return;
    try {
      final message = jsonDecode(rawMessage as String) as Map<String, dynamic>;
      final event = message['event'] as String?;
      final data = _decodeData(message['data']);
      if (event == 'pusher:connection_established') {
        final socketId = data['socket_id'] as String?;
        if (socketId != null) {
          await _authorizeAndSubscribe(socketId, generation);
        }
      } else if (event == 'pusher:ping') {
        _send({'event': 'pusher:pong', 'data': {}});
      } else if (event == 'pusher_internal:subscription_succeeded' ||
          event == 'pusher:subscription_succeeded') {
        _retryCount = 0;
        _setConnected(true);
      } else if (event == 'kot.ticket.created' ||
          event == 'kot.ticket.updated') {
        _onTicketChanged?.call();
      } else if (event == 'pusher:error') {
        _setConnected(false);
        await _socket?.close();
      }
    } on Object {
      // Ignore malformed events; the API refresh remains the source of truth.
    }
  }

  Future<void> _authorizeAndSubscribe(String socketId, int generation) async {
    final kitchenId = _kitchenId;
    if (kitchenId == null || generation != _generation) return;
    final token = await readToken();
    if (token == null || token.isEmpty) {
      await _socket?.close();
      return;
    }

    final apiUri = Uri.parse(apiBaseUrl.trim())
        .replace(path: '/api/broadcasting/auth', query: null, fragment: null);
    final channelName = 'private-kitchens.$kitchenId';
    try {
      final response = await _httpClient
          .post(
            apiUri,
            headers: {
              'Accept': 'application/json',
              'Authorization': 'Bearer $token',
              'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: {'socket_id': socketId, 'channel_name': channelName},
          )
          .timeout(const Duration(seconds: 12));
      if (response.statusCode < 200 || response.statusCode >= 300) {
        _setConnected(false);
        if (response.statusCode == 401 || response.statusCode == 403) {
          _reconnectEnabled = false;
        }
        await _socket?.close();
        return;
      }
      final authorization =
          (jsonDecode(response.body) as Map<String, dynamic>)['auth']
              as String?;
      if (authorization == null || generation != _generation) {
        await _socket?.close();
        return;
      }
      _send({
        'event': 'pusher:subscribe',
        'data': {'auth': authorization, 'channel': channelName},
      });
    } on Object {
      _setConnected(false);
      await _socket?.close();
    }
  }

  Map<String, dynamic> _decodeData(Object? value) {
    if (value is Map<String, dynamic>) return value;
    if (value is String) {
      final decoded = jsonDecode(value);
      if (decoded is Map<String, dynamic>) return decoded;
    }
    return const {};
  }

  void _send(Map<String, Object?> message) {
    final socket = _socket;
    if (socket == null || socket.readyState != WebSocket.open) return;
    socket.add(jsonEncode(message));
  }

  void _handleDisconnect(int generation) {
    if (_disposed || generation != _generation) return;
    _socket = null;
    _subscription = null;
    _setConnected(false);
    if (!_reconnectEnabled) return;
    _reconnectTimer?.cancel();
    final seconds = switch (_retryCount++) {
      0 => 2,
      1 => 4,
      2 => 8,
      _ => 15,
    };
    _reconnectTimer = Timer(Duration(seconds: seconds), () {
      _reconnectTimer = null;
      _open(generation);
    });
  }

  void _setConnected(bool value) {
    if (_connected == value) return;
    _connected = value;
    _onConnectionChanged?.call(value);
  }

  void disconnect() {
    _generation++;
    _reconnectEnabled = false;
    _reconnectTimer?.cancel();
    _reconnectTimer = null;
    _subscription?.cancel();
    _subscription = null;
    final socket = _socket;
    _socket = null;
    if (socket != null && socket.readyState == WebSocket.open) {
      socket.close();
    }
    _setConnected(false);
  }

  void dispose() {
    _onConnectionChanged = null;
    _onTicketChanged = null;
    disconnect();
    _disposed = true;
    _httpClient.close();
  }
}

typedef VoidCallback = void Function();
