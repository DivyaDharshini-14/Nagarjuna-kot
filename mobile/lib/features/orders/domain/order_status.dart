class OrderItemProgress {
  const OrderItemProgress({
    required this.id,
    required this.name,
    required this.quantity,
    required this.status,
    this.instructions,
  });

  final int id;
  final String name;
  final int quantity;
  final String status;
  final String? instructions;
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
