import 'package:flutter/material.dart';

import '../../../core/network/mobile_api_client.dart';
import '../../tables/data/floor_table_repository.dart';
import '../data/mobile_order_repository.dart';
import '../data/pending_order_draft_store.dart';
import '../domain/order_status.dart';
import 'cancellation_record_text.dart';

class CancellationHistoryScreen extends StatefulWidget {
  const CancellationHistoryScreen({
    super.key,
    required this.repository,
    required this.store,
    required this.userId,
    required this.canViewOrders,
    required this.onBack,
    required this.onSessionExpired,
    this.floorRepository,
  });

  final MobileOrderRepository repository;
  final PendingOrderDraftStore store;
  final int userId;
  final bool canViewOrders;
  final VoidCallback onBack;
  final Future<void> Function() onSessionExpired;
  final FloorTableRepository? floorRepository;

  @override
  State<CancellationHistoryScreen> createState() =>
      _CancellationHistoryScreenState();
}

typedef _HistoryEntry = ({
  String orderNumber,
  String tableName,
  String dishName,
  ItemCancellationProgress cancellation,
});

class _CancellationHistoryScreenState extends State<CancellationHistoryScreen> {
  static const _pageSize = 12;
  List<int> _orderIds = const [];
  List<_HistoryEntry> _entries = const [];
  int _checked = 0;
  bool _loading = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _refresh();
  }

  Future<void> _refresh() => _load(reset: true);

  Future<void> _load({bool reset = false}) async {
    if (!widget.canViewOrders || _loading) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final orderIds = reset ? await _discoverOrders() : _orderIds;
      if (!mounted) return;
      final start = reset ? 0 : _checked;
      final ids = orderIds.skip(start).take(_pageSize).toList();
      final orders = await Future.wait(ids.map(_readOrder));
      if (!mounted) return;
      final entries = <_HistoryEntry>[if (!reset) ..._entries];
      for (final order in orders.whereType<OrderStatusSnapshot>()) {
        if (order.items.every((item) => item.cancellations.isEmpty)) continue;
        // Keep active orders discoverable after their tables are freed.
        try {
          await widget.store.rememberCancellationOrder(widget.userId, order.id);
        } catch (_) {
          // The live history still displays if local indexing fails.
        }
        for (final item in order.items) {
          for (final cancellation in item.cancellations) {
            final status = cancellation.status.toLowerCase();
            if (status != 'approved' && status != 'rejected') continue;
            entries.add((
              orderNumber: order.orderNumber,
              tableName: order.tableName,
              dishName: item.name,
              cancellation: cancellation,
            ));
          }
        }
      }
      entries.sort((a, b) => _date(b).compareTo(_date(a)));
      if (!mounted) return;
      setState(() {
        _orderIds = orderIds;
        _checked = start + ids.length;
        _entries = entries;
      });
    } on ApiException catch (error) {
      if (!mounted) return;
      if (error.requiresSignIn) {
        await widget.onSessionExpired();
      } else {
        setState(() => _error = error.message);
      }
    } on ApiTransportException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } catch (_) {
      if (mounted) {
        setState(() => _error = 'Could not load cancellation history.');
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<List<int>> _discoverOrders() async {
    final saved = await widget.store.cancellationOrderIds(widget.userId);
    final floor = await widget.floorRepository?.loadAssignedFloorTables();
    return {...saved, if (floor != null) ...floor.activeOrderIds}.toList();
  }

  Future<OrderStatusSnapshot?> _readOrder(int id) async {
    try {
      return await widget.repository.getOrderStatus(id);
    } on ApiException catch (error) {
      // A saved reference can outlive its order or the user's branch access.
      if (error.statusCode == 404) return null;
      rethrow;
    }
  }

  int _date(_HistoryEntry entry) =>
      (entry.cancellation.decidedAt ?? entry.cancellation.requestedAt)
          ?.millisecondsSinceEpoch ??
      0;

  @override
  Widget build(BuildContext context) {
    final hasMore = _checked < _orderIds.length;
    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          onPressed: widget.onBack,
          icon: const Icon(Icons.arrow_back_rounded),
          tooltip: 'Back',
        ),
        title: const Text('Cancellation history'),
      ),
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: _refresh,
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.all(18),
            children: [
              if (!widget.canViewOrders)
                const Text('Your role needs order-view access to see history.')
              else ...[
                const Text(
                  'Records from active orders and orders seen on this device.',
                ),
                if (_error != null) ...[
                  const SizedBox(height: 12),
                  Text(_error!),
                  TextButton(
                    onPressed: _refresh,
                    child: const Text('Try again'),
                  ),
                ],
                if (_entries.isEmpty && !_loading && _error == null) ...[
                  const SizedBox(height: 24),
                  Text(
                    hasMore
                        ? 'No completed or rejected cancellations in the orders checked so far.'
                        : 'No completed or rejected cancellations found.',
                  ),
                ],
                for (final entry in _entries)
                  Card(
                    child: ListTile(
                      title: Text(entry.dishName),
                      subtitle: Text(
                        '${entry.orderNumber} · ${entry.tableName}\n'
                        '${formatCancellationRecord(entry.cancellation)}',
                      ),
                    ),
                  ),
                if (_loading)
                  const Padding(
                    padding: EdgeInsets.all(16),
                    child: Center(child: CircularProgressIndicator()),
                  )
                else if (hasMore)
                  OutlinedButton(
                    onPressed: _load,
                    child: const Text('Check more orders'),
                  ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
