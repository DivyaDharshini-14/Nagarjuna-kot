import 'dart:async';

import 'package:flutter/material.dart';

import '../../../app/app_theme.dart';
import '../../../core/network/mobile_api_client.dart';
import '../data/mobile_order_repository.dart';
import '../domain/order_status.dart';

class OrderProgressScreen extends StatefulWidget {
  const OrderProgressScreen({
    super.key,
    required this.orderId,
    required this.orderNumber,
    required this.repository,
    required this.onBack,
    required this.onSessionExpired,
  });

  final int orderId;
  final String orderNumber;
  final MobileOrderRepository repository;
  final VoidCallback onBack;
  final Future<void> Function() onSessionExpired;

  @override
  State<OrderProgressScreen> createState() => _OrderProgressScreenState();
}

class _OrderProgressScreenState extends State<OrderProgressScreen>
    with WidgetsBindingObserver {
  static const _refreshInterval = Duration(seconds: 12);

  Timer? _pollTimer;
  OrderStatusSnapshot? _snapshot;
  String? _refreshError;
  bool _isLoading = true;
  bool _isRefreshing = false;
  bool _isInForeground = true;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _refresh();
    _pollTimer = Timer.periodic(_refreshInterval, (_) {
      if (_isInForeground && !_isTerminal(_snapshot?.orderStatus)) {
        _refresh(quiet: true);
      }
    });
  }

  @override
  void dispose() {
    _pollTimer?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    _isInForeground = state == AppLifecycleState.resumed;
    if (_isInForeground) _refresh(quiet: true);
  }

  Future<void> _refresh({bool quiet = false}) async {
    if (_isRefreshing) return;
    _isRefreshing = true;
    if (!quiet && mounted) {
      setState(() {
        _isLoading = _snapshot == null;
        _refreshError = null;
      });
    }

    try {
      final previous = _snapshot;
      final latest = await widget.repository.getOrderStatus(widget.orderId);
      if (!mounted) return;
      setState(() {
        _snapshot = latest;
        _isLoading = false;
        _refreshError = null;
      });
      if (previous != null) _announceNewReadyItems(previous, latest);
    } on ApiException catch (error) {
      if (!mounted) return;
      if (error.requiresSignIn) {
        await widget.onSessionExpired();
        return;
      }
      setState(() {
        _isLoading = false;
        _refreshError = error.message;
      });
    } on ApiTransportException catch (error) {
      if (!mounted) return;
      setState(() {
        _isLoading = false;
        _refreshError = error.message;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _isLoading = false;
        _refreshError = 'Could not refresh order progress. Try again.';
      });
    } finally {
      _isRefreshing = false;
    }
  }

  void _announceNewReadyItems(
    OrderStatusSnapshot previous,
    OrderStatusSnapshot latest,
  ) {
    final previousStatuses = {
      for (final item in previous.items) item.id: item.status.toLowerCase(),
    };
    for (final item in latest.items) {
      if (item.status.toLowerCase() == 'ready' &&
          previousStatuses[item.id] != 'ready') {
        ScaffoldMessenger.of(context)
          ..hideCurrentSnackBar()
          ..showSnackBar(
            SnackBar(content: Text('${item.name} is ready for the table.')),
          );
        return;
      }
    }
  }

  bool _isTerminal(String? status) =>
      status == 'completed' || status == 'cancelled';

  @override
  Widget build(BuildContext context) {
    final snapshot = _snapshot;
    return Scaffold(
      backgroundColor: AppColors.canvas,
      appBar: AppBar(
        backgroundColor: AppColors.canvas,
        leading: IconButton(
          onPressed: widget.onBack,
          tooltip: 'Back to tables',
          icon: const Icon(Icons.arrow_back_rounded),
        ),
        title: const Text('Order progress'),
        actions: [
          IconButton(
            onPressed: _isRefreshing ? null : () => _refresh(),
            tooltip: 'Refresh order status',
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: _isLoading
          ? const Center(
              child: CircularProgressIndicator(color: AppColors.terracotta),
            )
          : snapshot == null
          ? _UnavailableState(
              message: _refreshError ?? 'Order progress is unavailable.',
              onRetry: () => _refresh(),
            )
          : RefreshIndicator(
              onRefresh: () => _refresh(),
              color: AppColors.terracotta,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(20, 8, 20, 28),
                children: [
                  _OrderHeader(snapshot: snapshot),
                  if (_refreshError != null) ...[
                    const SizedBox(height: 12),
                    _RefreshNotice(message: _refreshError!),
                  ],
                  const SizedBox(height: 20),
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          'DISH STATUS',
                          style: Theme.of(context).textTheme.labelMedium,
                        ),
                      ),
                      Text(
                        'Auto refreshes every 12 sec',
                        style: Theme.of(context).textTheme.bodySmall
                            ?.copyWith(color: AppColors.mutedInk, fontSize: 11),
                      ),
                    ],
                  ),
                  const SizedBox(height: 10),
                  if (snapshot.items.isEmpty)
                    const _EmptyItems()
                  else
                    ...snapshot.items.map((item) => _OrderItemCard(item: item)),
                  if (snapshot.tickets.isNotEmpty) ...[
                    const SizedBox(height: 20),
                    Text(
                      'KITCHEN TICKETS',
                      style: Theme.of(context).textTheme.labelMedium,
                    ),
                    const SizedBox(height: 10),
                    ...snapshot.tickets.map(
                      (ticket) => _TicketCard(ticket: ticket),
                    ),
                  ],
                  const SizedBox(height: 16),
                  Text(
                    'Kitchen updates appear here as they are recorded.',
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.bodySmall
                        ?.copyWith(color: AppColors.mutedInk),
                  ),
                ],
              ),
            ),
    );
  }
}

