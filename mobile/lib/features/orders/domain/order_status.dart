class OrderItemProgress {
  const OrderItemProgress({
    required this.id,
    required this.name,
    required this.quantity,
    required this.status,
    this.menuItemId,
    this.cancellableQuantity = 0,
    this.cancelledQuantity = 0,
    this.pendingCancellationQuantity = 0,
    this.canModify = false,
    this.canMarkServed = false,
    this.cancellations = const [],
    this.instructions,
  });

  final int id;
  final int? menuItemId;
  final String name;
  final int quantity;
  final String status;
  final int cancellableQuantity;
  final int cancelledQuantity;
  final int pendingCancellationQuantity;
  final bool canModify;
  final bool canMarkServed;
  final List<ItemCancellationProgress> cancellations;
  final String? instructions;

  bool get isFullyCancelled => quantity > 0 && cancelledQuantity >= quantity;
  String get effectiveStatus => isFullyCancelled ? 'cancelled' : status;
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
    this.tableId,
    required this.items,
    required this.tickets,
    required this.updatedAt,
    this.createdAt,
  });

  final int id;
  final String orderNumber;
  final String orderStatus;
  final String tableName;
  final int? tableId;
  final List<OrderItemProgress> items;
  final List<KitchenTicketProgress> tickets;
  final DateTime? updatedAt;
  final DateTime? createdAt;

  DateTime? get firstKnownActivityAt {
    if (createdAt != null) return createdAt;
    var earliest = updatedAt;
    for (final item in items) {
      for (final cancellation in item.cancellations) {
        final requested = cancellation.requestedAt;
        if (requested != null &&
            (earliest == null || requested.isBefore(earliest))) {
          earliest = requested;
        }
      }
    }
    return earliest;
  }
}
