import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nagarjuna_kitchen/domain/kitchen_models.dart';
import 'package:nagarjuna_kitchen/presentation/kitchen_board_screen.dart';

void main() {
  testWidgets('active ticket marks a dish ready by item ID', (tester) async {
    final ticket = KotTicket.fromJson({
      'id': 5,
      'kitchen_id': 1,
      'ticket_status': 'accepted',
      'order': {'status': 'partially_ready'},
      'items': [
        {
          'id': 7,
          'quantity': 2,
          'item_status': 'accepted',
          'menu_item': {'name': 'Mutton Fry', 'short_code': 'MF'},
        },
      ],
    });
    int? markedItem;
    await tester.pumpWidget(
      MaterialApp(
        home: KitchenBoardScreen(
          account: const KitchenAccount(name: 'Cook', branchName: 'Test'),
          kitchens: const [KitchenStation(id: 1, name: 'Main', code: 'K')],
          selectedKitchen: null,
          tickets: [ticket],
          busyTicketId: null,
          hasSnapshot: true,
          isRefreshing: false,
          error: null,
          boardView: 'active',
          onRefresh: () async {},
          onKitchenChanged: (_) async {},
          onBoardViewChanged: (_) async {},
          onAcceptTicket: (_) async {},
          onItemReady: (_, dish) async => markedItem = dish.id,
          onSignOut: () async {},
          currentPage: 1,
          lastPage: 1,
          historyMayBeLimited: false,
          onPageChanged: (_) {},
        ),
      ),
    );

    expect(find.text('PARTIALLY READY'), findsOneWidget);
    expect(find.text('2× MF'), findsOneWidget);
    expect(find.textContaining('Mutton Fry'), findsNothing);
    expect(find.text('Preparing'), findsNothing);
    expect(find.text('Ready'), findsNothing);

    final markReady = find.text('Mark ready');
    await tester.ensureVisible(markReady);
    await tester.tap(markReady);
    expect(markedItem, 7);
  });
}
