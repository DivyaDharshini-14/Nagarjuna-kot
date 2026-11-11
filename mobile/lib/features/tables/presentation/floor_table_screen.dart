import 'package:flutter/material.dart';

import '../../../app/app_theme.dart';
import '../../../core/network/mobile_api_client.dart';
import '../data/floor_table_repository.dart';
import '../domain/floor_table.dart';

enum _TableFilter { all, available, occupied }

class FloorTableScreen extends StatefulWidget {
  const FloorTableScreen({
    super.key,
    required this.repository,
    required this.onSignOut,
    required this.onSessionExpired,
    required this.onTableSelected,
    required this.onTrackOrder,
    required this.canApproveCancellations,
    required this.onOpenCancellationApprovals,
  });

  final FloorTableRepository repository;
  final Future<void> Function() onSignOut;
  final Future<void> Function() onSessionExpired;
  final ValueChanged<RestaurantTable> onTableSelected;
  final ValueChanged<RestaurantTable> onTrackOrder;
  final bool canApproveCancellations;
  final VoidCallback onOpenCancellationApprovals;

  @override
  State<FloorTableScreen> createState() => _FloorTableScreenState();
}

class _FloorTableScreenState extends State<FloorTableScreen> {
  FloorTableSnapshot? _snapshot;
  RestaurantTable? _selectedTable;
  int? _selectedFloorId;
  _TableFilter _filter = _TableFilter.all;
  bool _isLoading = true;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _loadFloorTables();
  }

  Future<void> _loadFloorTables() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
      _snapshot = null;
      _selectedTable = null;
      _selectedFloorId = null;
    });

    try {
      final snapshot = await widget.repository.loadAssignedFloorTables();
      if (!mounted) return;
      setState(() {
        _snapshot = snapshot;
        _selectedFloorId = snapshot.floors.isEmpty
            ? null
            : snapshot.floors.first.id;
        _selectedTable = null;
        _isLoading = false;
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
    }
  }

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
      _selectedTable = null;
    });
  }

  void _selectTable(RestaurantTable table) {
    if (!table.canOpen) return;
    setState(() => _selectedTable = table);
  }

  @override
  Widget build(BuildContext context) {
    final snapshot = _snapshot;
    final canShowSelectionBar =
        snapshot != null &&
        snapshot.floors.isNotEmpty &&
        !_isLoading &&
        _errorMessage == null;

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
          if (widget.canApproveCancellations)
            IconButton(
              onPressed: widget.onOpenCancellationApprovals,
              tooltip: 'Cancellation approvals',
              icon: const Icon(Icons.fact_check_outlined),
            ),
          IconButton(
            onPressed: _loadFloorTables,
            tooltip: 'Refresh table status',
            icon: const Icon(Icons.refresh_rounded),
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
            : _errorMessage != null
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
            : _buildTableContent(snapshot),
      ),
      bottomNavigationBar: canShowSelectionBar
          ? _SelectionBar(
              table: _selectedTable,
              onContinue: _selectedTable == null
                  ? null
                  : () => widget.onTableSelected(_selectedTable!),
              onTrackOrder: _selectedTable?.activeOrderId == null
                  ? null
                  : () => widget.onTrackOrder(_selectedTable!),
            )
          : null,
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
    final allTables = snapshot.floors.expand((floor) => floor.tables).toList();
    final availableCount = allTables
        .where((table) => table.status == TableStatus.available)
        .length;
    final occupiedCount = allTables
        .where((table) => table.status == TableStatus.occupied)
        .length;
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
            padding: const EdgeInsets.fromLTRB(20, 24, 20, 0),
            sliver: SliverToBoxAdapter(
              child: _TableSummary(
                total: allTables.length,
                available: availableCount,
                occupied: occupiedCount,
              ),
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
                onChanged: (filter) => setState(() {
                  _filter = filter;
                  _selectedTable = null;
                }),
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
                      return _TableCard(
                        table: table,
                        selected: _selectedTable?.id == table.id,
                        onTap: table.canOpen ? () => _selectTable(table) : null,
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
          'Select a floor, then choose an available table to start an order.',
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

class _TableSummary extends StatelessWidget {
  const _TableSummary({
    required this.total,
    required this.available,
    required this.occupied,
  });

  final int total;
  final int available;
  final int occupied;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 17, horizontal: 12),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColors.line),
      ),
      child: Row(
        children: [
          Expanded(
            child: _SummaryValue(value: '$total', label: 'TABLES'),
          ),
          const _SummaryDivider(),
          Expanded(
            child: _SummaryValue(
              value: '$available',
              label: 'AVAILABLE',
              valueColor: const Color(0xFF2F7A50),
            ),
          ),
          const _SummaryDivider(),
          Expanded(
            child: _SummaryValue(
              value: '$occupied',
              label: 'IN SERVICE',
              valueColor: AppColors.terracotta,
            ),
          ),
        ],
      ),
    );
  }
}

