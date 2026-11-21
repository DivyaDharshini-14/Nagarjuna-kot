import 'dart:async';

import 'package:flutter/material.dart';

import '../../../app/app_theme.dart';
import '../../../core/network/mobile_api_client.dart';
import '../../../core/network/mobile_order_live_connection.dart';
import '../../../core/restaurant_day.dart';
import '../../tables/domain/floor_table.dart';
import '../data/mobile_order_repository.dart';
import '../data/pending_order_draft_store.dart';
import '../domain/order_status.dart';
import 'cancellation_record_text.dart';

class OrderProgressScreen extends StatefulWidget {
  const OrderProgressScreen({
    super.key,
    required this.orderId,
    required this.orderNumber,
    this.firstKnownActivity,
    required this.repository,
    required this.pendingDraftStore,
    required this.userId,
    required this.onRecoverPendingOrder,
    required this.liveConnection,
    required this.onBack,
    required this.onOrderCompleted,
    required this.onEditOrder,
    required this.canAddItems,
    required this.canEditOrders,
    required this.canCancelOrders,
    required this.onSessionExpired,
  });

  final int orderId;
  final String orderNumber;
  final DateTime? firstKnownActivity;
  final MobileOrderRepository repository;
  final PendingOrderDraftStore pendingDraftStore;
  final int userId;
  final ValueChanged<int> onRecoverPendingOrder;
  final MobileOrderLiveConnection liveConnection;
  final VoidCallback onBack;
  final VoidCallback onOrderCompleted;
  final ValueChanged<RestaurantTable> onEditOrder;
  final bool canAddItems;
  final bool canEditOrders;
  final bool canCancelOrders;
  final Future<void> Function() onSessionExpired;

  @override
  State<OrderProgressScreen> createState() => _OrderProgressScreenState();
}

