import 'package:flutter/material.dart';

import '../app/kitchen_theme.dart';
import '../domain/kitchen_models.dart';
import 'kitchen_page_controls.dart';

class KitchenBoardScreen extends StatefulWidget {
  const KitchenBoardScreen({
    super.key,
    required this.account,
    required this.kitchens,
    required this.selectedKitchen,
    required this.tickets,
    required this.busyTicketId,
    required this.hasSnapshot,
    required this.isRefreshing,
    required this.error,
    required this.boardView,
    required this.onRefresh,
    required this.onKitchenChanged,
    required this.onBoardViewChanged,
    required this.onAcceptTicket,
    required this.onItemReady,
    required this.onSignOut,
    required this.currentPage,
    required this.lastPage,
    required this.historyMayBeLimited,
    required this.onPageChanged,
    this.onStockControl,
  });

  final KitchenAccount account;
  final List<KitchenStation> kitchens;
  final KitchenStation? selectedKitchen;
  final List<KotTicket> tickets;
  final String? busyTicketId;
  final bool hasSnapshot;
  final bool isRefreshing;
  final String? error;
  final String boardView;
  final Future<void> Function() onRefresh;
  final Future<void> Function(KitchenStation? kitchen) onKitchenChanged;
  final Future<void> Function(String view) onBoardViewChanged;
  final Future<void> Function(KotTicket ticket) onAcceptTicket;
  final Future<void> Function(KotTicket ticket, KotDish dish) onItemReady;
  final Future<void> Function() onSignOut;
  final VoidCallback? onStockControl;
  final int currentPage;
  final int lastPage;
  final bool historyMayBeLimited;
  final ValueChanged<int> onPageChanged;

  @override
  State<KitchenBoardScreen> createState() => _KitchenBoardScreenState();
}

class _KitchenBoardScreenState extends State<KitchenBoardScreen> {
  static const _ticketsPerPage = 10;

  final _searchController = TextEditingController();
  final _scrollController = ScrollController();
  String _activeFilter = 'All';
  int _localPage = 1;

  String get _filter => switch (widget.boardView) {
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
    _ => _activeFilter,
  };

  String get _boardTitle => switch (widget.boardView) {
    'completed' => 'Today’s completed tickets',
    'cancelled' => 'Today’s cancellation history',
    _ => 'Today’s kitchen tickets',
  };

  String get _searchQuery => _searchController.text.trim().toLowerCase();

  Iterable<KotTicket> get _matchingTickets {
    final query = _searchQuery;
    return widget.tickets.where(
      (ticket) => _matchesFilter(ticket) && _matchesSearch(ticket, query),
    );
  }

  int _lastLocalPage(int count) =>
      count == 0 ? 1 : (count - 1) ~/ _ticketsPerPage + 1;

