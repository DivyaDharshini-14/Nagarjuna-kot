import 'dart:async';

import 'package:flutter/material.dart';

import '../../../app/app_theme.dart';
import '../../../core/network/mobile_api_client.dart';
import '../../../core/restaurant_day.dart';
import '../../orders/data/mobile_order_repository.dart';
import '../data/floor_table_repository.dart';
import '../domain/floor_table.dart';

enum _TableFilter { all, available, occupied }

class FloorTableScreen extends StatefulWidget {
  const FloorTableScreen({
    super.key,
    required this.repository,
    required this.orderRepository,
    required this.orderActivity,
    required this.onSignOut,
    required this.onSessionExpired,
    required this.onTableSelected,
    required this.onTrackOrder,
    required this.onOpenCancellationHistory,
    this.onActiveOrdersSeen,
  });

  final FloorTableRepository repository;
  final MobileOrderRepository orderRepository;
  final Map<int, DateTime> orderActivity;
  final Future<void> Function() onSignOut;
  final Future<void> Function() onSessionExpired;
  final Future<void> Function(RestaurantTable) onTableSelected;
  final Future<void> Function(RestaurantTable) onTrackOrder;
  final VoidCallback onOpenCancellationHistory;
  final ValueChanged<Iterable<int>>? onActiveOrdersSeen;

  @override
  State<FloorTableScreen> createState() => _FloorTableScreenState();
}

