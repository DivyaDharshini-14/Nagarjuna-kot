class CancellationRequest {
  const CancellationRequest({
    required this.batch,
    required this.orderId,
    required this.orderNumber,
    required this.tableName,
    required this.requestedBy,
    required this.requestedAt,
    required this.canApprove,
    required this.items,
  });

  final String batch;
  final int orderId;
  final String orderNumber;
  final String tableName;
  final String requestedBy;
  final DateTime? requestedAt;
  final bool canApprove;
  final List<CancellationRequestItem> items;

  factory CancellationRequest.fromJson(Map<String, dynamic> json) {
    final rawItems = json['items'];
    return CancellationRequest(
      batch: json['batch'] as String? ?? '',
      orderId: (json['order_id'] as num?)?.toInt() ?? 0,
      orderNumber: json['order_number'] as String? ?? 'Order',
      tableName: json['table_name'] as String? ?? 'Table',
      requestedBy: json['requested_by'] as String? ?? 'Staff',
      requestedAt: DateTime.tryParse(json['requested_at'] as String? ?? '')
          ?.toLocal(),
      canApprove: json['can_approve'] != false,
      items: rawItems is List
          ? rawItems
                .whereType<Map<String, dynamic>>()
                .map(CancellationRequestItem.fromJson)
                .toList(growable: false)
          : const [],
    );
  }
}

class CancellationRequestItem {
  const CancellationRequestItem({
    required this.name,
    required this.quantity,
    required this.reason,
  });

  final String name;
  final int quantity;
  final String reason;

  factory CancellationRequestItem.fromJson(Map<String, dynamic> json) =>
      CancellationRequestItem(
        name: json['name'] as String? ?? 'Dish',
        quantity: (json['quantity'] as num?)?.toInt() ?? 0,
        reason: json['reason'] as String? ?? '',
      );
}
