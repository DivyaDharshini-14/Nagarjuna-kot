import '../../../core/network/mobile_api_client.dart';
import '../domain/floor_table.dart';

abstract interface class FloorTableRepository {
  /// Returns only floors and tables assigned to the authenticated staff member.
  Future<FloorTableSnapshot> loadAssignedFloorTables();
}

class ApiFloorTableRepository implements FloorTableRepository {
  const ApiFloorTableRepository({required this.api});

  final MobileApiClient api;

  @override
  Future<FloorTableSnapshot> loadAssignedFloorTables() async {
    final response = await api.get('floors');
    final data = _map(response['data'], 'data');
    final branch = _map(data['branch'], 'branch');
    final floorsValue = data['floors'];
    if (floorsValue is! List) {
      throw const FormatException('The floors response is invalid.');
    }

    final floors = floorsValue
        .map((value) {
          final floor = _map(value, 'floor');
          final tablesValue = floor['tables'];
          if (tablesValue is! List) {
            throw const FormatException(
              'The floor tables response is invalid.',
            );
          }
          return RestaurantFloor(
            id: _int(floor['id'], 'floor id'),
            name: _string(floor['name'], fallback: 'Unnamed floor'),
            code: _string(floor['code']),
            tables: tablesValue.map(_tableFromJson).toList(growable: false),
          );
        })
        .toList(growable: false);

    return FloorTableSnapshot(
      branchName: _string(branch['name'], fallback: 'Assigned branch'),
      floors: floors,
    );
  }

  RestaurantTable _tableFromJson(Object? value) {
    final table = _map(value, 'table');
    final status = _string(table['status']);
    return RestaurantTable(
      id: _int(table['id'], 'table id'),
      name: _string(table['name'], fallback: 'Unnamed table'),
      seatingCapacity: _int(table['seating_capacity'], 'table capacity'),
      status: tableStatusFromValue(status, isActive: true),
      activeOrderId: _nullableInt(table['active_order_id']),
      activeOrderNumber: table['active_order_number'] is String
          ? table['active_order_number'] as String
          : null,
    );
  }

  Map<String, dynamic> _map(Object? value, String label) {
    if (value is Map<String, dynamic>) return value;
    throw FormatException('The $label response is invalid.');
  }

  String _string(Object? value, {String fallback = ''}) {
    return value is String && value.trim().isNotEmpty ? value : fallback;
  }

  int _int(Object? value, String label) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    if (value is String) {
      final parsed = int.tryParse(value);
      if (parsed != null) return parsed;
    }
    throw FormatException('The $label value is invalid.');
  }

  int? _nullableInt(Object? value) {
    if (value == null) return null;
    if (value is int) return value;
    if (value is num) return value.toInt();
    return value is String ? int.tryParse(value) : null;
  }
}