class _FloorTableScreenState extends State<FloorTableScreen>
    with WidgetsBindingObserver {
  FloorTableSnapshot? _snapshot;
  int? _selectedFloorId;
  _TableFilter _filter = _TableFilter.all;
  bool _isLoading = true;
  String? _errorMessage;
  Timer? _refreshTimer;
  bool _isRefreshing = false;
  bool _openingTable = false;
  bool _inForeground = true;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _loadFloorTables();
    _refreshTimer = Timer.periodic(const Duration(seconds: 12), (_) {
      if (_inForeground) unawaited(_loadFloorTables(quiet: true));
    });
  }

  @override
  void dispose() {
    _refreshTimer?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    _inForeground = state == AppLifecycleState.resumed;
    if (_inForeground) unawaited(_loadFloorTables(quiet: true));
  }

  Future<void> _loadFloorTables({bool quiet = false}) async {
    if (_isRefreshing) return;
    _isRefreshing = true;
    setState(() {
      _isLoading = _snapshot == null;
      if (!quiet) _errorMessage = null;
    });

    try {
      final snapshot = await widget.repository.loadAssignedFloorTables();
      if (!mounted) return;
      widget.onActiveOrdersSeen?.call(snapshot.activeOrderIds);
      await _checkActiveOrderDates(snapshot);
      if (!mounted) return;
      final floorId = _selectedFloorId;
      setState(() {
        _snapshot = snapshot;
        _selectedFloorId = snapshot.floors.isEmpty
            ? null
            : snapshot.floors.any((floor) => floor.id == floorId)
            ? floorId
            : snapshot.floors.first.id;
        _isLoading = false;
        _errorMessage = null;
      });
    } on ApiException catch (error) {
      if (!mounted) return;
      if (error.requiresSignIn) {
        await widget.onSessionExpired();
        return;
      }
      setState(() {
        _errorMessage = error.message;
        _isLoading = false;
      });
    } on ApiTransportException catch (error) {
      if (!mounted) return;
      setState(() {
        _errorMessage = error.message;
        _isLoading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _errorMessage = 'We could not load your restaurant tables. Check your connection and try again.';
        _isLoading = false;
      });
    } finally {
      _isRefreshing = false;
    }
  }

  Future<void> _checkActiveOrderDates(FloorTableSnapshot snapshot) async {
    final ids = {
      for (final id in snapshot.activeOrderIds)
        if (!widget.orderActivity.containsKey(id)) id,
    }.toList();

    const batchSize = 6;
    for (var index = 0; index < ids.length && mounted; index += batchSize) {
      await Future.wait(
        ids.skip(index).take(batchSize).map((id) async {
          try {
            final order = await widget.orderRepository.getOrderStatus(id);
            final activity = order.firstKnownActivityAt;
            if (!mounted || activity == null) return;
            final cached = widget.orderActivity[id];
            if (cached == null || activity.isBefore(cached)) {
              widget.orderActivity[id] = activity;
            }
          } on ApiException catch (error) {
            if (error.requiresSignIn) rethrow;
          } on Object {
            // Keep the server's table state if the order date is unavailable.
          }
        }),
      );
    }
  }

  bool _hasPreviousDayOrder(RestaurantTable? table) =>
      isFromPreviousRestaurantDay(widget.orderActivity[table?.activeOrderId]);

  RestaurantFloor? get _selectedFloor {
    final floors = _snapshot?.floors ?? const <RestaurantFloor>[];
    for (final floor in floors) {
      if (floor.id == _selectedFloorId) return floor;
    }
    return null;
  }

  List<RestaurantTable> get _visibleTables {
    final tables = _selectedFloor?.tables ?? const <RestaurantTable>[];
    return switch (_filter) {
      _TableFilter.all => tables,
      _TableFilter.available =>
        tables.where((table) => table.status == TableStatus.available).toList(),
      _TableFilter.occupied =>
        tables.where((table) => table.status == TableStatus.occupied).toList(),
    };
  }

  void _selectFloor(RestaurantFloor floor) {
    setState(() {
      _selectedFloorId = floor.id;
    });
  }

  Future<void> _openTable(
    RestaurantTable table, {
    bool addItems = false,
  }) async {
    if (_openingTable || _errorMessage != null || !table.canOpen) return;
    _openingTable = true;
    try {
      if (table.activeOrderId != null &&
          (!addItems || _hasPreviousDayOrder(table))) {
        await widget.onTrackOrder(table);
      } else {
        await widget.onTableSelected(table);
      }
    } on Object {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Could not open this table. Try again.'),
          ),
        );
      }
    } finally {
      _openingTable = false;
    }
  }

  @override
  Widget build(BuildContext context) {
    final snapshot = _snapshot;

    return Scaffold(
      backgroundColor: AppColors.canvas,
      appBar: AppBar(
        backgroundColor: AppColors.canvas,
        titleSpacing: 20,
        title: const Text(
          'NAGARJUNA',
          style: TextStyle(
            color: AppColors.ink,
            fontSize: 14,
            fontWeight: FontWeight.w800,
            letterSpacing: 1.4,
          ),
        ),
        actions: [
          IconButton(
            onPressed: widget.onOpenCancellationHistory,
            tooltip: 'Cancellation history',
            icon: const Icon(Icons.history_rounded),
          ),
          IconButton(
            onPressed: widget.onSignOut,
            tooltip: 'Sign out',
            icon: const Icon(Icons.logout_rounded),
          ),
          const SizedBox(width: 8),
        ],
      ),
      body: SafeArea(
        child: _isLoading
            ? const _LoadingState()
            : _errorMessage != null && snapshot == null
            ? _UnavailableState(
                title: 'Could not load tables',
                message: _errorMessage!,
                onRetry: _loadFloorTables,
              )
            : snapshot == null
            ? _UnavailableState(
                title: 'Tables are not available',
                message:
                    'There is no floor information for your assigned branch.',
                onRetry: _loadFloorTables,
              )
            : Column(
                children: [
                  if (_errorMessage != null)
                    Padding(
                      padding: const EdgeInsets.fromLTRB(20, 8, 20, 0),
                      child: Text(
                        'Showing the last table status. $_errorMessage',
                      ),
                    ),
                  Expanded(child: _buildTableContent(snapshot)),
                ],
              ),
      ),
    );
  }

  Widget _buildTableContent(FloorTableSnapshot snapshot) {
    if (snapshot.floors.isEmpty) {
      return _UnavailableState(
        title: 'No floors configured',
        message:
            'Ask your branch manager to add floors and tables for this branch.',
        onRetry: _loadFloorTables,
      );
    }

    final selectedFloor = _selectedFloor ?? snapshot.floors.first;
    final visibleTables = _visibleTables;

    return RefreshIndicator(
      onRefresh: _loadFloorTables,
      color: AppColors.terracotta,
      child: CustomScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        slivers: [
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(20, 18, 20, 0),
            sliver: SliverToBoxAdapter(
              child: _PageHeader(branchName: snapshot.branchName),
            ),
          ),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(20, 26, 20, 0),
            sliver: SliverToBoxAdapter(
              child: _FloorSelector(
                floors: snapshot.floors,
                selectedFloor: selectedFloor,
                onSelected: _selectFloor,
              ),
            ),
          ),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(20, 22, 20, 0),
            sliver: SliverToBoxAdapter(
              child: _TableFilterRow(
                filter: _filter,
                onChanged: (filter) => setState(() => _filter = filter),
                count: visibleTables.length,
              ),
            ),
          ),
          if (visibleTables.isEmpty)
            const SliverFillRemaining(
              hasScrollBody: false,
              child: _EmptyFloorState(),
            )
          else
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(20, 14, 20, 24),
              sliver: SliverLayoutBuilder(
                builder: (context, constraints) {
                  final crossAxisCount = constraints.crossAxisExtent >= 590
                      ? 3
                      : constraints.crossAxisExtent >= 300
                      ? 2
                      : 1;
                  return SliverGrid.builder(
                    itemCount: visibleTables.length,
                    gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                      crossAxisCount: crossAxisCount,
                      mainAxisExtent: 142,
                      crossAxisSpacing: 12,
                      mainAxisSpacing: 12,
                    ),
                    itemBuilder: (context, index) {
                      final table = visibleTables[index];
                      final previousDayOrder = _hasPreviousDayOrder(table);
                      final canOpen = table.canOpen && _errorMessage == null;
                      return _TableCard(
                        table: table,
                        previousDayOrder: previousDayOrder,
                        onTap: canOpen ? () => _openTable(table) : null,
                        onAddItems:
                            canOpen &&
                                table.isOccupiedWithActiveOrder &&
                                !previousDayOrder
                            ? () => _openTable(table, addItems: true)
                            : null,
                      );
                    },
                  );
                },
              ),
            ),
        ],
      ),
    );
  }
}

