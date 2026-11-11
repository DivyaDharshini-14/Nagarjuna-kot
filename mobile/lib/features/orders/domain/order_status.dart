class OrderItemProgress {
  const OrderItemProgress({
    required this.id,
    required this.name,
    required this.quantity,
    required this.status,
    this.cancellableQuantity = 0,
    this.cancelledQuantity = 0,
    this.pendingCancellationQuantity = 0,
    this.canModify = false,
    this.cancellations = const [],
    this.instructions,
  });

  final int id;
  final String name;
  final int quantity;
  final String status;
  final int cancellableQuantity;
  final int cancelledQuantity;
  final int pendingCancellationQuantity;
  final bool canModify;
  final List<ItemCancellationProgress> cancellations;
  final String? instructions;
}

class ItemCancellationProgress {
  const ItemCancellationProgress({
    required this.quantity,
    required this.status,
    required this.reason,
    required this.stockReversedQuantity,
    this.wastageQuantity = 0,
    this.requestedAt,
    this.decidedAt,
    this.requestedBy,
    this.decidedBy,
    this.decisionNote,
  });

  final int quantity;
  final String status;
  final String reason;
  final int stockReversedQuantity;
  final int wastageQuantity;
  final DateTime? requestedAt;
  final DateTime? decidedAt;
  final String? requestedBy;
  final String? decidedBy;
  final String? decisionNote;
}

class KitchenTicketProgress {
  const KitchenTicketProgress({
    required this.number,
    required this.kitchenName,
    required this.status,
  });

  final String number;
  final String kitchenName;
  final String status;
}

class OrderStatusSnapshot {
  const OrderStatusSnapshot({
    required this.id,
    required this.orderNumber,
    required this.orderStatus,
    required this.tableName,
    required this.items,
    required this.tickets,
    required this.updatedAt,
  });

  final int id;
  final String orderNumber;
  final String orderStatus;
  final String tableName;
  final List<OrderItemProgress> items;
  final List<KitchenTicketProgress> tickets;
  final DateTime? updatedAt;
}