class _SummaryValue extends StatelessWidget {
  const _SummaryValue({
    required this.value,
    required this.label,
    this.valueColor = AppColors.ink,
  });

  final String value;
  final String label;
  final Color valueColor;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Text(
          value,
          style: TextStyle(
            color: valueColor,
            fontSize: 23,
            height: 1.1,
            fontWeight: FontWeight.w700,
            letterSpacing: -0.5,
          ),
        ),
        const SizedBox(height: 5),
        FittedBox(
          fit: BoxFit.scaleDown,
          child: Text(
            label,
            style: const TextStyle(
              color: AppColors.mutedInk,
              fontSize: 9,
              fontWeight: FontWeight.w700,
              letterSpacing: 0.8,
            ),
          ),
        ),
      ],
    );
  }
}

class _SummaryDivider extends StatelessWidget {
  const _SummaryDivider();

  @override
  Widget build(BuildContext context) {
    return Container(width: 1, height: 34, color: AppColors.line);
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
    required this.selected,
    required this.onTap,
  });

  final RestaurantTable table;
  final bool selected;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final statusColor = _statusColor(table.status);
    final canOpen = table.canOpen;

    return Semantics(
      label:
          'Table ${table.name}, ${table.seatingCapacity} seats, ${table.status.label}',
      button: canOpen,
      enabled: canOpen,
      selected: selected,
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
              border: Border.all(
                color: selected ? AppColors.terracotta : AppColors.line,
                width: selected ? 1.6 : 1,
              ),
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
                    if (selected)
                      const Icon(
                        Icons.check_circle_rounded,
                        color: AppColors.terracotta,
                        size: 21,
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
                if (table.isOccupiedWithActiveOrder &&
                    table.activeOrderNumber != null) ...[
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
                          table.activeOrderNumber!,
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
                    if (table.isOccupiedWithActiveOrder)
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

class _SelectionBar extends StatelessWidget {
  const _SelectionBar({
    required this.table,
    required this.onContinue,
    required this.onTrackOrder,
  });

  final RestaurantTable? table;
  final VoidCallback? onContinue;
  final VoidCallback? onTrackOrder;

  @override
  Widget build(BuildContext context) {
    final canResume = table?.isOccupiedWithActiveOrder ?? false;

    return SafeArea(
      top: false,
      child: Container(
        padding: const EdgeInsets.fromLTRB(20, 14, 20, 12),
        decoration: const BoxDecoration(
          color: AppColors.white,
          border: Border(top: BorderSide(color: AppColors.line)),
        ),
        child: Row(
          children: [
            Expanded(
              child: table == null
                  ? const Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          'No table selected',
                          style: TextStyle(
                            color: AppColors.ink,
                            fontWeight: FontWeight.w700,
                            fontSize: 14,
                          ),
                        ),
                        SizedBox(height: 3),
                        Text(
                          'Choose an available table to continue.',
                          style: TextStyle(
                            color: AppColors.mutedInk,
                            fontSize: 11,
                          ),
                        ),
                      ],
                    )
                  : Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          'Table ${table!.name}',
                          style: const TextStyle(
                            color: AppColors.ink,
                            fontWeight: FontWeight.w700,
                            fontSize: 14,
                          ),
                        ),
                        const SizedBox(height: 3),
                        Text(
                          canResume
                              ? 'Active order ready'
                              : 'Ready for a new order',
                          style: const TextStyle(
                            color: AppColors.mutedInk,
                            fontSize: 11,
                          ),
                        ),
                      ],
                    ),
            ),
            const SizedBox(width: 12),
            if (canResume) ...[
              SizedBox(
                width: 108,
                child: FilledButton(
                  onPressed: onTrackOrder,
                  style: FilledButton.styleFrom(
                    minimumSize: const Size.fromHeight(48),
                    padding: const EdgeInsets.symmetric(horizontal: 8),
                    textStyle: const TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  child: const Text('Track order'),
                ),
              ),
              IconButton.filledTonal(
                onPressed: onContinue,
                tooltip: 'Add items to this order',
                icon: const Icon(Icons.add_shopping_cart_rounded, size: 19),
              ),
            ] else
              FilledButton(
                onPressed: onContinue,
                style: FilledButton.styleFrom(
                  minimumSize: const Size(132, 48),
                  padding: const EdgeInsets.symmetric(horizontal: 14),
                  textStyle: const TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                child: const Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text('Continue'),
                    SizedBox(width: 6),
                    Icon(Icons.arrow_forward_rounded, size: 16),
                  ],
                ),
              ),
          ],
        ),
      ),
    );
  }
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
