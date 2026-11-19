import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class PendingOrderDraftStore {
  PendingOrderDraftStore({FlutterSecureStorage? storage})
    : _storage = storage ?? const FlutterSecureStorage();

  final FlutterSecureStorage _storage;
  static const _legacySubmissionKey = 'pending_order_submission_v1';
  Future<void> _cancellationIndexWrites = Future.value();

  String _cancellationHistoryKey(int userId) =>
      'cancellation_history_order_ids_v1_$userId';

  Future<List<int>> cancellationOrderIds(int userId) async {
    await _cancellationIndexWrites;
    return _readCancellationOrderIds(userId);
  }

  Future<List<int>> _readCancellationOrderIds(int userId) async {
    final value = await _storage.read(key: _cancellationHistoryKey(userId));
    if (value == null) return [];
    return (jsonDecode(value) as List).whereType<int>().toList();
  }

  /// Stores references only. Cancellation details always come from the API.
  Future<void> rememberCancellationOrder(int userId, int orderId) =>
      rememberCancellationOrders(userId, [orderId]);

  Future<void> rememberCancellationOrders(int userId, Iterable<int> orderIds) {
    final observed = orderIds.toSet();
    if (observed.isEmpty) return Future.value();
    final write = _cancellationIndexWrites.then((_) async {
      final ids = await _readCancellationOrderIds(userId);
      final newIds = observed.difference(ids.toSet());
      if (newIds.isEmpty) return;
      await _storage.write(
        key: _cancellationHistoryKey(userId),
        value: jsonEncode([...newIds, ...ids]),
      );
    });
    _cancellationIndexWrites = write.then<void>(
      (_) {},
      onError: (Object _, StackTrace _) {},
    );
    return write;
  }

  Future<bool> hasLegacySubmission(int tableId) async {
    final value = await _storage.read(key: _legacySubmissionKey);
    if (value == null) return false;
    final decoded = jsonDecode(value);
    return decoded is Map<String, dynamic> && decoded['table_id'] == tableId;
  }

  Future<void> clearLegacySubmission() =>
      _storage.delete(key: _legacySubmissionKey);

  String _submissionKey(int userId, int tableId) =>
      'pending_order_submission_v2_${userId}_$tableId';

  Future<Map<String, dynamic>?> read(int userId, int tableId) async {
    final value = await _storage.read(key: _submissionKey(userId, tableId));
    if (value == null || value.isEmpty) return null;
    final decoded = jsonDecode(value);
    if (decoded is! Map<String, dynamic>) {
      throw const FormatException('Invalid saved order submission.');
    }
    return decoded;
  }

  Future<void> write(int userId, int tableId, Map<String, Object?> value) =>
      _storage.write(
        key: _submissionKey(userId, tableId),
        value: jsonEncode(value),
      );

  Future<void> clear(int userId, int tableId) =>
      _storage.delete(key: _submissionKey(userId, tableId));

  String _cancellationKey(int userId, int orderId) =>
      'unconfirmed_cancellation_v1_${userId}_$orderId';

  Future<String?> readUnconfirmedCancellation(int userId, int orderId) =>
      _storage.read(key: _cancellationKey(userId, orderId));

  Future<void> saveUnconfirmedCancellation(
    int userId,
    int orderId,
    String description,
  ) async {
    if (await readUnconfirmedCancellation(userId, orderId) != null) {
      throw StateError('A cancellation still needs review.');
    }
    await _storage.write(
      key: _cancellationKey(userId, orderId),
      value: description,
    );
  }

  Future<void> clearUnconfirmedCancellation(int userId, int orderId) =>
      _storage.delete(key: _cancellationKey(userId, orderId));

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
