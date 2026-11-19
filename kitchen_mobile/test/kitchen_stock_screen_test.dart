import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:nagarjuna_kitchen/core/api_client.dart';
import 'package:nagarjuna_kitchen/core/secure_token_store.dart';
import 'package:nagarjuna_kitchen/data/kitchen_repository.dart';
import 'package:nagarjuna_kitchen/domain/kitchen_models.dart';
import 'package:nagarjuna_kitchen/presentation/kitchen_stock_screen.dart';

void main() {
  test('explicit stock permissions control access independently', () {
    final viewer = KitchenAccount.fromJson({
      'can_view_stock': true,
      'can_manage_stock': false,
    });
    expect(viewer.canViewStock, isTrue);
    expect(viewer.canManageStock, isFalse);
    final hidden = KitchenAccount.fromJson({
      'can_view_stock': false,
      'can_manage_stock': true,
    });
    expect(hidden.canViewStock, isFalse);
    final manager = KitchenAccount.fromJson({
      'can_view_stock': true,
      'can_manage_stock': true,
    });
    expect(manager.canViewStock, isTrue);
    expect(manager.canManageStock, isTrue);
  });

  testWidgets('stock viewers can read history without mutation controls', (
    tester,
  ) async {
    final requests = <http.Request>[];
    final repository = _repository((request) async {
      requests.add(request);
      if (request.url.path.endsWith('/transactions')) {
        return _response({
          'transactions': [_transaction(1)],
          'pagination': _pagination(page: 1, total: 1),
        });
      }
      return _response({
        'items': [_stockItem()],
        'pagination': _pagination(page: 1, total: 1),
      });
    });
    final account = KitchenAccount.fromJson({
      'name': 'Stock viewer',
      'can_view_stock': true,
      'can_manage_stock': false,
    });
    expect(account.canViewStock, isTrue);
    expect(account.canManageStock, isFalse);

    await _openStock(
      tester,
      repository,
      canManageStock: account.canManageStock,
    );

    expect(find.text('Chicken Biryani'), findsOneWidget);
    for (final label in ['Stock in', 'Wastage', 'Damaged', 'Correction']) {
      expect(find.text(label), findsNothing);
    }
    final availability = tester.widget<DropdownButton<String>>(
      find.byType(DropdownButton<String>),
    );
    expect(availability.onChanged, isNull);

    await tester.tap(find.byTooltip('Stock history'));
    await tester.pumpAndSettle();
    expect(find.text('Movement 1'), findsOneWidget);
    expect(requests.map((request) => request.method), everyElement('GET'));
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'history reaches movements beyond fifty and returns to page one',
    (tester) async {
      final historyPages = <int>[];
      final repository = _repository((request) async {
        if (request.url.path.endsWith('/transactions')) {
          final page = int.parse(request.url.queryParameters['page']!);
          historyPages.add(page);
          return _response({
            'transactions': page == 1
                ? [for (var id = 51; id >= 2; id--) _transaction(id)]
                : [_transaction(1)],
            'pagination': _pagination(page: page, total: 51),
          });
        }
        return _response({
          'items': [_stockItem()],
          'pagination': _pagination(page: 1, total: 1),
        });
      });
      await _openStock(tester, repository);
      await tester.tap(find.byTooltip('Stock history'));
      await tester.pumpAndSettle();

      expect(find.text('Movement 51'), findsOneWidget);
      expect(find.text('Movement 1'), findsNothing);
      await _tapDialogPage(tester, 'Next page');
      expect(find.text('Movement 1'), findsOneWidget);
      expect(find.text('Movement 51'), findsNothing);
      expect(historyPages, [1, 2]);
      expect(
        tester
            .widget<IconButton>(
              find.widgetWithIcon(IconButton, Icons.chevron_right_rounded),
            )
            .onPressed,
        isNull,
      );

      await _tapDialogPage(tester, 'Previous page');
      expect(find.text('Movement 51'), findsOneWidget);
      expect(historyPages, [1, 2, 1]);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('stock in requires a reason and shows the saved portion count', (
    tester,
  ) async {
    final movements = <Map<String, dynamic>>[];
    final repository = _repository((request) async {
      if (request.method == 'POST') {
        movements.add(jsonDecode(request.body) as Map<String, dynamic>);
        return _response({
          'item': {..._stockItem(), 'prepared_stock_quantity': 15},
        });
      }
      return _response({
        'items': [_stockItem()],
        'pagination': _pagination(page: 1, total: 1),
      });
    });
    await _openStock(tester, repository, canManageStock: true);
    await tester.tap(find.text('Stock in'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Save movement'));
    await tester.pumpAndSettle();
    expect(movements, isEmpty);
    expect(
      find.text('Enter a reason with at least 3 characters.'),
      findsOneWidget,
    );
    await tester.enterText(find.byType(TextFormField).at(0), '5');
    await tester.enterText(find.byType(TextFormField).at(1), 'Fresh batch');
    await tester.ensureVisible(find.text('Save movement'));
    await tester.tap(find.text('Save movement'));
    await tester.pumpAndSettle();
    expect(movements, [
      {'type': 'stock_in', 'quantity': 5, 'reason': 'Fresh batch'},
    ]);
    expect(find.text('15 portions'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('cancelling an availability change preserves its saved value', (
    tester,
  ) async {
    final methods = <String>[];
    final repository = _repository((request) async {
      methods.add(request.method);
      return _response({
        'items': [_stockItem()],
        'pagination': _pagination(page: 1, total: 1),
      });
    });
    await _openStock(tester, repository, canManageStock: true);
    await tester.tap(find.text('Available'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Sold out').last);
    await tester.pumpAndSettle();
    expect(find.text('Why is availability changing?'), findsOneWidget);
    await tester.tap(find.text('Back'));
    await tester.pumpAndSettle();
    expect(methods, ['GET']);
    expect(
      tester
          .widget<DropdownButton<String>>(find.byType(DropdownButton<String>))
          .value,
      'available',
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('prepared dishes load the selected stock page', (tester) async {
    final stockPages = <int>[];
    final repository = _repository((request) async {
      final page = int.parse(request.url.queryParameters['page']!);
      stockPages.add(page);
      return _response({
        'items': [_stockItem(id: page, name: 'Dish page $page')],
        'pagination': _pagination(page: page, total: 51),
      });
    });
    await _openStock(tester, repository);
    expect(find.text('Dish page 1'), findsOneWidget);
    await tester.tap(find.byTooltip('Next page'));
    await tester.pumpAndSettle();
    expect(find.text('Dish page 2'), findsOneWidget);
    expect(find.text('Dish page 1'), findsNothing);
    await tester.tap(find.byTooltip('Previous page'));
    await tester.pumpAndSettle();
    expect(find.text('Dish page 1'), findsOneWidget);
    expect(stockPages, [1, 2, 1]);
    expect(tester.takeException(), isNull);
  });

  testWidgets('stock returns to the last page when the list shrinks', (
    tester,
  ) async {
    final stockPages = <int>[];
    final repository = _repository((request) async {
      final page = int.parse(request.url.queryParameters['page']!);
      stockPages.add(page);
      final shrunk = stockPages.length > 1;
      return _response({
        'items': page == 1 ? [_stockItem()] : [],
        'pagination': _pagination(page: page, total: shrunk ? 1 : 51),
      });
    });
    await _openStock(tester, repository);
    await tester.tap(find.byTooltip('Next page'));
    await tester.pumpAndSettle();
    expect(stockPages, [1, 2, 1]);
    expect(find.text('Chicken Biryani'), findsOneWidget);
    expect(
      find.text('No active dishes are mapped to your assigned kitchens.'),
      findsNothing,
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'failed history page can retry without losing the requested page',
    (tester) async {
      var secondPageAttempts = 0;
      final repository = _repository((request) async {
        if (request.url.path.endsWith('/transactions')) {
          final page = int.parse(request.url.queryParameters['page']!);
          if (page == 2 && ++secondPageAttempts == 1) {
            return http.Response(
              jsonEncode({'message': 'Connection lost'}),
              503,
            );
          }
          return _response({
            'transactions': [_transaction(page == 1 ? 51 : 1)],
            'pagination': _pagination(page: page, total: 51),
          });
        }
        return _response({
          'items': [_stockItem()],
          'pagination': _pagination(page: 1, total: 1),
        });
      });
      await _openStock(tester, repository);
      await tester.tap(find.byTooltip('Stock history'));
      await tester.pumpAndSettle();
      await _tapDialogPage(tester, 'Next page');
      expect(find.text('Connection lost'), findsOneWidget);
      await tester.tap(find.text('Retry'));
      await tester.pumpAndSettle();
      expect(find.text('Movement 1'), findsOneWidget);
      expect(secondPageAttempts, 2);
      expect(tester.takeException(), isNull);
    },
  );
}

KitchenRepository _repository(
  Future<http.Response> Function(http.Request) respond,
) => KitchenRepository(
  api: KitchenApiClient(
    baseUrl: 'http://localhost/api/v1',
    readToken: () async => 'test-kitchen-token',
    client: MockClient(respond),
  ),
  tokens: KitchenTokenStore(),
);

Future<void> _openStock(
  WidgetTester tester,
  KitchenRepository repository, {
  bool canManageStock = false,
}) async {
  tester.view.physicalSize = const Size(430, 900);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
  await tester.pumpWidget(
    MaterialApp(
      home: KitchenStockScreen(
        repository: repository,
        canManageStock: canManageStock,
        onBack: () {},
      ),
    ),
  );
  await tester.pumpAndSettle();
}

Future<void> _tapDialogPage(WidgetTester tester, String tooltip) async {
  await tester.ensureVisible(find.byTooltip(tooltip));
  await tester.tap(find.byTooltip(tooltip));
  await tester.pumpAndSettle();
}

Map<String, Object?> _stockItem({
  int id = 1,
  String name = 'Chicken Biryani',
}) => {
  'id': id,
  'name': name,
  'code': 'DISH-$id',
  'stock_tracking_enabled': true,
  'prepared_stock_quantity': 10,
  'availability': 'available',
  'kitchens': [
    {'name': 'Biryani Kitchen'},
  ],
};

Map<String, Object?> _transaction(int id) => {
  'id': id,
  'type': 'stock_in',
  'quantity': 1,
  'quantity_delta': 1,
  'stock_before': id - 1,
  'stock_after': id,
  'reason': 'Movement $id',
  'user': 'Kitchen staff',
  'created_at': '2026-11-17T10:00:00Z',
};

Map<String, int> _pagination({required int page, required int total}) => {
  'current_page': page,
  'last_page': (total / 50).ceil(),
  'per_page': 50,
  'total': total,
};

http.Response _response(Map<String, Object?> data) =>
    http.Response(jsonEncode({'data': data}), 200);
