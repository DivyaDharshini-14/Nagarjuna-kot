enum TableStatus { available, occupied, reserved, cleaning, inactive, unknown }

TableStatus tableStatusFromValue(String value, {required bool isActive}) {
  if (!isActive) return TableStatus.inactive;

  return switch (value.trim().toLowerCase()) {
    'available' => TableStatus.available,
    'occupied' => TableStatus.occupied,
    'reserved' => TableStatus.reserved,
    'cleaning' => TableStatus.cleaning,
    _ => TableStatus.unknown,
  };
}

extension TableStatusLabel on TableStatus {
  String get label => switch (this) {
    TableStatus.available => 'Available',
    TableStatus.occupied => 'Occupied',
    TableStatus.reserved => 'Reserved',
    TableStatus.cleaning => 'Cleaning',
    TableStatus.inactive => 'Inactive',
    TableStatus.unknown => 'Status unavailable',
  };
}

class RestaurantTable {
  const RestaurantTable({
    required this.id,
    required this.name,
    required this.seatingCapacity,
    required this.status,
    this.activeOrderId,
    this.activeOrderNumber,
  });

  final int id;
  final String name;
  final int seatingCapacity;
  final TableStatus status;
  final int? activeOrderId;
  final String? activeOrderNumber;

  bool get canOpen =>
      status == TableStatus.available ||
      (status == TableStatus.occupied && activeOrderId != null);

  bool get isOccupiedWithActiveOrder =>
      status == TableStatus.occupied && activeOrderId != null;
}

class RestaurantFloor {
  const RestaurantFloor({
    required this.id,
    required this.name,
    required this.code,
    required this.tables,
  });

  final int id;
  final String name;
  final String code;
  final List<RestaurantTable> tables;
}

class FloorTableSnapshot {
  const FloorTableSnapshot({required this.branchName, required this.floors});

  final String branchName;
  final List<RestaurantFloor> floors;
}