class _PageHeader extends StatelessWidget {
  const _PageHeader({required this.branchName});

  final String branchName;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const SizedBox(height: 8),
        Text(
          'Choose a table',
          style: Theme.of(context).textTheme.headlineMedium,
        ),
        const SizedBox(height: 7),
        Text(
          'Tap an available table to open the menu.',
          style: Theme.of(context).textTheme.bodyMedium,
        ),
        const SizedBox(height: 15),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 8),
          decoration: BoxDecoration(
            color: AppColors.white,
            borderRadius: BorderRadius.circular(30),
            border: Border.all(color: AppColors.line),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(
                Icons.location_on_outlined,
                size: 16,
                color: AppColors.terracotta,
              ),
              const SizedBox(width: 6),
              Flexible(
                child: Text(
                  branchName,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: AppColors.ink,
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _FloorSelector extends StatelessWidget {
  const _FloorSelector({
    required this.floors,
    required this.selectedFloor,
    required this.onSelected,
  });

  final List<RestaurantFloor> floors;
  final RestaurantFloor selectedFloor;
  final ValueChanged<RestaurantFloor> onSelected;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('FLOOR', style: Theme.of(context).textTheme.labelMedium),
        const SizedBox(height: 11),
        SizedBox(
          height: 44,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            itemCount: floors.length,
            separatorBuilder: (context, index) => const SizedBox(width: 8),
            itemBuilder: (context, index) {
              final floor = floors[index];
              final selected = floor.id == selectedFloor.id;
              return ChoiceChip(
                label: Text(floor.name),
                selected: selected,
                onSelected: (_) => onSelected(floor),
                labelStyle: TextStyle(
                  color: selected ? AppColors.white : AppColors.ink,
                  fontWeight: FontWeight.w600,
                  fontSize: 13,
                ),
                side: BorderSide(
                  color: selected ? AppColors.ink : AppColors.line,
                ),
                backgroundColor: AppColors.white,
                selectedColor: AppColors.ink,
                showCheckmark: false,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(13),
                ),
                padding: const EdgeInsets.symmetric(horizontal: 10),
              );
            },
          ),
        ),
      ],
    );
  }
}