  @override
  void didUpdateWidget(covariant KitchenBoardScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.boardView != widget.boardView ||
        oldWidget.selectedKitchen?.id != widget.selectedKitchen?.id ||
        (oldWidget.lastPage > 1) != (widget.lastPage > 1)) {
      _localPage = 1;
    } else if (_localPage > 1 && oldWidget.tickets != widget.tickets) {
      _localPage = _localPage.clamp(1, _lastLocalPage(_matchingTickets.length));
    }
  }

  @override
  void dispose() {
    _searchController.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final matchingTickets = _matchingTickets.toList()
      ..sort((a, b) {
        final receivedOrder = (b.createdAt?.microsecondsSinceEpoch ?? -1)
            .compareTo(a.createdAt?.microsecondsSinceEpoch ?? -1);
        return receivedOrder == 0 ? b.id.compareTo(a.id) : receivedOrder;
      });
    final hasApiPages = widget.lastPage > 1;
    final lastPage = hasApiPages
        ? widget.lastPage
        : _lastLocalPage(matchingTickets.length);
    final currentPage = hasApiPages ? widget.currentPage : _localPage;
    final visibleTickets = hasApiPages
        ? matchingTickets
        : matchingTickets
              .skip((currentPage - 1) * _ticketsPerPage)
              .take(_ticketsPerPage);

    return Scaffold(
      appBar: AppBar(
        toolbarHeight: 46 + MediaQuery.textScalerOf(context).scale(16),
        titleSpacing: 12,
        title: Row(
          children: [
            if (MediaQuery.sizeOf(context).width >= 400 &&
                MediaQuery.textScalerOf(context).scale(19) <= 25) ...[
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
            ],
            Expanded(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    widget.selectedKitchen?.name ??
                        (widget.kitchens.length == 1
                            ? widget.kitchens.single.name
                            : 'All assigned kitchens'),
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
            tooltip: 'Sign out',
            onPressed: () => _confirmSignOut(context),
            icon: const Icon(Icons.logout_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: RefreshIndicator(
          onRefresh: widget.onRefresh,
          child: ListView(
            controller: _scrollController,
            physics: const AlwaysScrollableScrollPhysics(),
            keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
            padding: const EdgeInsets.fromLTRB(18, 13, 18, 30),
            children: [
              Text(
                _boardTitle,
                style: Theme.of(context).textTheme.headlineMedium,
              ),
              const SizedBox(height: 5),
              if (widget.historyMayBeLimited)
                Text(
                  widget.selectedKitchen == null && widget.kitchens.length > 1
                      ? 'History uses the latest 100 records returned. Select a station to check its own history.'
                      : 'History uses the latest 100 records returned.',
                ),
              if (widget.kitchens.length > 1) ...[
                const SizedBox(height: 14),
                _stationPicker(),
              ],
              const SizedBox(height: 12),
              _filterBar(),
              if (widget.kitchens.isNotEmpty) ...[
                const SizedBox(height: 8),
                _searchField(context),
              ],
              const SizedBox(height: 10),
              if (widget.kitchens.isEmpty)
                const _EmptyState(
                  icon: Icons.store_mall_directory_outlined,
                  title: 'No kitchen stations yet',
                  message: 'Ask a manager to assign an active kitchen station to your account.',
                )
              else if (widget.error case final error?)
                _TicketLoadError(
                  message: error,
                  showingPreviousTickets: visibleTickets.isNotEmpty,
                  onRetry: widget.isRefreshing ? null : widget.onRefresh,
                )
              else if (!widget.hasSnapshot)
                const Padding(
                  padding: EdgeInsets.only(top: 55),
                  child: Center(child: CircularProgressIndicator()),
                )
              else if (visibleTickets.isEmpty && _searchQuery.isNotEmpty)
                _EmptyState(
                  icon: Icons.search_off_rounded,
                  title: 'No matching tickets',
                  message: hasApiPages
                      ? 'Try another page, search, or filter.'
                      : 'Try another search or change the filters.',
                )
              else if (visibleTickets.isEmpty)
                _EmptyState(
                  icon: _filter == 'New'
                      ? Icons.mark_email_read_outlined
                      : Icons.restaurant_menu_rounded,
                  title: _emptyTitle,
                  message: _emptyMessage,
                ),
              ...visibleTickets.map(
                (ticket) => Padding(
                  key: ValueKey(ticket.id),
                  padding: const EdgeInsets.only(bottom: 13),
                  child: _TicketCard(
                    ticket: ticket,
                    kitchenName: widget.selectedKitchen == null
                        ? _kitchenName(ticket)
                        : null,
                    isBusy: widget.busyTicketId == '${ticket.id}',
                    updateInProgress: widget.busyTicketId != null,
                    isActive: widget.boardView == 'active',
                    onAccept: () => widget.onAcceptTicket(ticket),
                    onItemReady: (dish) => widget.onItemReady(ticket, dish),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
      bottomNavigationBar: lastPage > 1
          ? Material(
              child: SafeArea(
                top: false,
                minimum: const EdgeInsets.symmetric(horizontal: 18),
                child: KitchenPageControls(
                  currentPage: currentPage,
                  lastPage: lastPage,
                  total: hasApiPages ? null : matchingTickets.length,
                  loading: hasApiPages && widget.isRefreshing,
                  onPageChanged: _changePage,
                ),
              ),
            )
          : null,
    );
  }

  void _changePage(int page) {
    if (widget.lastPage > 1) {
      widget.onPageChanged(page);
    } else {
      setState(() => _localPage = page);
    }
    if (_scrollController.hasClients) _scrollController.jumpTo(0);
  }

  Widget _stationPicker() {
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

  Widget _filterBar() => Wrap(
    spacing: 8,
    crossAxisAlignment: WrapCrossAlignment.center,
    children: [
      for (final filter in const ['All', 'New', 'Accepted'])
        _activeFilterChip(filter),
      PopupMenuButton<String>(
        tooltip: 'Ticket history',
        initialValue: widget.boardView,
        onSelected: widget.onBoardViewChanged,
        itemBuilder: (context) => const [
          PopupMenuItem(
            value: 'cancelled',
            child: Text('Cancellation history'),
          ),
          PopupMenuItem(value: 'completed', child: Text('Completed tickets')),
        ],
        child: const Chip(
          avatar: Icon(Icons.history_rounded, size: 18),
          label: Text('History'),
        ),
      ),
    ],
  );

  Widget _activeFilterChip(String filter) {
    final selected = widget.boardView == 'active' && _activeFilter == filter;
    return ChoiceChip(
      label: Text(filter),
      selected: selected,
      showCheckmark: false,
      onSelected: (_) {
        if (!selected) {
          setState(() {
            _activeFilter = filter;
            _localPage = 1;
          });
        }
        widget.onBoardViewChanged('active');
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
    );
  }

  Widget _searchField(BuildContext context) => TextField(
    controller: _searchController,
    autocorrect: false,
    enableSuggestions: false,
    textInputAction: TextInputAction.search,
    onChanged: (_) => setState(() => _localPage = 1),
    onTapOutside: (_) => FocusScope.of(context).unfocus(),
    decoration: InputDecoration(
      labelText: 'Search tickets',
      hintText: 'KOT, order, table, or dish',
      prefixIcon: const Icon(Icons.search_rounded),
      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      suffixIcon: _searchController.text.isEmpty
          ? null
          : IconButton(
              tooltip: 'Clear search',
              onPressed: () => setState(() {
                _searchController.clear();
                _localPage = 1;
              }),
              icon: const Icon(Icons.close_rounded),
            ),
    ),
  );

  bool _matchesSearch(KotTicket ticket, String query) {
    if (query.isEmpty) return true;
    return ticket.number.toLowerCase().contains(query) ||
        ticket.orderNumber.toLowerCase().contains(query) ||
        _tableLabel(ticket.tableName).toLowerCase().contains(query) ||
        ticket.dishes.any(
          (dish) =>
              dish.name.toLowerCase().contains(query) ||
              dish.displayName.toLowerCase().contains(query),
        );
  }

  bool _matchesFilter(KotTicket ticket) => switch (_filter) {
    'New' => ticket.status == 'new',
    'Accepted' => ticket.isAccepted,
    'Cancelled' =>
      ticket.status == 'cancelled' ||
          ticket.dishes.any((dish) => dish.cancelledQuantity > 0),
    'Completed' =>
      ticket.orderStatus == 'completed' || ticket.status == 'served',
    _ => true,
  };

  String? _kitchenName(KotTicket ticket) {
    for (final kitchen in widget.kitchens) {
      if (kitchen.id == ticket.kitchenId) return kitchen.name;
    }
    return null;
  }

  String get _emptyTitle => switch (_filter) {
    'New' => 'No new tickets',
    'Accepted' => 'No accepted tickets',
    'Cancelled' => 'No cancelled items',
    'Completed' => 'No completed orders',
    _ => 'All caught up',
  };

  String get _emptyMessage => switch (_filter) {
    'New' => 'New kitchen orders will appear here automatically.',
    'Accepted' => 'New tickets will appear here after a kitchen accepts them.',
    'Cancelled' => 'Today’s tickets with cancelled dishes will appear here.',
    'Completed' => 'Today’s completed tickets will appear here.',
    _ => 'There are no active tickets from today for your assigned stations.',
  };

  Future<void> _confirmSignOut(BuildContext context) async {
    final shouldSignOut = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        scrollable: true,
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

class _TicketCard extends StatelessWidget {
  const _TicketCard({
    required this.ticket,
    required this.kitchenName,
    required this.isBusy,
    required this.updateInProgress,
    required this.isActive,
    required this.onAccept,
    required this.onItemReady,
  });

  final KotTicket ticket;
  final String? kitchenName;
  final bool isBusy;
  final bool updateInProgress;
  final bool isActive;
  final VoidCallback onAccept;
  final ValueChanged<KotDish> onItemReady;

  @override
  Widget build(BuildContext context) {
    final status = isActive && ticket.isAccepted ? 'accepted' : ticket.status;
    final color = _statusColor(status);
    final receivedAt = ticket.createdAt;
    final age = receivedAt == null
        ? null
        : _durationLabel(DateTime.now().difference(receivedAt));
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
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      ticket.number,
                      style: const TextStyle(
                        color: KitchenColors.ink,
                        fontSize: 16,
                        fontWeight: FontWeight.w900,
                        letterSpacing: -0.3,
                      ),
                    ),
                    const SizedBox(height: 5),
                    if (kitchenName != null)
                      Text(
                        kitchenName!,
                        style: Theme.of(context).textTheme.labelLarge,
                      ),
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
                        if (receivedAt != null)
                          Text(
                            'Received ${_receivedTimeLabel(receivedAt)}',
                            style: const TextStyle(
                              color: KitchenColors.muted,
                              fontSize: 11,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                      ],
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                Wrap(
                  spacing: 10,
                  runSpacing: 6,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  children: [
                    _StatusPill(label: _statusName(status), color: color),
                    if (isActive && ticket.orderStatus == 'partially_ready')
                      const _StatusPill(
                        label: 'Partially Ready',
                        color: KitchenColors.amber,
                      ),
                    if (age != null)
                      Row(
                        mainAxisSize: MainAxisSize.min,
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
                for (final dish in ticket.dishes)
                  _DishRow(
                    dish: dish,
                    isActive: isActive,
                    canMarkReady:
                        isActive &&
                        ticket.isAccepted &&
                        const [
                          'new',
                          'accepted',
                          'preparing',
                        ].contains(dish.displayStatus),
                    onMarkReady: updateInProgress
                        ? null
                        : () => onItemReady(dish),
                  ),
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
                if (isActive && ticket.status == 'new') ...[
                  const SizedBox(height: 13),
                  FilledButton.icon(
                    onPressed: updateInProgress ? null : onAccept,
                    icon: isBusy
                        ? const SizedBox.square(
                            dimension: 17,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: Colors.white,
                            ),
                          )
                        : const Icon(Icons.done_rounded),
                    label: Text(isBusy ? 'Updating ticket…' : 'Accept ticket'),
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
  const _DishRow({
    required this.dish,
    required this.isActive,
    required this.canMarkReady,
    required this.onMarkReady,
  });
  final KotDish dish;
  final bool isActive;
  final bool canMarkReady;
  final VoidCallback? onMarkReady;

  @override
  Widget build(BuildContext context) {
    final status = dish.displayStatus;
    final isCancelled = status == 'cancelled';
    final quantity = dish.activeQuantity;
    final note = dish.note?.trim();
    final String? cancellation = switch (status) {
      'cancelled' =>
        '${dish.cancelledQuantity > 0 ? dish.cancelledQuantity : dish.quantity} cancelled',
      _ when dish.cancelledQuantity == 0 => null,
      _ => '$quantity remaining · ${dish.cancelledQuantity} cancelled',
    };

    return Padding(
      padding: const EdgeInsets.only(bottom: 13),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            isCancelled ? dish.displayName : '$quantity× ${dish.displayName}',
            style: TextStyle(
              color: isCancelled ? KitchenColors.muted : KitchenColors.ink,
              fontSize: 18,
              fontWeight: FontWeight.w800,
            ),
          ),
          if (note != null && note.isNotEmpty) ...[
            const SizedBox(height: 4),
            Text(
              note,
              style: const TextStyle(
                color: Color(0xFF76520F),
                fontSize: 15,
                fontWeight: FontWeight.w600,
                height: 1.3,
              ),
            ),
          ],
          if (cancellation != null) ...[
            const SizedBox(height: 5),
            Text(
              cancellation,
              style: const TextStyle(
                color: KitchenColors.red,
                fontSize: 14,
                fontWeight: FontWeight.w800,
              ),
            ),
          ],
          if (canMarkReady)
            OutlinedButton.icon(
              onPressed: onMarkReady,
              icon: const Icon(Icons.check_circle_outline_rounded),
              label: const Text('Mark ready'),
            ),
          if (!isActive && !isCancelled) ...[
            const SizedBox(height: 6),
            _StatusPill(
              label: _statusName(status),
              color: _statusColor(status),
            ),
          ],
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
                  fontSize: 14,
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

class _TicketLoadError extends StatelessWidget {
  const _TicketLoadError({
    required this.message,
    required this.showingPreviousTickets,
    required this.onRetry,
  });

  final String message;
  final bool showingPreviousTickets;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.only(bottom: 10),
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: const Color(0xFFFFF0ED),
      borderRadius: BorderRadius.circular(12),
      border: Border.all(color: KitchenColors.red.withValues(alpha: 0.22)),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          showingPreviousTickets
              ? 'Couldn’t refresh tickets'
              : 'Couldn’t load tickets',
          style: Theme.of(context).textTheme.titleMedium
              ?.copyWith(color: KitchenColors.red),
        ),
        const SizedBox(height: 4),
        Text(
          message,
          style: const TextStyle(color: KitchenColors.ink, fontSize: 14),
        ),
        if (showingPreviousTickets)
          const Text(
            'Showing tickets from the last successful refresh.',
            style: TextStyle(color: KitchenColors.ink, fontSize: 14),
          ),
        const SizedBox(height: 4),
        OutlinedButton(
          onPressed: onRetry,
          child: Text(onRetry == null ? 'Retrying…' : 'Retry'),
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

String _receivedTimeLabel(DateTime time) {
  final local = kitchenRestaurantTime(time);
  String two(int value) => value.toString().padLeft(2, '0');
  return '${two(local.day)}/${two(local.month)}/${local.year} ${two(local.hour)}:${two(local.minute)}';
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
