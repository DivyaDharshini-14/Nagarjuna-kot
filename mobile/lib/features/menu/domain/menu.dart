enum MenuAvailability { available, temporarilyUnavailable, soldOut, unknown }

MenuAvailability menuAvailabilityFromValue(String value) {
  return switch (value.trim().toLowerCase()) {
    'available' => MenuAvailability.available,
    'temporarily_unavailable' => MenuAvailability.temporarilyUnavailable,
    'sold_out' => MenuAvailability.soldOut,
    _ => MenuAvailability.unknown,
  };
}

extension MenuAvailabilityLabel on MenuAvailability {
  String get label => switch (this) {
    MenuAvailability.available => 'Available',
    MenuAvailability.temporarilyUnavailable => 'Temporarily unavailable',
    MenuAvailability.soldOut => 'Sold out',
    MenuAvailability.unknown => 'Availability unknown',
  };
}

class MenuDish {
  const MenuDish({
    required this.id,
    required this.categoryId,
    required this.name,
    required this.code,
    required this.price,
    required this.menuType,
    required this.availability,
    required this.stockTrackingEnabled,
    required this.stockConfigured,
    required this.isOrderable,
    this.description,
    this.imagePath,
    this.preparedStockQuantity,
  });

  final int id;
  final int categoryId;
  final String name;
  final String code;
  final double price;
  final String menuType;
  final MenuAvailability availability;
  final bool stockTrackingEnabled;
  final bool stockConfigured;
  final bool isOrderable;
  final String? description;
  final String? imagePath;
  final int? preparedStockQuantity;

  int get maximumQuantity {
    if (!stockTrackingEnabled) return 99;
    final quantity = preparedStockQuantity ?? 0;
    return quantity < 0
        ? 0
        : quantity > 99
        ? 99
        : quantity;
  }

  bool get isVegetarian {
    final type = menuType.toLowerCase();
    return type.contains('veg') && !type.contains('non');
  }
}

class MenuCategory {
  const MenuCategory({
    required this.id,
    required this.name,
    required this.dishes,
  });

  final int id;
  final String name;
  final List<MenuDish> dishes;
}

class MenuSnapshot {
  const MenuSnapshot({required this.branchName, required this.categories});

  final String branchName;
  final List<MenuCategory> categories;

  List<MenuDish> get dishes =>
      categories.expand((category) => category.dishes).toList();
}

class CartLine {
  const CartLine({required this.dish, required this.quantity, this.note = ''});

  final MenuDish dish;
  final int quantity;
  final String note;

  CartLine copyWith({MenuDish? dish, int? quantity, String? note}) {
    return CartLine(
      dish: dish ?? this.dish,
      quantity: quantity ?? this.quantity,
      note: note ?? this.note,
    );
  }

  double get total => dish.price * quantity;
}

class CartDraft {
  const CartDraft({
    required this.lines,
    required this.orderInstructions,
    required this.guestCount,
  });

  final List<CartLine> lines;
  final String orderInstructions;
  final int guestCount;
}
