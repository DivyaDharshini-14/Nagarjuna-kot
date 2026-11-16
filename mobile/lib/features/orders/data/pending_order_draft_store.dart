import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class PendingOrderDraftStore {
  PendingOrderDraftStore({FlutterSecureStorage? storage})
    : _storage = storage ?? const FlutterSecureStorage();

  static const _key = 'pending_order_submission_v1';
  final FlutterSecureStorage _storage;

  Future<Map<String, dynamic>?> read() async {
    final value = await _storage.read(key: _key);
    if (value == null || value.isEmpty) return null;
    try {
      final decoded = jsonDecode(value);
      return decoded is Map<String, dynamic> ? decoded : null;
    } on FormatException {
      await clear();
      return null;
    }
  }

  Future<void> write(Map<String, Object?> value) =>
      _storage.write(key: _key, value: jsonEncode(value));

  Future<void> clear() => _storage.delete(key: _key);

  String _additionKey(int userId) => 'pending_quantity_addition_v1_$userId';

  Future<PendingQuantityAddition?> readQuantityAddition(int userId) async {
    final value = await _storage.read(key: _additionKey(userId));
    if (value == null) return null;
    final decoded = jsonDecode(value);
    if (decoded is! Map<String, dynamic>) {
      throw const FormatException('The saved dish addition could not be read.');
    }
    return PendingQuantityAddition.fromJson(decoded);
  }

  Future<void> writeQuantityAddition(
    int userId,
    PendingQuantityAddition addition,
  ) async {
    final existing = await readQuantityAddition(userId);
    if (existing != null &&
        existing.idempotencyKey != addition.idempotencyKey) {
      throw StateError(
        'Check the saved dish addition before adding more portions.',
      );
    }
    await _storage.write(
      key: _additionKey(userId),
      value: jsonEncode(addition.toJson()),
    );
  }

  Future<void> clearQuantityAddition(int userId, String idempotencyKey) async {
    final existing = await readQuantityAddition(userId);
    if (existing?.idempotencyKey == idempotencyKey) {
      await _storage.delete(key: _additionKey(userId));
    }
  }
}

/// The request identity and payload stay unchanged until its result is known.
class PendingQuantityAddition {
  const PendingQuantityAddition({
    required this.orderId,
    required this.tableId,
    required this.menuItemId,
    required this.quantity,
    required this.instructions,
    required this.idempotencyKey,
    required this.dishName,
  });

  final int orderId;
  final int tableId;
  final int menuItemId;
  final int quantity;
  final String? instructions;
  final String idempotencyKey;
  final String dishName;

  Map<String, Object?> toJson() => {
    'order_id': orderId,
    'table_id': tableId,
    'menu_item_id': menuItemId,
    'quantity': quantity,
    'instructions': instructions,
    'idempotency_key': idempotencyKey,
    'dish_name': dishName,
  };

  factory PendingQuantityAddition.fromJson(Map<String, dynamic> json) {
    final orderId = json['order_id'];
    final tableId = json['table_id'];
    final menuItemId = json['menu_item_id'];
    final quantity = json['quantity'];
    final key = json['idempotency_key'];
    if (orderId is! int ||
        orderId < 1 ||
        tableId is! int ||
        tableId < 1 ||
        menuItemId is! int ||
        menuItemId < 1 ||
        quantity is! int ||
        quantity < 1 ||
        quantity > 99 ||
        key is! String ||
        key.isEmpty) {
      throw const FormatException('The saved dish addition is incomplete.');
    }
    return PendingQuantityAddition(
      orderId: orderId,
      tableId: tableId,
      menuItemId: menuItemId,
      quantity: quantity,
      instructions: json['instructions'] as String?,
      idempotencyKey: key,
      dishName: json['dish_name'] as String? ?? 'Dish',
    );
  }
}
