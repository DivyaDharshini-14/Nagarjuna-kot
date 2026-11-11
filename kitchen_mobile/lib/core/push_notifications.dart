import 'dart:async';
import 'dart:io';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';

typedef PushTokenAction = Future<void> Function(String token);
typedef PushMessageHandler = void Function(Map<String, dynamic> data);

@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  await Firebase.initializeApp();
}

class MobilePushNotifications {
  bool _initialized = false;
  StreamSubscription<String>? _tokenSubscription;
  StreamSubscription<RemoteMessage>? _messageSubscription;
  StreamSubscription<RemoteMessage>? _openedSubscription;
  String? _registeredToken;

  Future<void> initialize() async {
    try {
      await Firebase.initializeApp();
      FirebaseMessaging.onBackgroundMessage(firebaseMessagingBackgroundHandler);
      _initialized = true;
    } catch (error) {
      debugPrint('Push notifications are not configured: $error');
    }
  }

  Future<void> bind({
    required PushMessageHandler onForegroundMessage,
    required PushMessageHandler onNotificationOpened,
  }) async {
    if (!_initialized) return;
    _messageSubscription ??= FirebaseMessaging.onMessage.listen(
      (message) => onForegroundMessage(message.data),
    );
    _openedSubscription ??= FirebaseMessaging.onMessageOpenedApp.listen(
      (message) => onNotificationOpened(message.data),
    );
    final initialMessage = await FirebaseMessaging.instance.getInitialMessage();
    if (initialMessage != null) onNotificationOpened(initialMessage.data);
  }

  Future<void> activate({required PushTokenAction registerToken}) async {
    if (!_initialized) return;
    try {
      await FirebaseMessaging.instance.requestPermission(
        alert: true,
        badge: true,
        sound: true,
      );
      await _tokenSubscription?.cancel();
      _tokenSubscription = FirebaseMessaging.instance.onRegistered.listen((
        token,
      ) async {
        _registeredToken = token;
        try {
          await registerToken(token);
        } catch (error) {
          debugPrint('Could not refresh the push registration: $error');
        }
      });
      await FirebaseMessaging.instance.register();
    } catch (error) {
      debugPrint('Could not activate push notifications: $error');
    }
  }

  Future<void> deactivate({required PushTokenAction unregisterToken}) async {
    await _tokenSubscription?.cancel();
    _tokenSubscription = null;
    final token = _registeredToken;
    _registeredToken = null;
    if (token == null) return;
    try {
      await unregisterToken(token);
    } catch (error) {
      // Revoking the Sanctum token on sign-out also removes its registrations.
      debugPrint('Could not explicitly unregister push token: $error');
    }
  }

  String get platform => Platform.isIOS ? 'ios' : 'android';

  Future<void> dispose() async {
    await _tokenSubscription?.cancel();
    await _messageSubscription?.cancel();
    await _openedSubscription?.cancel();
  }
}