class _TableFilterRow extends StatelessWidget {
  const _TableFilterRow({
    required this.filter,
    required this.onChanged,
    required this.count,
  });

  final _TableFilter filter;
  final ValueChanged<_TableFilter> onChanged;
  final int count;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: Row(
              children: [
                _FilterChip(
                  label: 'All',
                  selected: filter == _TableFilter.all,
                  onTap: () => onChanged(_TableFilter.all),
                ),
                const SizedBox(width: 8),
                _FilterChip(
                  label: 'Available',
                  selected: filter == _TableFilter.available,
                  onTap: () => onChanged(_TableFilter.available),
                ),
                const SizedBox(width: 8),
                _FilterChip(
                  label: 'Occupied',
                  selected: filter == _TableFilter.occupied,
                  onTap: () => onChanged(_TableFilter.occupied),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(width: 8),
        Text(
          '$count tables',
          style: const TextStyle(
            color: AppColors.mutedInk,
            fontSize: 11,
            fontWeight: FontWeight.w600,
          ),
        ),
      ],
    );
  }
}

class _FilterChip extends StatelessWidget {
  const _FilterChip({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return ChoiceChip(
      label: Text(label),
      selected: selected,
      onSelected: (_) => onTap(),
      labelStyle: TextStyle(
        color: selected ? AppColors.terracotta : AppColors.mutedInk,
        fontSize: 12,
        fontWeight: FontWeight.w600,
      ),
      backgroundColor: Colors.transparent,
      selectedColor: const Color(0x14B94E35),
      side: BorderSide(
        color: selected ? const Color(0x55B94E35) : AppColors.line,
      ),
      showCheckmark: false,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      padding: const EdgeInsets.symmetric(horizontal: 8),
    );
  }
}

class _TableCard extends StatelessWidget {
  const _TableCard({
    required this.table,
    required this.previousDayOrder,
    required this.onTap,
    required this.onAddItems,
  });

  final RestaurantTable table;
  final bool previousDayOrder;
  final VoidCallback? onTap;
  final VoidCallback? onAddItems;

