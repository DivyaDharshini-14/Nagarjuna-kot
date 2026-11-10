class KitchenTicketConfirmation {
  const KitchenTicketConfirmation({
    required this.number,
    required this.kitchenName,
    required this.status,
  });

  final String number;
  final String kitchenName;
  final String status;
}

class OrderConfirmation {
  const OrderConfirmation({
    required this.id,
    required this.orderNumber,
    required this.status,
    required this.totalAmount,
    required this.kotTickets,
    required this.replayed,
  });

  final int id;
  final String orderNumber;
  final String status;
  final double totalAmount;
  final List<KitchenTicketConfirmation> kotTickets;
  final bool replayed;
}
