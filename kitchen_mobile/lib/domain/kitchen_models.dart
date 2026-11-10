class KitchenAccount {
  const KitchenAccount({required this.name, required this.branchName});

  final String name;
  final String branchName;

  factory KitchenAccount.fromJson(Map<String, dynamic> json) => KitchenAccount(
    name: json['name'] as String? ?? 'Kitchen staff',
    branchName:
        (json['branch'] as Map<String, dynamic>?)?['name'] as String? ??
        'Assigned branch',
  );
}

class KitchenStation {
  const KitchenStation({
    required this.id,
    required this.name,
    required this.code,
  });

  final int id;
  final String name;
  final String code;

  factory KitchenStation.fromJson(Map<String, dynamic> json) => KitchenStation(
    id: (json['id'] as num).toInt(),
    name: json['name'] as String? ?? 'Kitchen',
    code: json['code'] as String? ?? '',
  );
}

class KotDish {
  const KotDish({
    required this.name,
    required this.quantity,
    required this.status,
    required this.note,
    required this.foodType,
  });

  final String name;
  final int quantity;
  final String status;
  final String? note;
  final String? foodType;

  factory KotDish.fromJson(Map<String, dynamic> json) {
    final menuItem = json['menu_item'] as Map<String, dynamic>? ?? const {};
    final menuType = menuItem['menu_type'] as Map<String, dynamic>? ?? const {};
    return KotDish(
      name: menuItem['name'] as String? ?? 'Dish',
      quantity: (json['quantity'] as num?)?.toInt() ?? 1,
      status: json['item_status'] as String? ?? 'new',
      note: json['special_instructions'] as String?,
      foodType: menuType['name'] as String?,
    );
  }
}

class KotTicket {
  const KotTicket({
    required this.id,
    required this.number,
    required this.status,
    required this.createdAt,
    required this.orderNumber,
    required this.tableName,
    required this.orderNote,
    required this.kitchenNote,
    required this.dishes,
  });

  final int id;
  final String number;
  final String status;
  final DateTime createdAt;
  final String orderNumber;
  final String tableName;
  final String? orderNote;
  final String? kitchenNote;
  final List<KotDish> dishes;

  factory KotTicket.fromJson(Map<String, dynamic> json) {
    final order = json['order'] as Map<String, dynamic>? ?? const {};
    final table = order['table'] as Map<String, dynamic>? ?? const {};
    final items = json['items'] as List<dynamic>? ?? const [];
    return KotTicket(
      id: (json['id'] as num).toInt(),
      number: json['kot_number'] as String? ?? 'KOT',
      status: json['ticket_status'] as String? ?? 'new',
      createdAt:
          DateTime.tryParse(json['created_at'] as String? ?? '')?.toLocal() ??
          DateTime.now(),
      orderNumber: order['order_number'] as String? ?? 'Order',
      tableName: table['name'] as String? ?? 'Takeaway',
      orderNote: order['special_instructions'] as String?,
      kitchenNote: json['notes'] as String?,
      dishes: items
          .whereType<Map<String, dynamic>>()
          .map(KotDish.fromJson)
          .toList(growable: false),
    );
  }
}
