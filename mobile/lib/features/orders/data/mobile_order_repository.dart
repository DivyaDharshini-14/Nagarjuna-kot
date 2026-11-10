import 'dart:math';

import '../../../core/network/mobile_api_client.dart';
import '../../menu/domain/menu.dart';
import '../domain/order_confirmation.dart';
import '../domain/order_status.dart';

abstract interface class MobileOrderRepository {
  String createIdempotencyKey();

  Future<OrderConfirmation> createOrder({
    required int tableId,
    required int guestCount,
    required String orderInstructions,
    required List<CartLine> lines,
    required String idempotencyKey,
  });

  Future<OrderConfirmation> addItems({
    required int orderId,
    required int tableId,
    required String orderInstructions,
    required List<CartLine> lines,
    required String idempotencyKey,
  });

  Future<OrderStatusSnapshot> getOrderStatus(int orderId);
}

class ApiMobileOrderRepository implements MobileOrderRepository {
  const ApiMobileOrderRepository({required this.api});

  final MobileApiClient api;

  @override
  String createIdempotencyKey() {
    final random = Random.secure();
    final bytes = List<int>.generate(16, (_) => random.nextInt(256));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    final hex = bytes
        .map((value) => value.toRadixString(16).padLeft(2, '0'))
        .join();
    return '${hex.substring(0, 8)}-${hex.substring(8, 12)}-'
        '${hex.substring(12, 16)}-${hex.substring(16, 20)}-${hex.substring(20)}';
  }

  @override
  Future<OrderConfirmation> createOrder({
    required int tableId,
    required int guestCount,
    required String orderInstructions,
    required List<CartLine> lines,
    required String idempotencyKey,
  }) async {
    final response = await api.post(
      'mobile/orders',
      requestHeaders: {'Idempotency-Key': idempotencyKey},
      body: {
        'table_id': tableId,
        'guest_count': guestCount,
        'special_instructions': orderInstructions,
        'items': _itemsPayload(lines),
      },
    );
    return _confirmationFromResponse(response);
  }

  @override
  Future<OrderConfirmation> addItems({
    required int orderId,
    required int tableId,
    required String orderInstructions,
    required List<CartLine> lines,
    required String idempotencyKey,
  }) async {
    final response = await api.post(
      'mobile/orders/$orderId/items',
      requestHeaders: {'Idempotency-Key': idempotencyKey},
      body: {
        'table_id': tableId,
        'special_instructions': orderInstructions,
        'items': _itemsPayload(lines),
      },
    );
    return _confirmationFromResponse(response);
  }

  @override
  Future<OrderStatusSnapshot> getOrderStatus(int orderId) async {
    final response = await api.get('mobile/orders/$orderId');
    final data = response['data'];
    final order = data is Map<String, dynamic> ? data['order'] : null;
    if (order is! Map<String, dynamic>) {
      throw const ApiException(
        502,
        'The server did not return order progress.',
      );
    }

    final items = order['items'];
    final tickets = order['kot_tickets'];
    final table = order['table'];
    return OrderStatusSnapshot(
      id: _int(order['id'], 'order ID'),
      orderNumber: _string(order['order_number'], 'order number'),
      orderStatus: _string(order['status'], 'order status'),
      tableName: table is Map<String, dynamic> && table['name'] is String
          ? table['name'] as String
          : '—',
      items: items is List
          ? items
                .whereType<Map<String, dynamic>>()
                .map(
                  (item) => OrderItemProgress(
                    id: _int(item['id'], 'order item ID'),
                    name: item['name'] is String
                        ? item['name'] as String
                        : 'Menu item',
                    quantity: _int(item['quantity'], 'item quantity'),
                    status: item['status'] is String
                        ? item['status'] as String
                        : 'new',
                    instructions: item['special_instructions'] is String
                        ? item['special_instructions'] as String
                        : null,
                  ),
                )
                .toList(growable: false)
          : const <OrderItemProgress>[],
      tickets: tickets is List
          ? tickets
                .whereType<Map<String, dynamic>>()
                .map(
                  (ticket) => KitchenTicketProgress(
                    number: ticket['kot_number'] is String
                        ? ticket['kot_number'] as String
                        : 'KOT',
                    kitchenName: ticket['kitchen_name'] is String
                        ? ticket['kitchen_name'] as String
                        : 'Kitchen',
                    status: ticket['status'] is String
                        ? ticket['status'] as String
                        : 'new',
                  ),
                )
                .toList(growable: false)
          : const <KitchenTicketProgress>[],
      updatedAt: order['updated_at'] is String
          ? DateTime.tryParse(order['updated_at'] as String)
          : null,
    );
  }

  List<Map<String, Object?>> _itemsPayload(List<CartLine> lines) {
    return lines
        .map(
          (line) => <String, Object?>{
            'menu_item_id': line.dish.id,
            'quantity': line.quantity,
            'special_instructions': line.note,
          },
        )
        .toList(growable: false);
  }

  OrderConfirmation _confirmationFromResponse(Map<String, dynamic> response) {
    final data = response['data'];
    final order = data is Map<String, dynamic> ? data['order'] : null;
    if (order is! Map<String, dynamic>) {
      throw const ApiException(
        502,
        'The server did not return order confirmation.',
      );
    }
    final ticketsValue = order['kot_tickets'];
    final tickets = ticketsValue is List
        ? ticketsValue
              .whereType<Map<String, dynamic>>()
              .map(
                (ticket) => KitchenTicketConfirmation(
                  number: ticket['kot_number'] is String
                      ? ticket['kot_number'] as String
                      : 'KOT',
                  kitchenName: ticket['kitchen_name'] is String
                      ? ticket['kitchen_name'] as String
                      : 'Kitchen',
                  status: ticket['status'] is String
                      ? ticket['status'] as String
                      : 'new',
                ),
              )
              .toList(growable: false)
        : const <KitchenTicketConfirmation>[];

    return OrderConfirmation(
      id: _int(order['id'], 'order ID'),
      orderNumber: _string(order['order_number'], 'order number'),
      status: _string(order['status'], 'order status'),
      totalAmount: _double(order['total_amount']),
      kotTickets: tickets,
      replayed: data is Map<String, dynamic> && data['replayed'] == true,
    );
  }

  int _int(Object? value, String label) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    throw ApiException(502, 'The server returned an invalid $label.');
  }

  String _string(Object? value, String label) {
    if (value is String && value.isNotEmpty) return value;
    throw ApiException(502, 'The server returned an invalid $label.');
  }

  double _double(Object? value) {
    if (value is num) return value.toDouble();
    if (value is String) {
      final parsed = double.tryParse(value);
      if (parsed != null) return parsed;
    }
    throw const ApiException(
      502,
      'The server returned an invalid order total.',
    );
  }
}
