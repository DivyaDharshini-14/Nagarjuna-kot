import 'dart:async';

import 'package:flutter/material.dart';

import '../../../app/app_theme.dart';
import '../../../core/network/mobile_api_client.dart';
import '../../../core/network/mobile_order_live_connection.dart';
import '../data/mobile_order_repository.dart';
import '../domain/order_status.dart';

class OrderProgressScreen extends StatefulWidget {
  const OrderProgressScreen({
    super.key,
    required this.orderId,
    required this.orderNumber,
    required this.repository,
    required this.liveConnection,
    required this.onBack,
    required this.onOrderCompleted,
    required this.onSessionExpired,
  });

  final int orderId;
  final String orderNumber;
  final MobileOrderRepository repository;
  final MobileOrderLiveConnection liveConnection;
  final VoidCallback onBack;
  final VoidCallback onOrderCompleted;
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
  bool _isCompleting = false;
  bool _isCancelling = false;
  bool _isModifying = false;
  bool _isInForeground = true;
  bool _liveConnected = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _refresh();
    _connectLive();
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
    widget.liveConnection.disconnect();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    _isInForeground = state == AppLifecycleState.resumed;
    if (_isInForeground) {
      _refresh(quiet: true);
      _connectLive();
    } else {
      widget.liveConnection.disconnect();
    }
  }

  void _connectLive() {
    if (!widget.liveConnection.isConfigured ||
        _isTerminal(_snapshot?.orderStatus)) {
      return;
    }
    widget.liveConnection.connect(
      orderId: widget.orderId,
      onConnectionChanged: (connected) {
        if (!mounted) return;
        setState(() => _liveConnected = connected);
        if (connected && !_isTerminal(_snapshot?.orderStatus)) {
          _refresh(quiet: true);
        }
      },
      onOrderChanged: () => _refresh(quiet: true),
    );
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
      if (_isTerminal(latest.orderStatus)) widget.liveConnection.disconnect();
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

  bool _canCompleteOrder(OrderStatusSnapshot snapshot) {
    final orderStatus = snapshot.orderStatus.trim().toLowerCase();
    if (orderStatus != 'ready' && orderStatus != 'served') return false;
    if (snapshot.tickets.isEmpty) return false;
    return snapshot.tickets.every((ticket) {
      final status = ticket.status.trim().toLowerCase();
      return status == 'ready' || status == 'served' || status == 'cancelled';
    });
  }

  Future<void> _completeOrder(OrderStatusSnapshot snapshot) async {
    if (_isCompleting || !_canCompleteOrder(snapshot)) return;

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Complete this order?'),
        content: Text(
          'Confirm the guests have left and Table ${snapshot.tableName} is clear. '
          'The table will become available for a new order.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('Keep open'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: const Text('Complete order'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    setState(() => _isCompleting = true);
    try {
      await widget.repository.completeOrder(widget.orderId);
    } on ApiException catch (error) {
      if (!mounted) return;
      if (error.requiresSignIn) {
        await widget.onSessionExpired();
        return;
      }
      _showCompletionError(error.message);
      return;
    } on ApiTransportException catch (error) {
      if (mounted) _showCompletionError(error.message);
      return;
    } catch (_) {
      if (mounted) {
        _showCompletionError('Could not complete the order. Please retry.');
      }
      return;
    } finally {
      if (mounted) setState(() => _isCompleting = false);
    }

    if (!mounted) return;
    widget.liveConnection.disconnect();
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          'Order completed. Table ${snapshot.tableName} is available.',
        ),
      ),
    );
    widget.onOrderCompleted();
  }

  Future<void> _requestCancellation({OrderItemProgress? item}) async {
    if (_isCancelling || _snapshot == null) return;
    final cancellation = await showDialog<_CancellationDraft>(
      context: context,
      builder: (_) => _CancellationReasonDialog(
        title: item == null ? 'Cancel this order?' : 'Cancel ${item.name}?',
        maxQuantity: item?.cancellableQuantity,
      ),
    );
    if (cancellation == null || !mounted) return;

    setState(() => _isCancelling = true);
    try {
      final message = await widget.repository.requestCancellation(
        orderId: widget.orderId,
        reason: cancellation.reason,
        cancelAll: item == null,
        items: item == null
            ? const []
            : [
                {
                  'order_item_id': item.id,
                  'quantity': cancellation.quantity ?? item.cancellableQuantity,
                },
              ],
      );
      await _refresh();
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(message)));
      }
    } on ApiException catch (error) {
      if (mounted && error.requiresSignIn) {
        await widget.onSessionExpired();
      } else if (mounted) {
        _showCompletionError(error.message);
      }
    } on ApiTransportException catch (error) {
      if (mounted) _showCompletionError(error.message);
    } catch (_) {
      if (mounted) {
        _showCompletionError('Could not send the cancellation request. Retry.');
      }
    } finally {
      if (mounted) setState(() => _isCancelling = false);
    }
  }

  Future<void> _editItemInstructions(OrderItemProgress item) async {
    if (_isModifying || !item.canModify) return;
    final instructions = await showDialog<String?>(
      context: context,
      builder: (_) => _EditInstructionsDialog(
        itemName: item.name,
        initialInstructions: item.instructions ?? '',
      ),
    );
    if (instructions == null || !mounted) return;

    setState(() => _isModifying = true);
    try {
      await widget.repository.updateItemInstructions(
        orderId: widget.orderId,
        orderItemId: item.id,
        instructions: instructions.isEmpty ? null : instructions,
      );
      await _refresh();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Dish instructions updated for the kitchen.'),
          ),
        );
      }
    } on ApiException catch (error) {
      if (!mounted) return;
      if (error.requiresSignIn) {
        await widget.onSessionExpired();
      } else {
        _showCompletionError(error.message);
      }
    } on ApiTransportException catch (error) {
      if (mounted) _showCompletionError(error.message);
    } catch (_) {
      if (mounted) {
        _showCompletionError('Could not update this dish. Try again.');
      }
    } finally {
      if (mounted) setState(() => _isModifying = false);
    }
  }

  void _showCompletionError(String message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }

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
                  if (snapshot.items.any(
                    (item) => item.cancellableQuantity > 0,
                  )) ...[
                    const SizedBox(height: 12),
                    OutlinedButton.icon(
                      onPressed: _isCancelling
                          ? null
                          : () => _requestCancellation(),
                      icon: const Icon(Icons.cancel_outlined),
                      label: Text(
                        _isCancelling
                            ? 'Sending request…'
                            : 'Request cancellation for order',
                      ),
                    ),
                  ],
                  if (_canCompleteOrder(snapshot)) ...[
                    const SizedBox(height: 14),
                    Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: AppColors.white,
                        borderRadius: BorderRadius.circular(18),
                        border: Border.all(color: AppColors.line),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          const Text(
                            'All active kitchen tickets are ready. Complete the order after the guests have left and the table is clear.',
                            style: TextStyle(
                              color: AppColors.mutedInk,
                              fontSize: 12,
                              height: 1.4,
                            ),
                          ),
                          const SizedBox(height: 12),
                          FilledButton.icon(
                            onPressed: _isCompleting
                                ? null
                                : () => _completeOrder(snapshot),
                            icon: _isCompleting
                                ? const SizedBox(
                                    width: 18,
                                    height: 18,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                    ),
                                  )
                                : const Icon(Icons.task_alt_rounded),
                            label: Text(
                              _isCompleting
                                  ? 'Completing order…'
                                  : 'Complete order & free table',
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
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
                      _LiveUpdateStatus(
                        connected: _liveConnected,
                        configured: widget.liveConnection.isConfigured,
                      ),
                    ],
                  ),
                  const SizedBox(height: 10),
                  if (snapshot.items.isEmpty)
                    const _EmptyItems()
                  else
                    ...snapshot.items.map(
                      (item) => Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          _OrderItemCard(item: item),
                          if (item.canModify)
                            Align(
                              alignment: Alignment.centerRight,
                              child: TextButton.icon(
                                onPressed: _isModifying
                                    ? null
                                    : () => _editItemInstructions(item),
                                icon: const Icon(
                                  Icons.edit_note_rounded,
                                  size: 17,
                                ),
                                label: Text(
                                  _isModifying ? 'Updating…' : 'Edit dish note',
                                ),
                              ),
                            ),
                          if (item.pendingCancellationQuantity > 0)
                            const Padding(
                              padding: EdgeInsets.only(left: 8, bottom: 8),
                              child: Text(
                                'Cancellation waiting for manager approval',
                                style: TextStyle(
                                  color: Color(0xFF8B641D),
                                  fontSize: 11,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                            ),
                          if (item.cancellableQuantity > 0)
                            Align(
                              alignment: Alignment.centerRight,
                              child: TextButton.icon(
                                onPressed: _isCancelling
                                    ? null
                                    : () => _requestCancellation(item: item),
                                icon: const Icon(
                                  Icons.remove_circle_outline,
                                  size: 17,
                                ),
                                label: const Text('Request item cancellation'),
                              ),
                            ),
                        ],
                      ),
                    ),
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