class _OrderHeader extends StatelessWidget {
  const _OrderHeader({required this.snapshot});

  final OrderStatusSnapshot snapshot;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: AppColors.ink,
        borderRadius: BorderRadius.circular(22),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'ORDER',
            style: TextStyle(
              color: Color(0xFFB9B9B2),
              fontSize: 10,
              letterSpacing: 1.4,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 6),
          Row(
            children: [
              Expanded(
                child: Text(
                  snapshot.orderNumber,
                  style: const TextStyle(
                    color: AppColors.white,
                    fontSize: 21,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              _StatusPill(status: snapshot.orderStatus, inverse: true),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            'Table ${snapshot.tableName}',
            style: const TextStyle(color: Color(0xFFD6D5CF), fontSize: 13),
          ),
        ],
      ),
    );
  }
}

class _OrderItemCard extends StatelessWidget {
  const _OrderItemCard({required this.item});

  final OrderItemProgress item;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 9),
      padding: const EdgeInsets.symmetric(horizontal: 15, vertical: 13),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(17),
        border: Border.all(color: AppColors.line),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 34,
            height: 34,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: const Color(0xFFF3EEE8),
              borderRadius: BorderRadius.circular(11),
            ),
            child: Text(
              '${item.quantity}×',
              style: const TextStyle(
                color: AppColors.ink,
                fontWeight: FontWeight.w800,
                fontSize: 12,
              ),
            ),
          ),
          const SizedBox(width: 11),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  item.name,
                  style: const TextStyle(
                    color: AppColors.ink,
                    fontSize: 14,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                if (item.instructions?.trim().isNotEmpty == true) ...[
                  const SizedBox(height: 3),
                  Text(
                    item.instructions!,
                    style: const TextStyle(
                      color: AppColors.mutedInk,
                      fontSize: 11,
                    ),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(width: 8),
          _StatusPill(status: item.status),
        ],
      ),
    );
  }
}

class _TicketCard extends StatelessWidget {
  const _TicketCard({required this.ticket});

  final KitchenTicketProgress ticket;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: AppColors.field,
        borderRadius: BorderRadius.circular(15),
        border: Border.all(color: AppColors.line),
      ),
      child: Row(
        children: [
          const Icon(
            Icons.receipt_long_outlined,
            size: 19,
            color: AppColors.terracotta,
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '${ticket.kitchenName} · ${ticket.number}',
                  style: const TextStyle(
                    color: AppColors.ink,
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
          _StatusPill(status: ticket.status),
        ],
      ),
    );
  }
}

class _StatusPill extends StatelessWidget {
  const _StatusPill({required this.status, this.inverse = false});

  final String status;
  final bool inverse;

  @override
  Widget build(BuildContext context) {
    final normalized = status.trim().toLowerCase();
    final color = switch (normalized) {
      'ready' || 'served' || 'completed' => const Color(0xFF2F7A50),
      'preparing' || 'accepted' => const Color(0xFF536A9D),
      'cancelled' => AppColors.error,
      _ => AppColors.terracotta,
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6),
      decoration: BoxDecoration(
        color: inverse
            ? color.withValues(alpha: 0.2)
            : color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(30),
      ),
      child: Text(
        _label(normalized),
        style: TextStyle(
          color: inverse ? const Color(0xFFF4F2ED) : color,
          fontSize: 10,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }

  String _label(String value) => switch (value) {
    'new' => 'New',
    'accepted' => 'Accepted',
    'preparing' => 'Preparing',
    'ready' => 'Ready',
    'served' => 'Served',
    'cancelled' => 'Cancelled',
    'completed' => 'Completed',
    _ => 'Updating',
  };
}

class _RefreshNotice extends StatelessWidget {
  const _RefreshNotice({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: AppColors.errorSurface,
      border: Border.all(color: const Color(0xFFF4C7C2)),
      borderRadius: BorderRadius.circular(12),
    ),
    child: Text(
      'Showing the last update. $message',
      style: const TextStyle(color: AppColors.error, fontSize: 12),
    ),
  );
}

class _EmptyItems extends StatelessWidget {
  const _EmptyItems();

  @override
  Widget build(BuildContext context) => const Padding(
    padding: EdgeInsets.symmetric(vertical: 24),
    child: Text(
      'No dish items are attached to this order.',
      textAlign: TextAlign.center,
      style: TextStyle(color: AppColors.mutedInk, fontSize: 13),
    ),
  );
}

class _UnavailableState extends StatelessWidget {
  const _UnavailableState({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(
            Icons.cloud_off_outlined,
            size: 42,
            color: AppColors.mutedInk,
          ),
          const SizedBox(height: 14),
          const Text(
            'Could not load order progress',
            style: TextStyle(color: AppColors.ink, fontWeight: FontWeight.w700),
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 7),
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 18),
          OutlinedButton.icon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh_rounded),
            label: const Text('Retry'),
          ),
        ],
      ),
    ),
  );
}
