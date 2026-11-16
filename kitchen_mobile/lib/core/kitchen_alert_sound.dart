import 'package:flutter/services.dart';

class KitchenAlertSound {
  static const _channel = MethodChannel('com.nagarjuna.kitchen/alerts');

  Future<bool> play() async {
    try {
      return await _channel.invokeMethod<bool>('playNewKotSound') ?? false;
    } on PlatformException {
      return false;
    } on MissingPluginException {
      return false;
    }
  }
}