class _LiveUpdateStatus extends StatelessWidget {
  const _LiveUpdateStatus({required this.connected, required this.configured});

  final bool connected;
  final bool configured;

  @override
  Widget build(BuildContext context) {
    final color = connected
        ? const Color(0xFF2F7A50)
        : configured
        ? AppColors.terracotta
        : AppColors.mutedInk;
    final label = connected
        ? 'Live'
        : configured
        ? 'Reconnecting'
        : '12s refresh';
    final description = connected
        ? 'Live kitchen updates connected.'
        : 'Live kitchen updates are unavailable. Order status refreshes every 12 seconds.';

    return Tooltip(
      message: description,
      child: Semantics(
        label: description,
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              connected ? Icons.wifi_tethering_rounded : Icons.sync_rounded,
              size: 14,
              color: color,
            ),
            const SizedBox(width: 4),
            Text(
              label,
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: color,
                fontSize: 11,
                fontWeight: FontWeight.w600,
              ),
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
    final remainingQuantity = (item.quantity - item.cancelledQuantity).clamp(
      0,
      item.quantity,
    );
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
              '$remainingQuantity×',
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
                for (final cancellation in item.cancellations) ...[
                  const SizedBox(height: 5),
                  Text(
                    _cancellationHistory(cancellation),
                    style: const TextStyle(
                      color: AppColors.mutedInk,
                      fontSize: 10,
                      height: 1.3,
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

  String _cancellationHistory(ItemCancellationProgress cancellation) {
    final lines = <String>[
      '${cancellation.quantity}× cancellation ${cancellation.status.toLowerCase()} · ${cancellation.reason}',
    ];
    final requestedBy = cancellation.requestedBy;
    if (requestedBy != null) {
      lines.add(
        'Requested by $requestedBy${cancellation.requestedAt == null ? '' : ' · ${_formatDateTime(cancellation.requestedAt!)}'}',
      );
    }
    final decidedBy = cancellation.decidedBy;
    if (decidedBy != null) {
      lines.add(
        'Decided by $decidedBy${cancellation.decidedAt == null ? '' : ' · ${_formatDateTime(cancellation.decidedAt!)}'}',
      );
    }
    if (cancellation.stockReversedQuantity > 0) {
      lines.add(
        '${cancellation.stockReversedQuantity} portion(s) returned to stock',
      );
    }
    if (cancellation.wastageQuantity > 0) {
      lines.add(
        '${cancellation.wastageQuantity} portion(s) recorded as wastage',
      );
    }
    final decisionNote = cancellation.decisionNote?.trim() ?? '';
    if (decisionNote.isNotEmpty) lines.add('Decision: $decisionNote');
    return lines.join('\n');
  }

  String _formatDateTime(DateTime value) =>
      '${value.day.toString().padLeft(2, '0')}/${value.month.toString().padLeft(2, '0')} ${value.hour.toString().padLeft(2, '0')}:${value.minute.toString().padLeft(2, '0')}';
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

class _CancellationReasonDialog extends StatefulWidget {
  const _CancellationReasonDialog({required this.title, this.maxQuantity});

  final String title;
  final int? maxQuantity;

  @override
  State<_CancellationReasonDialog> createState() =>
      _CancellationReasonDialogState();
}

class _CancellationReasonDialogState extends State<_CancellationReasonDialog> {
  final _reasonController = TextEditingController();
  int _quantity = 1;

  @override
  void dispose() {
    _reasonController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: Text(widget.title),
    content: Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (widget.maxQuantity != null && widget.maxQuantity! > 1) ...[
          DropdownButtonFormField<int>(
            initialValue: _quantity,
            decoration: const InputDecoration(labelText: 'Quantity to cancel'),
            items: [
              for (
                var quantity = 1;
                quantity <= widget.maxQuantity!;
                quantity++
              )
                DropdownMenuItem(value: quantity, child: Text('$quantity')),
            ],
            onChanged: (value) {
              if (value != null) setState(() => _quantity = value);
            },
          ),
          const SizedBox(height: 12),
        ],
        TextField(
          controller: _reasonController,
          autofocus: true,
          minLines: 2,
          maxLines: 4,
          maxLength: 500,
          decoration: const InputDecoration(
            labelText: 'Cancellation reason',
            hintText: 'Required for the kitchen and manager',
          ),
        ),
      ],
    ),
    actions: [
      TextButton(
        onPressed: () => Navigator.of(context).pop(),
        child: const Text('Keep order'),
      ),
      FilledButton(
        onPressed: () {
          final reason = _reasonController.text.trim();
          if (reason.length < 3) return;
          Navigator.of(context).pop(
            _CancellationDraft(
              reason: reason,
              quantity: widget.maxQuantity == null ? null : _quantity,
            ),
          );
        },
        child: const Text('Send request'),
      ),
    ],
  );
}

class _CancellationDraft {
  const _CancellationDraft({required this.reason, this.quantity});

  final String reason;
  final int? quantity;
}

class _EditInstructionsDialog extends StatefulWidget {
  const _EditInstructionsDialog({
    required this.itemName,
    required this.initialInstructions,
  });

  final String itemName;
  final String initialInstructions;

  @override
  State<_EditInstructionsDialog> createState() =>
      _EditInstructionsDialogState();
}

class _EditInstructionsDialogState extends State<_EditInstructionsDialog> {
  late final TextEditingController _controller;

  @override
  void initState() {
    super.initState();
    _controller = TextEditingController(text: widget.initialInstructions);
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: Text('Edit ${widget.itemName} note'),
    content: TextField(
      controller: _controller,
      autofocus: true,
      minLines: 2,
      maxLines: 4,
      maxLength: 255,
      decoration: const InputDecoration(
        labelText: 'Special instructions',
        hintText: 'For example: less spicy, no onion',
      ),
    ),
    actions: [
      TextButton(
        onPressed: () => Navigator.of(context).pop(),
        child: const Text('Cancel'),
      ),
      FilledButton(
        onPressed: () => Navigator.of(context).pop(_controller.text.trim()),
        child: const Text('Save note'),
      ),
    ],
  );
}