  @override
  Widget build(BuildContext context) {
    final statusColor = _statusColor(table.status);
    final canOpen = onTap != null;

    return Semantics(
      label:
          'Table ${table.name}, ${table.seatingCapacity} seats, ${table.status.label}',
      button: canOpen,
      enabled: canOpen,
      child: Material(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(18),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(18),
          child: Container(
            padding: const EdgeInsets.all(15),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: AppColors.line),
              color: canOpen ? AppColors.white : const Color(0xFFFBFAF8),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        table.name,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: AppColors.ink,
                          fontSize: 21,
                          fontWeight: FontWeight.w700,
                          letterSpacing: -0.4,
                        ),
                      ),
                    ),
                    if (onAddItems != null)
                      IconButton(
                        tooltip: 'Add items to this order',
                        onPressed: onAddItems,
                        icon: const Icon(Icons.add_shopping_cart_rounded),
                      ),
                  ],
                ),
                const SizedBox(height: 7),
                Row(
                  children: [
                    const Icon(
                      Icons.chair_alt_outlined,
                      size: 16,
                      color: AppColors.mutedInk,
                    ),
                    const SizedBox(width: 5),
                    Text(
                      '${table.seatingCapacity} seats',
                      style: const TextStyle(
                        color: AppColors.mutedInk,
                        fontSize: 12,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ],
                ),
                if (previousDayOrder ||
                    (table.isOccupiedWithActiveOrder &&
                        table.activeOrderNumber != null)) ...[
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      const Icon(
                        Icons.receipt_outlined,
                        size: 14,
                        color: AppColors.mutedInk,
                      ),
                      const SizedBox(width: 5),
                      Expanded(
                        child: Text(
                          previousDayOrder
                              ? 'Previous-day order'
                              : table.activeOrderNumber!,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: AppColors.mutedInk,
                            fontSize: 10,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
                const Spacer(),
                Row(
                  children: [
                    Container(
                      width: 7,
                      height: 7,
                      decoration: BoxDecoration(
                        color: statusColor,
                        shape: BoxShape.circle,
                      ),
                    ),
                    const SizedBox(width: 7),
                    Expanded(
                      child: Text(
                        table.status.label,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          color: statusColor,
                          fontSize: 11,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                    if (canOpen)
                      const Icon(
                        Icons.arrow_forward_rounded,
                        size: 17,
                        color: AppColors.mutedInk,
                      ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Color _statusColor(TableStatus status) => switch (status) {
    TableStatus.available => const Color(0xFF2F7A50),
    TableStatus.occupied => AppColors.terracotta,
    TableStatus.reserved => const Color(0xFF536A9D),
    TableStatus.cleaning => const Color(0xFF777A73),
    TableStatus.inactive => const Color(0xFF94968F),
    TableStatus.unknown => const Color(0xFF777A73),
  };
}

class _LoadingState extends StatelessWidget {
  const _LoadingState();

  @override
  Widget build(BuildContext context) {
    return const Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          CircularProgressIndicator(color: AppColors.terracotta),
          SizedBox(height: 16),
          Text(
            'Loading your tables…',
            style: TextStyle(color: AppColors.mutedInk, fontSize: 13),
          ),
        ],
      ),
    );
  }
}

class _UnavailableState extends StatelessWidget {
  const _UnavailableState({
    required this.title,
    required this.message,
    required this.onRetry,
  });

  final String title;
  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(28),
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 380),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 68,
                height: 68,
                decoration: BoxDecoration(
                  color: const Color(0x14B94E35),
                  borderRadius: BorderRadius.circular(22),
                ),
                child: const Icon(
                  Icons.table_restaurant_outlined,
                  color: AppColors.terracotta,
                  size: 30,
                ),
              ),
              const SizedBox(height: 20),
              Text(
                title,
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.titleMedium?.copyWith(
                  color: AppColors.ink,
                  fontSize: 20,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                message,
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.bodyMedium,
              ),
              const SizedBox(height: 22),
              OutlinedButton.icon(
                onPressed: onRetry,
                icon: const Icon(Icons.refresh_rounded),
                label: const Text('Try again'),
                style: OutlinedButton.styleFrom(
                  foregroundColor: AppColors.ink,
                  minimumSize: const Size(150, 48),
                  side: const BorderSide(color: AppColors.line),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _EmptyFloorState extends StatelessWidget {
  const _EmptyFloorState();

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(
              Icons.event_seat_outlined,
              size: 36,
              color: AppColors.mutedInk,
            ),
            const SizedBox(height: 12),
            Text(
              'No tables on this floor',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: 5),
            const Text(
              'Choose another floor or ask your manager to add tables.',
              textAlign: TextAlign.center,
              style: TextStyle(color: AppColors.mutedInk, fontSize: 13),
            ),
          ],
        ),
      ),
    );
  }
}
