import 'package:flutter/material.dart';

import '../app/kitchen_theme.dart';
import '../domain/kitchen_models.dart';

class KitchenBoardScreen extends StatefulWidget {
  const KitchenBoardScreen({
    super.key,
    required this.account,
    required this.kitchens,
    required this.selectedKitchen,
    required this.tickets,
    required this.busyTicketId,
    required this.isLoading,
    required this.isRefreshing,
    required this.error,
    required this.refreshedAt,
    required this.boardView,
    required this.liveConfigured,
    required this.liveConnected,
    required this.onRefresh,
    required this.onKitchenChanged,
    required this.onBoardViewChanged,
    required this.onStatusChange,
    required this.onSignOut,
    this.onStockControl,
  });

  final KitchenAccount account;
  final List<KitchenStation> kitchens;
  final KitchenStation? selectedKitchen;
  final List<KotTicket> tickets;
  final String? busyTicketId;
  final bool isLoading;
  final bool isRefreshing;
  final String? error;
  final DateTime? refreshedAt;
  final String boardView;
  final bool liveConfigured;
  final bool liveConnected;
  final Future<void> Function() onRefresh;
  final Future<void> Function(KitchenStation? kitchen) onKitchenChanged;
  final Future<void> Function(String view) onBoardViewChanged;
  final Future<void> Function(KotTicket ticket, String status) onStatusChange;
  final Future<void> Function() onSignOut;
  final VoidCallback? onStockControl;

  @override
  State<KitchenBoardScreen> createState() => _KitchenBoardScreenState();
}

class _KitchenBoardScreenState extends State<KitchenBoardScreen> {
  String _filter = 'All';