class _OrderProgressScreenState extends State<OrderProgressScreen>
    with WidgetsBindingObserver {
  static const _refreshInterval = Duration(seconds: 12);

  Timer? _pollTimer;
  OrderStatusSnapshot? _snapshot;
  DateTime? _firstKnownActivity;
  String? _refreshError;
  bool _isLoading = true;
  bool _isRefreshing = false;
  bool _isCompleting = false;
  bool _isCancelling = false;
  bool _isModifying = false;
  bool _isInForeground = true;
  bool _liveConnected = false;
  PendingQuantityAddition? _pendingAddition;
  bool _restoringAddition = true;
  String? _additionRestoreError;
  String? _unconfirmedCancellation;
  bool _checkingCancellation = true;
  bool _cancellationReadFailed = false;
  bool _cancellationOrderRemembered = false;

  bool get _previousDay =>
      isFromPreviousRestaurantDay(widget.firstKnownActivity) ||
      isFromPreviousRestaurantDay(_firstKnownActivity);

  bool get _canAddItems => widget.canAddItems && !_previousDay;

  bool get _hasUnreviewedCancellation =>
      _checkingCancellation ||
      _cancellationReadFailed ||
      _unconfirmedCancellation != null;

  bool get _hasUnresolvedAddition =>
      _restoringAddition ||
      _pendingAddition != null ||
      _additionRestoreError != null;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _refresh();
    unawaited(_restoreQuantityAddition());
    unawaited(_loadUnconfirmedCancellation());
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
        final activity = latest.firstKnownActivityAt;
        final cached = _firstKnownActivity;
        if (activity != null && (cached == null || activity.isBefore(cached))) {
          _firstKnownActivity = activity;
        }
        _snapshot = latest;
        _isLoading = false;
        _refreshError = null;
      });
      if (previous != null && !_previousDay) {
        _announceNewReadyItems(previous, latest);
      }
      if (latest.items.any((item) => item.cancellations.isNotEmpty)) {
        unawaited(_rememberCancellationOrder());
      }
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

  Future<void> _rememberCancellationOrder() async {
    if (_cancellationOrderRemembered) return;
    _cancellationOrderRemembered = true;
    try {
      await widget.pendingDraftStore.rememberCancellationOrder(
        widget.userId,
        widget.orderId,
      );
    } catch (_) {
      _cancellationOrderRemembered = false;
    }
  }

  void _announceNewReadyItems(
    OrderStatusSnapshot previous,
    OrderStatusSnapshot latest,
  ) {
    final previousStatuses = {
      for (final item in previous.items)
        item.id: item.effectiveStatus.toLowerCase(),
    };
    for (final item in latest.items) {
      if (item.effectiveStatus.toLowerCase() == 'ready' &&
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
    if (!widget.canEditOrders ||
        _hasUnresolvedAddition ||
        _hasUnreviewedCancellation ||
        _isModifying) {
      return false;
    }
    final orderStatus = snapshot.orderStatus.trim().toLowerCase();
    if (orderStatus != 'ready' && orderStatus != 'served') return false;
    if (snapshot.items.any(
      (item) =>
          item.pendingCancellationQuantity > 0 ||
          (!item.isFullyCancelled &&
              item.effectiveStatus.trim().toLowerCase() != 'served'),
    )) {
      return false;
    }
    if (snapshot.tickets.isEmpty) return false;
    return snapshot.tickets.every((ticket) {
      final status = ticket.status.trim().toLowerCase();
      return status == 'ready' || status == 'served' || status == 'cancelled';
    });
  }

  Future<void> _completeOrder(OrderStatusSnapshot snapshot) async {
    if (_isCompleting || _isCancelling || !_canCompleteOrder(snapshot)) return;

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Complete this order?'),
        content: Text(
          'Confirm the guests have left and ${_tableLabel(snapshot.tableName)} is clear. '
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
    final latest = _snapshot;
    if (_isCompleting ||
        _isCancelling ||
        latest == null ||
        !_canCompleteOrder(latest)) {
      return;
    }

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
          'Order completed. ${_tableLabel(snapshot.tableName)} is available.',
        ),
      ),
    );
    widget.onOrderCompleted();
  }

  Future<void> _cancelOrder({OrderItemProgress? item}) async {
    if (!widget.canCancelOrders ||
        _hasUnreviewedCancellation ||
        _hasUnresolvedAddition ||
        _isCancelling ||
        _isModifying ||
        _isCompleting ||
        _snapshot == null) {
      return;
    }
    final cancellation = await showDialog<_CancellationDraft>(
      context: context,
      builder: (_) => _CancellationReasonDialog(
        title: item == null ? 'Cancel this order?' : 'Cancel ${item.name}?',
        maxQuantity: item?.cancellableQuantity,
      ),
    );
    if (cancellation == null || !mounted || item?.isFullyCancelled == true) {
      return;
    }

    await _submitCancellation(
      reason: cancellation.reason,
      item: item,
      quantity: cancellation.quantity ?? item?.cancellableQuantity,
    );
  }

  Future<void> _adjustItemQuantity(OrderItemProgress selectedItem) async {
    final snapshot = _snapshot;
    if (snapshot == null ||
        _hasUnresolvedAddition ||
        _isTerminal(snapshot.orderStatus) ||
        _isModifying ||
        _isCancelling ||
        _isCompleting) {
      return;
    }
    OrderItemProgress? foundItem;
    for (final candidate in snapshot.items) {
      if (candidate.id == selectedItem.id) {
        foundItem = candidate;
        break;
      }
    }
    if (foundItem == null || foundItem.pendingCancellationQuantity > 0) return;
    final item = foundItem;

    final activeQuantity = (item.quantity - item.cancelledQuantity).clamp(
      0,
      item.quantity,
    );
    if (activeQuantity == 0) return;
    final canIncrease =
        _canAddItems && snapshot.tableId != null && item.menuItemId != null;
    final canDecrease =
        widget.canCancelOrders &&
        !_hasUnreviewedCancellation &&
        item.cancellableQuantity > 0;
    if (!canIncrease && !canDecrease) return;

    final adjustment = await showDialog<_QuantityAdjustmentDraft>(
      context: context,
      builder: (_) => _OrderQuantityAdjustmentDialog(
        itemName: item.name,
        activeQuantity: activeQuantity,
        canAdd: canIncrease,
        maximumReduction: canDecrease ? item.cancellableQuantity : 0,
      ),
    );
    if (adjustment == null || !mounted) return;

    if (adjustment.isAddition) {
      if (!_canAddItems) return;
      final addition = PendingQuantityAddition(
        orderId: snapshot.id,
        tableId: snapshot.tableId!,
        menuItemId: item.menuItemId!,
        quantity: adjustment.quantity,
        instructions: item.instructions,
        idempotencyKey: widget.repository.createIdempotencyKey(),
        dishName: item.name,
      );
      setState(() => _isModifying = true);
      try {
        await widget.pendingDraftStore.writeQuantityAddition(
          widget.userId,
          addition,
        );
        if (!mounted) return;
        setState(() => _pendingAddition = addition);
      } catch (_) {
        if (mounted) {
          _showCompletionError(
            'Could not save the dish addition. No new request was sent.',
          );
        }
        return;
      } finally {
        if (mounted) setState(() => _isModifying = false);
      }
      await _sendPendingAddition();
      return;
    }

    await _submitCancellation(
      reason: adjustment.reason!,
      item: item,
      quantity: adjustment.quantity,
    );
  }

  Future<void> _loadUnconfirmedCancellation() async {
    setState(() => _checkingCancellation = true);
    try {
      final saved = await widget.pendingDraftStore.readUnconfirmedCancellation(
        widget.userId,
        widget.orderId,
      );
      if (!mounted) return;
      setState(() {
        _unconfirmedCancellation = saved;
        _cancellationReadFailed = false;
      });
    } catch (_) {
      if (mounted) setState(() => _cancellationReadFailed = true);
    } finally {
      if (mounted) setState(() => _checkingCancellation = false);
    }
  }

  Future<void> _submitCancellation({
    required String reason,
    OrderItemProgress? item,
    int? quantity,
  }) async {
    if (!widget.canCancelOrders ||
        _hasUnreviewedCancellation ||
        _hasUnresolvedAddition ||
        _isCancelling ||
        _isCompleting ||
        _isModifying ||
        _isTerminal(_snapshot?.orderStatus)) {
      return;
    }
    final orderId = widget.orderId;
    final userId = widget.userId;
    final store = widget.pendingDraftStore;
    final target = item == null
        ? 'Entire order'
        : '$quantity portions of ${item.name}';
    final description = '$target. Reason: $reason';
    setState(() => _isCancelling = true);
    try {
      // Save the guard before sending. A restart cannot safely replay this API.
      try {
        await store.saveUnconfirmedCancellation(userId, orderId, description);
      } catch (_) {
        if (mounted) {
          await _loadUnconfirmedCancellation();
          if (mounted) {
            _showCompletionError(
              'Could not save the cancellation. Nothing was sent.',
            );
          }
        }
        return;
      }
      if (!mounted) return;
      setState(() => _unconfirmedCancellation = description);
      await _rememberCancellationOrder();
      if (!mounted) return;

      String message;
      try {
        message = await widget.repository.requestCancellation(
          orderId: orderId,
          reason: reason,
          cancelAll: item == null,
          items: item == null
              ? const []
              : [
                  {'order_item_id': item.id, 'quantity': quantity!},
                ],
        );
      } on ApiException catch (error) {
        if (!const {401, 403, 404, 422}.contains(error.statusCode)) rethrow;
        await store.clearUnconfirmedCancellation(userId, orderId);
        if (!mounted) return;
        setState(() => _unconfirmedCancellation = null);
        if (error.requiresSignIn) {
          await widget.onSessionExpired();
        } else {
          _showCompletionError(error.message);
          await _refresh(quiet: true);
        }
        return;
      }
      await store.clearUnconfirmedCancellation(userId, orderId);
      if (!mounted) return;
      setState(() => _unconfirmedCancellation = null);
      _showCompletionError(message);
      await _refresh(quiet: true);
    } catch (_) {
      if (mounted) {
        _showCompletionError(
          'Cancellation unconfirmed. Check the order history before sending another.',
        );
        await _refresh(quiet: true);
      }
    } finally {
      if (mounted) setState(() => _isCancelling = false);
    }
  }

  Future<void> _acknowledgeCancellation() async {
    if (_isCancelling || _unconfirmedCancellation == null) return;
    final reviewed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Resolve unconfirmed cancellation'),
        content: const Text(
          'Confirm the outcome in the order history or with a manager before '
          'clearing this notice. Clearing it does not send a cancellation.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('Keep notice'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: const Text('I confirmed the outcome'),
          ),
        ],
      ),
    );
    if (!mounted || reviewed != true || _isCancelling) return;
    setState(() => _isCancelling = true);
    try {
      await widget.pendingDraftStore.clearUnconfirmedCancellation(
        widget.userId,
        widget.orderId,
      );
      if (mounted) setState(() => _unconfirmedCancellation = null);
    } catch (_) {
      if (mounted) _showCompletionError('Could not clear the saved notice.');
    } finally {
      if (mounted) setState(() => _isCancelling = false);
    }
  }

  Future<void> _restoreQuantityAddition() async {
    if (_isModifying) return;
    if (mounted) {
      setState(() {
        _restoringAddition = true;
        _additionRestoreError = null;
      });
    }
    try {
      final addition = await widget.pendingDraftStore.readQuantityAddition(
        widget.userId,
      );
      if (!mounted) return;
      setState(() {
        _pendingAddition = addition;
        _restoringAddition = false;
      });
      if (addition?.orderId == widget.orderId) await _sendPendingAddition();
    } catch (_) {
      if (mounted) {
        setState(() {
          _restoringAddition = false;
          _additionRestoreError = 'Could not read the saved dish addition. Retry before changing this order.';
        });
      }
    }
  }

  Future<void> _sendPendingAddition() async {
    final addition = _pendingAddition;
    if (addition == null ||
        _isModifying ||
        addition.orderId != widget.orderId) {
      return;
    }
    setState(() => _isModifying = true);
    try {
      await widget.repository.increaseOrderItemQuantity(
        orderId: addition.orderId,
        tableId: addition.tableId,
        menuItemId: addition.menuItemId,
        quantity: addition.quantity,
        instructions: addition.instructions,
        idempotencyKey: addition.idempotencyKey,
      );
      await widget.pendingDraftStore.clearQuantityAddition(
        widget.userId,
        addition.idempotencyKey,
      );
      if (!mounted) return;
      setState(() => _pendingAddition = null);
      await _refresh(quiet: true);
      if (mounted) {
        _showCompletionError(
          '${addition.quantity} extra ${addition.dishName} portion(s) confirmed.',
        );
      }
    } on ApiException catch (error) {
      // An authentication failure can be retried by this same staff account.
      // Server failures and transport failures leave the saved request untouched.
      if (error.requiresSignIn) {
        if (mounted) await widget.onSessionExpired();
        return;
      }
      if (error.statusCode < 500 &&
          error.statusCode != 408 &&
          error.statusCode != 409 &&
          error.statusCode != 429) {
        await widget.pendingDraftStore.clearQuantityAddition(
          widget.userId,
          addition.idempotencyKey,
        );
        if (mounted) setState(() => _pendingAddition = null);
      }
      if (mounted) _showCompletionError(error.message);
    } on ApiTransportException catch (error) {
      if (mounted) {
        _showCompletionError(
          '${error.message} Check the saved addition to confirm its result.',
        );
      }
    } catch (_) {
      if (mounted) {
        _showCompletionError(
          'The dish addition is unconfirmed. Check the saved addition to safely retry.',
        );
      }
    } finally {
      if (mounted) setState(() => _isModifying = false);
    }
  }

  Future<void> _editItemInstructions(OrderItemProgress item) async {
    if (!widget.canEditOrders ||
        _hasUnresolvedAddition ||
        _isModifying ||
        _isCancelling ||
        _isCompleting ||
        !item.canModify) {
      return;
    }
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

  Future<void> _openOrderEditor(OrderStatusSnapshot snapshot) async {
    if (_hasUnresolvedAddition) return;
    if (!_canAddItems && !widget.canEditOrders && !widget.canCancelOrders) {
      return;
    }
    final action = await showModalBottomSheet<_EditOrderAction>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: AppColors.canvas,
      builder: (_) => _EditOrderSheet(
        snapshot: snapshot,
        canAddItems: _canAddItems,
        canEditInstructions: widget.canEditOrders,
        canCancelItems: widget.canCancelOrders && !_hasUnreviewedCancellation,
      ),
    );
    if (action == null || !mounted) return;

    switch (action.type) {
      case _EditOrderActionType.addItems:
        if (!_canAddItems) return;
        final tableId = snapshot.tableId;
        if (tableId == null) {
          _showCompletionError('This order is missing its table information.');
          return;
        }
        widget.onEditOrder(
          RestaurantTable(
            id: tableId,
            name: snapshot.tableName,
            seatingCapacity: 0,
            status: TableStatus.occupied,
            activeOrderId: snapshot.id,
            activeOrderNumber: snapshot.orderNumber,
          ),
        );
      case _EditOrderActionType.editInstructions:
        final item = action.item;
        if (item != null) await _editItemInstructions(item);
      case _EditOrderActionType.cancelItem:
        final item = action.item;
        if (item != null) await _cancelOrder(item: item);
      case _EditOrderActionType.adjustQuantity:
        final item = action.item;
        if (item != null) await _adjustItemQuantity(item);
    }
  }

  Future<void> _markItemServed(OrderItemProgress item) async {
    if (!widget.canEditOrders ||
        _hasUnresolvedAddition ||
        item.isFullyCancelled ||
        !item.canMarkServed ||
        _isModifying ||
        _isCancelling ||
        _isCompleting) {
      return;
    }
    setState(() => _isModifying = true);
    try {
      await widget.repository.markItemServed(
        orderId: widget.orderId,
        orderItemId: item.id,
      );
      await _refresh();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('${item.name} marked as served.')),
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
      if (mounted) _showCompletionError('Could not mark this dish as served.');
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
          onPressed: _isCancelling ? null : widget.onBack,
          tooltip: 'Back to tables',
          icon: const Icon(Icons.arrow_back_rounded),
        ),
        title: Text(_previousDay ? 'Previous-day order' : 'Order progress'),
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
                  if (_previousDay) ...[
                    const SizedBox(height: 12),
                    const Text(
                      'This table can take a new order once the open order is completed or fully cancelled.',
                    ),
                  ],
                  if (_cancellationReadFailed) ...[
                    const SizedBox(height: 12),
                    const Text(
                      'Could not check for an unconfirmed cancellation.',
                    ),
                    TextButton(
                      onPressed: _checkingCancellation
                          ? null
                          : _loadUnconfirmedCancellation,
                      child: const Text('Check saved cancellation'),
                    ),
                  ],
                  if (_unconfirmedCancellation != null && !_isCancelling) ...[
                    const SizedBox(height: 12),
                    Text(
                      'Cancellation unconfirmed. $_unconfirmedCancellation\n'
                      'Refresh the order and check each affected dish’s cancellation history.',
                    ),
                    TextButton(
                      onPressed: _acknowledgeCancellation,
                      child: const Text('Resolve cancellation notice'),
                    ),
                  ],
                  if (_hasUnresolvedAddition) ...[
                    const SizedBox(height: 12),
                    Text(
                      _additionRestoreError ??
                          (_restoringAddition
                              ? 'Checking saved dish additions…'
                              : '${_pendingAddition!.quantity} extra ${_pendingAddition!.dishName} portion(s) are unconfirmed. Confirm the saved request before changing this order.'),
                    ),
                    if (!_restoringAddition)
                      TextButton.icon(
                        onPressed: _isModifying
                            ? null
                            : () {
                                final addition = _pendingAddition;
                                if (addition == null) {
                                  unawaited(_restoreQuantityAddition());
                                } else if (addition.orderId != widget.orderId) {
                                  widget.onRecoverPendingOrder(
                                    addition.orderId,
                                  );
                                } else {
                                  unawaited(_sendPendingAddition());
                                }
                              },
                        icon: const Icon(Icons.sync_rounded),
                        label: Text(
                          _isModifying
                              ? 'Confirming addition…'
                              : 'Check saved addition',
                        ),
                      ),
                  ],
                  if (!_isTerminal(snapshot.orderStatus) &&
                      (_canAddItems ||
                          widget.canEditOrders ||
                          widget.canCancelOrders)) ...[
                    const SizedBox(height: 12),
                    OutlinedButton.icon(
                      onPressed:
                          _hasUnresolvedAddition ||
                              _isModifying ||
                              _isCancelling ||
                              _isCompleting
                          ? null
                          : () => _openOrderEditor(snapshot),
                      icon: const Icon(Icons.edit_rounded),
                      label: const Text('Edit order'),
                    ),
                  ],
                  if (widget.canCancelOrders &&
                      !_isTerminal(snapshot.orderStatus) &&
                      snapshot.items.any(
                        (item) => item.cancellableQuantity > 0,
                      )) ...[
                    const SizedBox(height: 12),
                    OutlinedButton.icon(
                      onPressed:
                          _hasUnresolvedAddition ||
                              _hasUnreviewedCancellation ||
                              _isCancelling ||
                              _isModifying ||
                              _isCompleting
                          ? null
                          : () => _cancelOrder(),
                      icon: const Icon(Icons.cancel_outlined),
                      label: Text(
                        _isCancelling ? 'Cancelling…' : 'Cancel order',
                      ),
                    ),
                  ],
                  if (snapshot.items.any(
                    (item) => item.pendingCancellationQuantity > 0,
                  )) ...[
                    const SizedBox(height: 12),
                    Container(
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: const Color(0xFFFFF4DE),
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: const Color(0xFFE8D4A8)),
                      ),
                      child: const Text(
                        'This order cannot be completed while a cancellation is waiting for manager approval.',
                        style: TextStyle(
                          color: Color(0xFF76551C),
                          fontSize: 12,
                          height: 1.4,
                        ),
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
                          FilledButton.icon(
                            onPressed:
                                _isCompleting || _isModifying || _isCancelling
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
                          if (widget.canEditOrders &&
                              item.canMarkServed &&
                              !item.isFullyCancelled)
                            Align(
                              alignment: Alignment.centerRight,
                              child: TextButton.icon(
                                onPressed:
                                    _hasUnresolvedAddition || _isModifying
                                    ? null
                                    : () => _markItemServed(item),
                                icon: const Icon(Icons.room_service_outlined),
                                label: Text(
                                  _isModifying ? 'Updating…' : 'Mark served',
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
            _tableLabel(snapshot.tableName),
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
                    formatCancellationRecord(cancellation),
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
          _StatusPill(status: item.effectiveStatus),
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
      'preparing' || 'accepted' || 'partially_ready' => const Color(0xFF536A9D),
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
    'partially_ready' => 'Partially Ready',
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

class _EditOrderSheet extends StatelessWidget {
  const _EditOrderSheet({
    required this.snapshot,
    required this.canAddItems,
    required this.canEditInstructions,
    required this.canCancelItems,
  });

  final OrderStatusSnapshot snapshot;
  final bool canAddItems;
  final bool canEditInstructions;
  final bool canCancelItems;

  @override
  Widget build(BuildContext context) => SafeArea(
    top: false,
    child: SizedBox(
      height: MediaQuery.sizeOf(context).height * 0.86,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(18, 14, 18, 18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Center(
              child: Container(
                width: 38,
                height: 4,
                decoration: BoxDecoration(
                  color: AppColors.line,
                  borderRadius: BorderRadius.circular(3),
                ),
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: [
                const Expanded(
                  child: Text(
                    'Edit order',
                    style: TextStyle(fontSize: 19, fontWeight: FontWeight.w800),
                  ),
                ),
                IconButton(
                  onPressed: () => Navigator.of(context).pop(),
                  tooltip: 'Close editor',
                  icon: const Icon(Icons.close_rounded),
                ),
              ],
            ),
            Text(
              'Add dishes as new kitchen tickets, edit instructions before preparation, or reduce/cancel a dish with a required reason. Stock and preparation rules still apply, and changes are recorded.',
              style: Theme.of(context).textTheme.bodySmall
                  ?.copyWith(color: AppColors.mutedInk, height: 1.4),
            ),
            const SizedBox(height: 14),
            if (canAddItems) ...[
              FilledButton.icon(
                onPressed: snapshot.tableId == null
                    ? null
                    : () => Navigator.of(context).pop(
                        const _EditOrderAction(_EditOrderActionType.addItems),
                      ),
                icon: const Icon(Icons.add_shopping_cart_rounded),
                label: const Text('Add items to this order'),
              ),
              if (snapshot.tableId == null) ...[
                const SizedBox(height: 6),
                const Text(
                  'Items cannot be added because the order has no table assigned.',
                  style: TextStyle(color: AppColors.mutedInk, fontSize: 11),
                ),
              ],
            ],
            const SizedBox(height: 18),
            Text('DISHES', style: Theme.of(context).textTheme.labelMedium),
            const SizedBox(height: 8),
            Expanded(
              child: snapshot.items.isEmpty
                  ? const Center(child: Text('This order has no dishes.'))
                  : ListView.separated(
                      itemCount: snapshot.items.length,
                      separatorBuilder: (_, _) => const SizedBox(height: 9),
                      itemBuilder: (context, index) => _EditableOrderItem(
                        item: snapshot.items[index],
                        canEditInstructions: canEditInstructions,
                        canCancel: canCancelItems,
                        canAddItems: canAddItems && snapshot.tableId != null,
                      ),
                    ),
            ),
          ],
        ),
      ),
    ),
  );
}

class _EditableOrderItem extends StatelessWidget {
  const _EditableOrderItem({
    required this.item,
    required this.canEditInstructions,
    required this.canCancel,
    required this.canAddItems,
  });

  final OrderItemProgress item;
  final bool canEditInstructions;
  final bool canCancel;
  final bool canAddItems;

  @override
  Widget build(BuildContext context) {
    final activeQuantity = (item.quantity - item.cancelledQuantity).clamp(
      0,
      item.quantity,
    );
    final mayEditInstructions =
        !item.isFullyCancelled && canEditInstructions && item.canModify;
    final mayCancel =
        !item.isFullyCancelled && canCancel && item.cancellableQuantity > 0;
    final mayAdjustQuantity =
        item.pendingCancellationQuantity == 0 &&
        activeQuantity > 0 &&
        ((canAddItems && item.menuItemId != null) || mayCancel);
    final hasActions = mayEditInstructions || mayCancel || mayAdjustQuantity;

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  item.name,
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
              ),
              _StatusPill(status: item.effectiveStatus),
            ],
          ),
          const SizedBox(height: 5),
          Text(
            '$activeQuantity active of ${item.quantity} ordered',
            style: const TextStyle(color: AppColors.mutedInk, fontSize: 12),
          ),
          if (item.instructions?.trim().isNotEmpty == true) ...[
            const SizedBox(height: 5),
            Text(
              item.instructions!,
              style: const TextStyle(color: AppColors.mutedInk, fontSize: 12),
            ),
          ],
          if (item.pendingCancellationQuantity > 0) ...[
            const SizedBox(height: 7),
            const Text(
              'Cancellation waiting for approval',
              style: TextStyle(
                color: Color(0xFF8B641D),
                fontSize: 11,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
          if (hasActions) ...[
            const SizedBox(height: 10),
            Wrap(
              spacing: 8,
              runSpacing: 4,
              children: [
                if (mayEditInstructions)
                  OutlinedButton.icon(
                    onPressed: () => Navigator.of(context).pop(
                      _EditOrderAction(
                        _EditOrderActionType.editInstructions,
                        item: item,
                      ),
                    ),
                    icon: const Icon(Icons.edit_note_rounded, size: 18),
                    label: const Text('Edit instructions'),
                  ),
                if (mayAdjustQuantity)
                  OutlinedButton.icon(
                    onPressed: () => Navigator.of(context).pop(
                      _EditOrderAction(
                        _EditOrderActionType.adjustQuantity,
                        item: item,
                      ),
                    ),
                    icon: const Icon(Icons.tune_rounded, size: 18),
                    label: const Text('Adjust quantity'),
                  ),
                if (mayCancel)
                  TextButton.icon(
                    onPressed: () => Navigator.of(context).pop(
                      _EditOrderAction(
                        _EditOrderActionType.cancelItem,
                        item: item,
                      ),
                    ),
                    icon: const Icon(Icons.remove_circle_outline, size: 18),
                    label: const Text('Cancel item'),
                  ),
              ],
            ),
          ] else if (activeQuantity > 0) ...[
            const SizedBox(height: 7),
            const Text(
              'This dish can no longer be modified.',
              style: TextStyle(color: AppColors.mutedInk, fontSize: 11),
            ),
          ],
        ],
      ),
    );
  }
}

enum _EditOrderActionType {
  addItems,
  editInstructions,
  cancelItem,
  adjustQuantity,
}

class _EditOrderAction {
  const _EditOrderAction(this.type, {this.item});

  final _EditOrderActionType type;
  final OrderItemProgress? item;
}

class _OrderQuantityAdjustmentDialog extends StatefulWidget {
  const _OrderQuantityAdjustmentDialog({
    required this.itemName,
    required this.activeQuantity,
    required this.canAdd,
    required this.maximumReduction,
  });

  final String itemName;
  final int activeQuantity;
  final bool canAdd;
  final int maximumReduction;

  @override
  State<_OrderQuantityAdjustmentDialog> createState() =>
      _OrderQuantityAdjustmentDialogState();
}

class _OrderQuantityAdjustmentDialogState
    extends State<_OrderQuantityAdjustmentDialog> {
  late bool _isAdding = widget.canAdd;
  int _quantity = 1;
  final _reasonController = TextEditingController();

  bool get _canSubmit =>
      _quantity > 0 && (_isAdding || _reasonController.text.trim().length >= 3);

  int get _maximumQuantity => _isAdding ? 99 : widget.maximumReduction;

  @override
  void dispose() {
    _reasonController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    scrollable: true,
    title: Text('Adjust ${widget.itemName}'),
    content: Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          'This existing line has ${widget.activeQuantity} active portion(s).',
          style: Theme.of(context).textTheme.bodyMedium,
        ),
        const SizedBox(height: 12),
        if (widget.canAdd && widget.maximumReduction > 0) ...[
          SegmentedButton<bool>(
            segments: const [
              ButtonSegment(value: true, label: Text('Add extra')),
              ButtonSegment(value: false, label: Text('Reduce line')),
            ],
            selected: {_isAdding},
            onSelectionChanged: (selection) => setState(() {
              _isAdding = selection.single;
              _quantity = 1;
            }),
          ),
          const SizedBox(height: 12),
        ],
        Text(
          _isAdding
              ? 'Extra portions to add'
              : 'Portions to cancel from this line',
        ),
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            IconButton.filledTonal(
              tooltip: 'One fewer portion',
              onPressed: _quantity <= 1
                  ? null
                  : () => setState(() => _quantity--),
              icon: const Icon(Icons.remove_rounded),
            ),
            const SizedBox(width: 18),
            Semantics(
              label:
                  '$_quantity ${_isAdding ? 'extra portions to add' : 'portions to cancel'}',
              child: Text(
                '$_quantity',
                style: Theme.of(context).textTheme.headlineSmall
                    ?.copyWith(fontWeight: FontWeight.w800),
              ),
            ),
            const SizedBox(width: 18),
            IconButton.filledTonal(
              tooltip: 'One more portion',
              onPressed: _quantity >= _maximumQuantity
                  ? null
                  : () => setState(() => _quantity++),
              icon: const Icon(Icons.add_rounded),
            ),
          ],
        ),
        const SizedBox(height: 8),
        if (!_isAdding) ...[
          Text(
            'This line will have ${widget.activeQuantity - _quantity} active portion(s) after cancellation approval, if required.',
          ),
          const SizedBox(height: 8),
          const Text(
            'The reduction is submitted as a cancellation. A reason is required; approval and stock handling follow this branch’s policy and preparation status.',
            style: TextStyle(fontSize: 12, height: 1.4),
          ),
          const SizedBox(height: 10),
          TextField(
            controller: _reasonController,
            autofocus: true,
            minLines: 2,
            maxLines: 4,
            maxLength: 500,
            decoration: const InputDecoration(
              labelText: 'Reason for quantity reduction',
              hintText: 'Required for the cancellation history',
            ),
            onChanged: (_) => setState(() {}),
          ),
        ] else ...[
          const Text(
            'These extra portions appear as a new order line and new kitchen tickets. The existing line stays as shown above. Current availability and stock are checked before adding.',
            style: TextStyle(fontSize: 12, height: 1.4),
          ),
        ],
      ],
    ),
    actions: [
      TextButton(
        onPressed: () => Navigator.of(context).pop(),
        child: const Text('Keep order'),
      ),
      FilledButton(
        onPressed: _canSubmit
            ? () => Navigator.of(context).pop(
                _QuantityAdjustmentDraft(
                  isAddition: _isAdding,
                  quantity: _quantity,
                  reason: _isAdding ? null : _reasonController.text.trim(),
                ),
              )
            : null,
        child: Text(
          _isAdding ? 'Add $_quantity extra' : 'Cancel $_quantity portion(s)',
        ),
      ),
    ],
  );
}

class _QuantityAdjustmentDraft {
  const _QuantityAdjustmentDraft({
    required this.isAddition,
    required this.quantity,
    this.reason,
  });

  final bool isAddition;
  final int quantity;
  final String? reason;
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
            hintText: 'This reason will be sent to the kitchen and saved in the history.',
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
        child: const Text('Confirm cancellation'),
      ),
    ],
  );
}

class _CancellationDraft {
  const _CancellationDraft({required this.reason, this.quantity});

  final String reason;
  final int? quantity;
}

String _tableLabel(String name) {
  final value = name.trim();
  return value.toLowerCase().startsWith('table ') ? value : 'Table $value';
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
