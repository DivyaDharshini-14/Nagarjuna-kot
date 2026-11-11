import '../../../core/network/mobile_api_client.dart';
import '../domain/menu.dart';

abstract interface class MenuRepository {
  Future<MenuSnapshot> loadMenu();
}

class ApiMenuRepository implements MenuRepository {
  const ApiMenuRepository({required this.api});

  final MobileApiClient api;

  @override
  Future<MenuSnapshot> loadMenu() async {
    final response = await api.get('menu');
    final data = _map(response['data'], 'menu data');
    final branch = _map(data['branch'], 'branch');
    final categoryValues = data['categories'];
    if (categoryValues is! List) {
      throw const FormatException('The menu categories response is invalid.');
    }

    final categories = categoryValues
        .map((value) {
          final category = _map(value, 'category');
          final itemValues = category['items'];
          if (itemValues is! List) {
            throw const FormatException('The menu dishes response is invalid.');
          }
          return MenuCategory(
            id: _int(category['id'], 'category id'),
            name: _string(category['name'], fallback: 'Menu'),
            dishes: itemValues.map(_dish).toList(growable: false),
          );
        })
        .toList(growable: false);

    return MenuSnapshot(
      branchId: _int(branch['id'], 'branch id'),
      branchName: _string(branch['name'], fallback: 'Assigned branch'),
      categories: categories,
    );
  }

  MenuDish _dish(Object? value) {
    final item = _map(value, 'dish');
    final type = _map(item['menu_type'], 'menu type');
    return MenuDish(
      id: _int(item['id'], 'dish id'),
      categoryId: _int(item['category_id'], 'dish category id'),
      name: _string(item['name'], fallback: 'Unnamed dish'),
      code: _string(item['code']),
      description: item['description'] is String
          ? item['description'] as String
          : null,
      imagePath: item['image_path'] is String
          ? item['image_path'] as String
          : null,
      price: _double(item['selling_price'], 'selling price'),
      menuType: _string(type['name'], fallback: 'Menu item'),
      availability: menuAvailabilityFromValue(_string(item['availability'])),
      stockTrackingEnabled: item['stock_tracking_enabled'] == true,
      preparedStockQuantity: _nullableInt(item['prepared_stock_quantity']),
      stockConfigured: item['stock_configured'] == true,
      isOrderable: item['is_orderable'] == true,
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

  double _double(Object? value, String label) {
    if (value is num) return value.toDouble();
    if (value is String) {
      final parsed = double.tryParse(value);
      if (parsed != null) return parsed;
    }
    throw FormatException('The $label value is invalid.');
  }
}