  @override
  void didUpdateWidget(covariant KitchenBoardScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.boardView != widget.boardView) {
      if (widget.boardView == 'completed') {
        _filter = 'Completed';
      } else if (widget.boardView == 'cancelled') {
        _filter = 'Cancelled';
      } else if (_filter == 'Completed' || _filter == 'Cancelled') {
        _filter = 'All';
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final newCount = widget.tickets.where((t) => t.status == 'new').length;
    final preparingCount = widget.tickets
        .where((t) => t.status == 'accepted' || t.status == 'preparing')
        .length;
    final readyCount = widget.tickets.where((t) => t.status == 'ready').length;
    final visibleTickets = widget.tickets.where(_matchesFilter).toList();

    return Scaffold(
      appBar: AppBar(
        toolbarHeight: 70,
        titleSpacing: 18,
        title: Row(
          children: [
            Container(
              width: 38,
              height: 38,
              decoration: BoxDecoration(
                color: KitchenColors.green,
                borderRadius: BorderRadius.circular(12),
              ),
              alignment: Alignment.center,
              child: const Text(
                'N',
                style: TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w900,
                  fontSize: 19,
                ),
              ),
            ),
            const SizedBox(width: 11),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    widget.selectedKitchen?.name ?? 'All assigned kitchens',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  Text(
                    widget.account.branchName.toUpperCase(),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: KitchenColors.muted,
                      fontSize: 9,
                      letterSpacing: 1,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
        actions: [
          if (widget.onStockControl != null)
            IconButton(
              tooltip: 'Prepared stock and availability',
              onPressed: widget.onStockControl,
              icon: const Icon(Icons.inventory_2_outlined),
            ),
          IconButton(
            tooltip: 'Refresh tickets',
            onPressed: widget.isRefreshing ? null : widget.onRefresh,
            icon: widget.isRefreshing
                ? const SizedBox.square(
                    dimension: 19,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.refresh_rounded),
          ),
          PopupMenuButton<String>(
            tooltip: 'Account options',
            icon: const Icon(Icons.more_vert_rounded),
            onSelected: (value) {
              if (value == 'signout') _confirmSignOut(context);
            },
            itemBuilder: (context) => const [
              PopupMenuItem(
                value: 'signout',
                child: Row(
                  children: [
                    Icon(Icons.logout_rounded, size: 19),
                    SizedBox(width: 10),
                    Text('Sign out'),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(width: 6),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: widget.onRefresh,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(18, 13, 18, 30),
          children: [
            Text(
              'Kitchen board',
              style: Theme.of(context).textTheme.headlineMedium,
            ),
            const SizedBox(height: 5),
            Text(switch (_filter) {
              'Completed' =>
                'Recently completed orders from your assigned kitchens.',
              'Cancelled' => 'Orders and dishes with recorded cancellations.',
              _ => 'Track incoming KOTs and move dishes through preparation.',
            }, style: Theme.of(context).textTheme.bodyMedium),
            const SizedBox(height: 18),
            _stationPicker(),
            const SizedBox(height: 10),
            _LiveStatus(
              configured: widget.liveConfigured,
              connected: widget.liveConnected,
            ),
            const SizedBox(height: 15),
            Row(
              children: [
                Expanded(
                  child: _SummaryTile(
                    label: 'NEW',
                    count: newCount,
                    color: KitchenColors.orange,
                    icon: Icons.notifications_active_outlined,
                  ),
                ),
                const SizedBox(width: 9),
                Expanded(
                  child: _SummaryTile(
                    label: 'PREPARING',
                    count: preparingCount,
                    color: KitchenColors.amber,
                    icon: Icons.soup_kitchen_outlined,
                  ),
                ),
                const SizedBox(width: 9),
                Expanded(
                  child: _SummaryTile(
                    label: 'READY',
                    count: readyCount,
                    color: KitchenColors.green,
                    icon: Icons.task_alt_rounded,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 20),
            _filterBar(),
            const SizedBox(height: 10),
            if (widget.error != null) _ConnectionBanner(message: widget.error!),
            if (widget.refreshedAt != null)
              Padding(
                padding: const EdgeInsets.only(bottom: 10, left: 3),
                child: Text(
                  'Updated ${_timeLabel(widget.refreshedAt!)}',
                  style: const TextStyle(
                    color: KitchenColors.muted,
                    fontSize: 11,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            if (widget.isLoading)
              const Padding(
                padding: EdgeInsets.only(top: 55),
                child: Center(child: CircularProgressIndicator()),
              )
            else if (widget.kitchens.isEmpty)
              const _EmptyState(
                icon: Icons.store_mall_directory_outlined,
                title: 'No kitchen stations yet',
                message: 'Ask a manager to activate a kitchen station for your branch.',
              )
            else if (visibleTickets.isEmpty)
              _EmptyState(
                icon: _filter == 'New'
                    ? Icons.mark_email_read_outlined
                    : Icons.restaurant_menu_rounded,
                title: _emptyTitle,
                message: _emptyMessage,
              )
            else
              ...visibleTickets.map(
                (ticket) => Padding(
                  padding: const EdgeInsets.only(bottom: 13),
                  child: _TicketCard(
                    ticket: ticket,
                    isBusy: widget.busyTicketId == '${ticket.id}',
                    onStatusChange: (status) =>
                        widget.onStatusChange(ticket, status),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _stationPicker() {
    if (widget.kitchens.length <= 1) {
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        decoration: BoxDecoration(
          color: KitchenColors.card,
          borderRadius: BorderRadius.circular(15),
          border: Border.all(color: KitchenColors.line),
        ),
        child: Row(
          children: [
            const Icon(
              Icons.restaurant_rounded,
              color: KitchenColors.green,
              size: 20,
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                widget.selectedKitchen?.name ?? 'All assigned stations',
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
            ),
            Text(
              widget.selectedKitchen == null
                  ? 'ALL ASSIGNED'
                  : 'ACTIVE STATION',
              style: TextStyle(
                color: KitchenColors.muted,
                fontSize: 9,
                letterSpacing: 0.7,
                fontWeight: FontWeight.w800,
              ),
            ),
          ],
        ),
      );
    }
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Padding(
          padding: EdgeInsets.only(left: 2, bottom: 9),
          child: Text(
            'KITCHEN STATIONS',
            style: TextStyle(
              color: KitchenColors.muted,
              fontSize: 10,
              letterSpacing: 1,
              fontWeight: FontWeight.w900,
            ),
          ),
        ),
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          child: Row(
            children: [
              Padding(
                padding: const EdgeInsets.only(right: 8),
                child: ChoiceChip(
                  selected: widget.selectedKitchen == null,
                  label: const Text('All assigned'),
                  avatar: Icon(
                    Icons.restaurant_menu_rounded,
                    size: 16,
                    color: widget.selectedKitchen == null
                        ? Colors.white
                        : KitchenColors.green,
                  ),
                  onSelected: (_) => widget.onKitchenChanged(null),
                  selectedColor: KitchenColors.green,
                  backgroundColor: KitchenColors.card,
                  labelStyle: TextStyle(
                    color: widget.selectedKitchen == null
                        ? Colors.white
                        : KitchenColors.ink,
                    fontWeight: FontWeight.w700,
                  ),
                  side: BorderSide(
                    color: widget.selectedKitchen == null
                        ? KitchenColors.green
                        : KitchenColors.line,
                  ),
                  showCheckmark: false,
                ),
              ),
              ...widget.kitchens.map((kitchen) {
                final selected = kitchen.id == widget.selectedKitchen?.id;
                return Padding(
                  padding: const EdgeInsets.only(right: 8),
                  child: ChoiceChip(
                    selected: selected,
                    label: Text(kitchen.name),
                    avatar: Icon(
                      Icons.restaurant_rounded,
                      size: 16,
                      color: selected ? Colors.white : KitchenColors.green,
                    ),
                    onSelected: (_) => widget.onKitchenChanged(kitchen),
                    selectedColor: KitchenColors.green,
                    backgroundColor: KitchenColors.card,
                    labelStyle: TextStyle(
                      color: selected ? Colors.white : KitchenColors.ink,
                      fontWeight: FontWeight.w700,
                    ),
                    side: BorderSide(
                      color: selected
                          ? KitchenColors.green
                          : KitchenColors.line,
                    ),
                    showCheckmark: false,
                  ),
                );
              }),
            ],
          ),
        ),
      ],
    );
  }

  Widget _filterBar() => SingleChildScrollView(
    scrollDirection: Axis.horizontal,
    child: Row(
      children:
          [
            'All',
            'New',
            'Accepted',
            'Preparing',
            'Ready',
            'Cancelled',
            'Completed',
          ].map((filter) {
            final selected = filter == _filter;
            return Padding(
              padding: const EdgeInsets.only(right: 8),
              child: ChoiceChip(
                label: Text(filter),
                selected: selected,
                showCheckmark: false,
                onSelected: (_) {
                  setState(() => _filter = filter);
                  final view = switch (filter) {
                    'Completed' => 'completed',
                    'Cancelled' => 'cancelled',
                    _ => 'active',
                  };
                  widget.onBoardViewChanged(view);
                },
                selectedColor: KitchenColors.ink,
                backgroundColor: Colors.transparent,
                side: BorderSide(
                  color: selected ? KitchenColors.ink : KitchenColors.line,
                ),
                labelStyle: TextStyle(
                  color: selected ? Colors.white : KitchenColors.ink,
                  fontWeight: FontWeight.w700,
                ),
              ),
            );
          }).toList(),
    ),
  );

  bool _matchesFilter(KotTicket ticket) => switch (_filter) {
    'New' => ticket.status == 'new',
    'Accepted' => ticket.status == 'accepted',
    'Preparing' => ticket.status == 'preparing',
    'Ready' => ticket.status == 'ready',
    'Cancelled' =>
      ticket.status == 'cancelled' ||
          ticket.dishes.any((dish) => dish.cancelledQuantity > 0),
    'Completed' =>
      ticket.orderStatus == 'completed' || ticket.status == 'served',
    _ => true,
  };

  String get _emptyTitle => switch (_filter) {
    'New' => 'No new tickets',
    'Accepted' => 'No accepted tickets',
    'Preparing' => 'Nothing in preparation',
    'Ready' => 'No ready tickets',
    'Cancelled' => 'No cancelled items',
    'Completed' => 'No completed orders',
    _ => 'All caught up',
  };

  String get _emptyMessage => switch (_filter) {
    'New' => 'New kitchen orders will appear here automatically.',
    'Accepted' => 'New tickets will appear here after a kitchen accepts them.',
    'Preparing' => 'Tickets in preparation will appear here.',
    'Ready' => 'Tickets marked ready will be shown in this queue.',
    'Cancelled' => 'Cancellation updates for this kitchen will appear here.',
    'Completed' => 'Orders completed by the waiter will appear here.',
    _ => 'There are no active KOT tickets for your assigned kitchen stations.',
  };

  Future<void> _confirmSignOut(BuildContext context) async {
    final shouldSignOut = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Sign out?'),
        content: const Text(
          'You will need your staff credentials to sign in again.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Sign out'),
          ),
        ],
      ),
    );
    if (shouldSignOut == true) await widget.onSignOut();
  }
}

class _SummaryTile extends StatelessWidget {
  const _SummaryTile({
    required this.label,
    required this.count,
    required this.color,
    required this.icon,
  });

  final String label;
  final int count;
  final Color color;
  final IconData icon;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.fromLTRB(10, 12, 10, 11),
    decoration: BoxDecoration(
      color: KitchenColors.card,
      borderRadius: BorderRadius.circular(17),
      border: Border.all(color: KitchenColors.line),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Icon(icon, color: color, size: 16),
            const Spacer(),
            Text(
              '$count',
              style: TextStyle(
                color: color,
                fontSize: 19,
                fontWeight: FontWeight.w900,
              ),
            ),
          ],
        ),
        const SizedBox(height: 8),
        Text(
          label,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(
            color: KitchenColors.muted,
            fontSize: 9,
            letterSpacing: 0.55,
            fontWeight: FontWeight.w900,
          ),
        ),
      ],
    ),
  );
}

class _LiveStatus extends StatelessWidget {
  const _LiveStatus({required this.configured, required this.connected});

  final bool configured;
  final bool connected;

  @override
  Widget build(BuildContext context) {
    final color = !configured
        ? KitchenColors.muted
        : connected
        ? KitchenColors.green
        : const Color(0xFF9A6A17);
    final icon = !configured
        ? Icons.sync_rounded
        : connected
        ? Icons.wifi_rounded
        : Icons.wifi_off_rounded;
    final label = !configured
        ? 'Auto refresh · every 12 seconds'
        : connected
        ? 'Live kitchen updates connected'
        : 'Reconnecting · auto refresh is active';
    return Row(
      children: [
        Icon(icon, color: color, size: 15),
        const SizedBox(width: 6),
        Expanded(
          child: Text(
            label,
            style: TextStyle(
              color: color,
              fontSize: 11,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
      ],
    );
  }
}

class _TicketCard extends StatelessWidget {
  const _TicketCard({
    required this.ticket,
    required this.isBusy,
    required this.onStatusChange,
  });

  final KotTicket ticket;
  final bool isBusy;
  final ValueChanged<String> onStatusChange;

  @override
  Widget build(BuildContext context) {
    final color = _statusColor(ticket.status);
    final action = _nextAction(ticket.status);
    final elapsed = DateTime.now().difference(ticket.createdAt);
    final age = _durationLabel(elapsed);
    return Card(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Container(
            padding: const EdgeInsets.fromLTRB(16, 15, 16, 13),
            decoration: BoxDecoration(
              color: color.withValues(alpha: 0.08),
              borderRadius: const BorderRadius.vertical(
                top: Radius.circular(19),
              ),
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        ticket.number,
                        style: const TextStyle(
                          color: KitchenColors.ink,
                          fontSize: 18,
                          fontWeight: FontWeight.w900,
                          letterSpacing: -0.3,
                        ),
                      ),
                      const SizedBox(height: 5),
                      Wrap(
                        spacing: 7,
                        crossAxisAlignment: WrapCrossAlignment.center,
                        children: [
                          Text(
                            ticket.orderNumber,
                            style: const TextStyle(
                              color: KitchenColors.muted,
                              fontSize: 12,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          const Icon(
                            Icons.circle,
                            color: KitchenColors.muted,
                            size: 4,
                          ),
                          Text(
                            _tableLabel(ticket.tableName),
                            style: const TextStyle(
                              color: KitchenColors.ink,
                              fontSize: 12,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 8),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    _StatusPill(
                      label: _statusName(ticket.status),
                      color: color,
                    ),
                    const SizedBox(height: 7),
                    Row(
                      children: [
                        Icon(Icons.schedule_rounded, color: color, size: 13),
                        const SizedBox(width: 3),
                        Text(
                          age,
                          style: TextStyle(
                            color: color,
                            fontSize: 11,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 15),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                ...ticket.dishes.map((dish) => _DishRow(dish: dish)),
                if (ticket.kitchenNote != null &&
                    ticket.kitchenNote!.trim().isNotEmpty) ...[
                  const SizedBox(height: 5),
                  _NoteBanner(label: 'KITCHEN NOTE', text: ticket.kitchenNote!),
                ],
                if (ticket.orderNote != null &&
                    ticket.orderNote!.trim().isNotEmpty) ...[
                  const SizedBox(height: 5),
                  _NoteBanner(label: 'ORDER NOTE', text: ticket.orderNote!),
                ],
                if (action != null) ...[
                  const SizedBox(height: 13),
                  FilledButton.icon(
                    onPressed: isBusy
                        ? null
                        : () => onStatusChange(action.status),
                    icon: isBusy
                        ? const SizedBox.square(
                            dimension: 17,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: Colors.white,
                            ),
                          )
                        : Icon(action.icon),
                    label: Text(isBusy ? 'Updating ticket…' : action.label),
                    style: FilledButton.styleFrom(
                      backgroundColor: ticket.status == 'preparing'
                          ? KitchenColors.green
                          : KitchenColors.ink,
                    ),
                  ),
                ] else if (ticket.status == 'ready') ...[
                  const SizedBox(height: 13),
                  Container(
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    decoration: BoxDecoration(
                      color: const Color(0xFFEAF4EE),
                      borderRadius: BorderRadius.circular(13),
                    ),
                    child: const Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(
                          Icons.check_circle_rounded,
                          color: KitchenColors.green,
                          size: 18,
                        ),
                        SizedBox(width: 7),
                        Text(
                          'Ready for service',
                          style: TextStyle(
                            color: KitchenColors.greenDark,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _DishRow extends StatelessWidget {
  const _DishRow({required this.dish});
  final KotDish dish;

  @override
  Widget build(BuildContext context) {
    final foodColor = _foodTypeColor(dish.foodType);
    return Padding(
      padding: const EdgeInsets.only(bottom: 13),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 29,
            height: 29,
            decoration: BoxDecoration(
              color: const Color(0xFFF0EFE9),
              borderRadius: BorderRadius.circular(9),
            ),
            alignment: Alignment.center,
            child: Text(
              '${dish.quantity - dish.cancelledQuantity}×',
              style: const TextStyle(
                color: KitchenColors.ink,
                fontSize: 12,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
          const SizedBox(width: 10),
          Container(
            margin: const EdgeInsets.only(top: 5),
            width: 10,
            height: 10,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(3),
              border: Border.all(color: foodColor, width: 1.4),
            ),
            alignment: Alignment.center,
            child: Container(
              width: 4,
              height: 4,
              decoration: BoxDecoration(
                color: foodColor,
                shape: BoxShape.circle,
              ),
            ),
          ),
          const SizedBox(width: 9),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        dish.name,
                        style: const TextStyle(
                          color: KitchenColors.ink,
                          fontSize: 14,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                    const SizedBox(width: 7),
                    _StatusPill(
                      label: _statusName(dish.status),
                      color: _statusColor(dish.status),
                    ),
                  ],
                ),
                if (dish.cancelledQuantity > 0) ...[
                  const SizedBox(height: 3),
                  Text(
                    '${dish.cancelledQuantity} cancelled',
                    style: const TextStyle(
                      color: KitchenColors.red,
                      fontSize: 11,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
                if (dish.note != null && dish.note!.trim().isNotEmpty) ...[
                  const SizedBox(height: 3),
                  Text(
                    dish.note!,
                    style: const TextStyle(
                      color: KitchenColors.orange,
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                      height: 1.3,
                    ),
                  ),
                ],
              ],
            ),
          ),
          if (dish.foodType != null && dish.foodType!.isNotEmpty)
            Padding(
              padding: const EdgeInsets.only(left: 8, top: 2),
              child: Text(
                dish.foodType!.toUpperCase(),
                style: TextStyle(
                  color: foodColor,
                  fontSize: 8,
                  letterSpacing: 0.4,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _NoteBanner extends StatelessWidget {
  const _NoteBanner({required this.label, required this.text});

  final String label;
  final String text;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(11),
    decoration: BoxDecoration(
      color: const Color(0xFFFFF7E8),
      borderRadius: BorderRadius.circular(11),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Icon(
          Icons.sticky_note_2_outlined,
          size: 16,
          color: Color(0xFF9C6A13),
        ),
        const SizedBox(width: 7),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                label,
                style: const TextStyle(
                  color: Color(0xFF9C6A13),
                  fontSize: 9,
                  letterSpacing: 0.6,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                text,
                style: const TextStyle(
                  color: Color(0xFF76520F),
                  fontSize: 12,
                  height: 1.35,
                ),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

class _StatusPill extends StatelessWidget {
  const _StatusPill({required this.label, required this.color});
  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6),
    decoration: BoxDecoration(
      color: color.withValues(alpha: 0.14),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Text(
      label.toUpperCase(),
      style: TextStyle(
        color: color,
        fontSize: 9,
        letterSpacing: 0.55,
        fontWeight: FontWeight.w900,
      ),
    ),
  );
}

class _ConnectionBanner extends StatelessWidget {
  const _ConnectionBanner({required this.message});
  final String message;

  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.only(bottom: 10),
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: const Color(0xFFFFF0ED),
      borderRadius: BorderRadius.circular(12),
      border: Border.all(color: KitchenColors.red.withValues(alpha: 0.22)),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Icon(
          Icons.cloud_off_outlined,
          color: KitchenColors.red,
          size: 17,
        ),
        const SizedBox(width: 8),
        Expanded(
          child: Text(
            'Connection issue · $message',
            style: const TextStyle(
              color: KitchenColors.red,
              fontSize: 12,
              height: 1.35,
            ),
          ),
        ),
      ],
    ),
  );
}

class _EmptyState extends StatelessWidget {
  const _EmptyState({
    required this.icon,
    required this.title,
    required this.message,
  });

  final IconData icon;
  final String title;
  final String message;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(20, 55, 20, 40),
    child: Column(
      children: [
        Container(
          width: 68,
          height: 68,
          decoration: const BoxDecoration(
            color: Color(0xFFEAE9E3),
            shape: BoxShape.circle,
          ),
          alignment: Alignment.center,
          child: Icon(icon, color: KitchenColors.muted, size: 30),
        ),
        const SizedBox(height: 17),
        Text(
          title,
          textAlign: TextAlign.center,
          style: Theme.of(context).textTheme.titleMedium,
        ),
        const SizedBox(height: 6),
        Text(message, textAlign: TextAlign.center),
      ],
    ),
  );
}

class _TicketAction {
  const _TicketAction(this.label, this.status, this.icon);
  final String label;
  final String status;
  final IconData icon;
}

_TicketAction? _nextAction(String status) => switch (status) {
  'new' => const _TicketAction('Accept ticket', 'accepted', Icons.done_rounded),
  'accepted' => const _TicketAction(
    'Start preparing',
    'preparing',
    Icons.play_arrow_rounded,
  ),
  'preparing' => const _TicketAction(
    'Mark as ready',
    'ready',
    Icons.check_circle_outline_rounded,
  ),
  _ => null,
};

Color _statusColor(String status) => switch (status) {
  'new' => KitchenColors.orange,
  'accepted' => const Color(0xFF89621B),
  'preparing' => KitchenColors.amber,
  'ready' || 'served' => KitchenColors.green,
  'cancelled' => KitchenColors.red,
  _ => KitchenColors.muted,
};

String _statusName(String status) => switch (status) {
  'new' => 'New',
  'accepted' => 'Accepted',
  'preparing' => 'Preparing',
  'ready' => 'Ready',
  'served' => 'Served',
  'cancelled' => 'Cancelled',
  _ => status,
};

Color _foodTypeColor(String? type) {
  final value = (type ?? '').toLowerCase();
  if (value.contains('non') ||
      value.contains('chicken') ||
      value.contains('meat')) {
    return const Color(0xFFB63A31);
  }
  if (value.contains('egg')) return const Color(0xFFBE821B);
  return KitchenColors.green;
}

String _timeLabel(DateTime time) {
  final difference = DateTime.now().difference(time);
  if (difference.inSeconds < 30) return 'just now';
  if (difference.inMinutes < 1) return 'less than a minute ago';
  return '${difference.inMinutes} min ago';
}

String _durationLabel(Duration elapsed) {
  if (elapsed.inSeconds < 30) return 'Just now';
  if (elapsed.inMinutes < 1) return '<1 min';
  if (elapsed.inHours < 1) return '${elapsed.inMinutes} min';
  if (elapsed.inDays < 1) {
    final minutes = elapsed.inMinutes.remainder(60);
    return '${elapsed.inHours}h ${minutes}m';
  }
  final hours = elapsed.inHours.remainder(24);
  return '${elapsed.inDays}d ${hours}h';
}

String _tableLabel(String name) {
  final value = name.trim();
  return value.toLowerCase().startsWith('table ') ? value : 'Table $value';
}
