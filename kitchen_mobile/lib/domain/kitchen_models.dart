class KitchenAccount {
  const KitchenAccount({
    required this.name,
    required this.branchName,
    this.canManageStock = false,
  });

  final String name;
  final String branchName;
  final bool canManageStock;

  factory KitchenAccount.fromJson(Map<String, dynamic> json) => KitchenAccount(
    name: json['name'] as String? ?? 'Kitchen staff',
    branchName:
        (json['branch'] as Map<String, dynamic>?)?['name'] as String? ??
        'Assigned branch',
    canManageStock: json['can_manage_stock'] == true,
  );
}

class KitchenStockItem {
  const KitchenStockItem({
    required this.id,
    required this.name,
    required this.code,
    required this.stockTrackingEnabled,
    required this.quantity,
    required this.availability,
    required this.kitchens,
  });

  final int id;
  final String name;
  final String code;
  final bool stockTrackingEnabled;
  final int? quantity;
  final String availability;
  final List<String> kitchens;

  factory KitchenStockItem.fromJson(Map<String, dynamic> json) {
    final stations = json['kitchens'] as List<dynamic>? ?? const [];
    return KitchenStockItem(
      id: (json['id'] as num).toInt(),
      name: json['name'] as String? ?? 'Dish',
      code: json['code'] as String? ?? '',
      stockTrackingEnabled: json['stock_tracking_enabled'] == true,
      quantity: (json['prepared_stock_quantity'] as num?)?.toInt(),
      availability: json['availability'] as String? ?? 'available',
      kitchens: stations
          .whereType<Map<String, dynamic>>()
          .map((station) => station['name'] as String? ?? '')
          .where((name) => name.isNotEmpty)
          .toList(growable: false),
    );
  }
}

class KitchenStockTransaction {
  const KitchenStockTransaction({
    required this.type,
    required this.quantity,
    required this.quantityDelta,
    required this.stockBefore,
    required this.stockAfter,
    required this.reason,
    required this.user,
    required this.createdAt,
  });

  final String type;
  final int quantity;
  final int quantityDelta;
  final int stockBefore;
  final int stockAfter;
  final String reason;
  final String user;
  final DateTime? createdAt;

  factory KitchenStockTransaction.fromJson(Map<String, dynamic> json) =>
      KitchenStockTransaction(
        type: json['type'] as String? ?? 'adjustment',
        quantity: (json['quantity'] as num?)?.toInt() ?? 0,
        quantityDelta: (json['quantity_delta'] as num?)?.toInt() ?? 0,
        stockBefore: (json['stock_before'] as num?)?.toInt() ?? 0,
        stockAfter: (json['stock_after'] as num?)?.toInt() ?? 0,
        reason: json['reason'] as String? ?? '',
        user: json['user'] as String? ?? 'Staff',
        createdAt: DateTime.tryParse(json['created_at'] as String? ?? '')
            ?.toLocal(),
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
    this.cancelledQuantity = 0,
    required this.status,
    required this.note,
    required this.foodType,
  });

  final String name;
  final int quantity;
  final int cancelledQuantity;
  final String status;
  final String? note;
  final String? foodType;

  factory KotDish.fromJson(Map<String, dynamic> json) {
    final menuItem = json['menu_item'] as Map<String, dynamic>? ?? const {};
    final menuType = menuItem['menu_type'] as Map<String, dynamic>? ?? const {};
    return KotDish(
      name: menuItem['name'] as String? ?? 'Dish',
      quantity: (json['quantity'] as num?)?.toInt() ?? 1,
      cancelledQuantity: (json['cancelled_quantity'] as num?)?.toInt() ?? 0,
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
    required this.orderStatus,
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
  final String orderStatus;
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
      orderStatus: order['status'] as String? ?? 'active',
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
