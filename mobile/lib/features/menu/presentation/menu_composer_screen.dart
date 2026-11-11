import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';

import '../../../app/app_theme.dart';
import '../../../core/network/mobile_api_client.dart';
import '../../../core/network/mobile_order_live_connection.dart';
import '../../tables/domain/floor_table.dart';
import '../data/menu_repository.dart';
import '../domain/menu.dart';
import '../../orders/data/mobile_order_repository.dart';
import '../../orders/data/pending_order_draft_store.dart';
import '../../orders/domain/order_confirmation.dart';

class MenuComposerScreen extends StatefulWidget {
  const MenuComposerScreen({
    super.key,
    required this.table,
    required this.repository,
    required this.orderRepository,
    required this.pendingDraftStore,
    required this.liveConnection,
    required this.resolveAssetUri,
    required this.onBack,
    required this.onOrderSubmitted,
    required this.onSignOut,
    required this.onSessionExpired,
  });

  final RestaurantTable table;
  final MenuRepository repository;
  final MobileOrderRepository orderRepository;
  final PendingOrderDraftStore pendingDraftStore;
  final MobileOrderLiveConnection liveConnection;
  final Uri? Function(String?) resolveAssetUri;
  final VoidCallback onBack;
  final ValueChanged<OrderConfirmation> onOrderSubmitted;
  final Future<void> Function() onSignOut;
  final Future<void> Function() onSessionExpired;

  @override
  State<MenuComposerScreen> createState() => _MenuComposerScreenState();
}

class _MenuComposerScreenState extends State<MenuComposerScreen> {
  final _searchController = TextEditingController();
  final Map<int, CartLine> _cart = {};
  MenuSnapshot? _snapshot;
  int? _selectedCategoryId;
  String _searchText = '';
  String _orderInstructions = '';
  int _guestCount = 1;
  String? _errorMessage;
  bool _isLoading = true;
  bool _isSubmitting = false;
  bool _retryPending = false;
  String? _pendingSignature;
  String? _pendingIdempotencyKey;
  CartDraft? _pendingDraft;
  int? _pendingOrderId;
  bool _draftRestoreAttempted = false;
  Timer? _menuRefreshTimer;
  int? _connectedMenuBranchId;

  @override
  void initState() {
    super.initState();
    _loadMenu();
    _menuRefreshTimer = Timer.periodic(const Duration(seconds: 15), (_) {
      if (mounted && !_retryPending && !_isSubmitting) _loadMenu();
    });
  }

  @override
  void dispose() {
    _searchController.dispose();
    _menuRefreshTimer?.cancel();
    widget.liveConnection.disconnect();
    super.dispose();
  }

  Future<void> _loadMenu() async {
    if (_retryPending || _isSubmitting) return;
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final snapshot = await widget.repository.loadMenu();
      if (!mounted) return;
      final latestById = {for (final dish in snapshot.dishes) dish.id: dish};
      final refreshedCart = <int, CartLine>{};
      var adjustedCart = false;
      for (final oldLine in _cart.values) {
        final latestDish = latestById[oldLine.dish.id];
        if (latestDish == null || !latestDish.isOrderable) {
          adjustedCart = true;
          continue;
        }
        final quantity = oldLine.quantity
            .clamp(0, latestDish.maximumQuantity)
            .toInt();
        if (quantity != oldLine.quantity) adjustedCart = true;
        if (quantity > 0) {
          refreshedCart[latestDish.id] = oldLine.copyWith(
            dish: latestDish,
            quantity: quantity,
          );
        }
      }

      setState(() {
        _snapshot = snapshot;
        _cart
          ..clear()
          ..addAll(refreshedCart);
        if (_selectedCategoryId != null &&
            !snapshot.categories.any(
              (category) => category.id == _selectedCategoryId,
            )) {
          _selectedCategoryId = null;
        }
        _isLoading = false;
      });
      if (_connectedMenuBranchId != snapshot.branchId &&
          widget.liveConnection.isConfigured) {
        _connectedMenuBranchId = snapshot.branchId;
        widget.liveConnection.connectMenu(
          branchId: snapshot.branchId,
          onConnectionChanged: (_) {},
          onMenuChanged: () => _loadMenu(),
        );
      }
      if (adjustedCart && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Menu or stock changed. Your cart was updated.'),
          ),
        );
      }
      if (!_draftRestoreAttempted) {
        _draftRestoreAttempted = true;
        await _restorePendingDraft(snapshot);
      }
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
        _errorMessage =
            'We could not load the menu. Check your connection and retry.';
        _isLoading = false;
      });
    }
  }

  List<MenuDish> get _visibleDishes {
    final snapshot = _snapshot;
    if (snapshot == null) return const [];
    final source = _selectedCategoryId == null
        ? snapshot.dishes
        : snapshot.categories
              .where((category) => category.id == _selectedCategoryId)
              .expand((category) => category.dishes)
              .toList();
    final query = _searchText.trim().toLowerCase();
    if (query.isEmpty) return source;
    return source
        .where(
          (dish) =>
              dish.name.toLowerCase().contains(query) ||
              dish.code.toLowerCase().contains(query) ||
              dish.description?.toLowerCase().contains(query) == true,
        )
        .toList();
  }

  int get _itemCount =>
      _cart.values.fold(0, (sum, line) => sum + line.quantity);

  double get _cartTotal =>
      _cart.values.fold(0, (sum, line) => sum + line.total);

  void _changeQuantity(MenuDish dish, int delta) {
    if (_retryPending || _isSubmitting) return;
    final current = _cart[dish.id];
    if (current == null && delta > 0) {
      if (!dish.isOrderable || dish.maximumQuantity == 0) return;
      setState(() => _cart[dish.id] = CartLine(dish: dish, quantity: 1));
      return;
    }
    if (current == null) return;
    final next = (current.quantity + delta)
        .clamp(0, dish.maximumQuantity)
        .toInt();
    setState(() {
      if (next == 0) {
        _cart.remove(dish.id);
      } else {
        _cart[dish.id] = current.copyWith(dish: dish, quantity: next);
      }
    });
  }

  Future<void> _reviewCart() async {
    final draft = await showModalBottomSheet<CartDraft>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: Colors.transparent,
      builder: (context) => CartReviewSheet(
        tableName: widget.table.name,
        initialLines: _cart.values.toList(growable: false),
        initialInstructions: _orderInstructions,
        initialGuestCount: _guestCount,
        maxGuests: widget.table.seatingCapacity <= 0
            ? 1
            : widget.table.seatingCapacity > 100
            ? 100
            : widget.table.seatingCapacity,
        showGuestCount: widget.table.activeOrderId == null,
      ),
    );
    if (!mounted || draft == null) return;
    setState(() {
      _cart
        ..clear()
        ..addEntries(draft.lines.map((line) => MapEntry(line.dish.id, line)));
      _orderInstructions = draft.orderInstructions;
      _guestCount = draft.guestCount;
    });
    if (draft.lines.isNotEmpty) await _submitDraft(draft);
  }

  Future<void> _retrySubmission() async {
    final draft = _pendingDraft;
    if (draft != null) await _submitDraft(draft, reusePendingKey: true);
  }

  Future<void> _restorePendingDraft(MenuSnapshot snapshot) async {
    final saved = await widget.pendingDraftStore.read();
    if (!mounted || saved == null || saved['table_id'] != widget.table.id) {
      return;
    }
    final rawItems = saved['items'];
    final latestById = {for (final dish in snapshot.dishes) dish.id: dish};
    final lines = <CartLine>[];
    if (rawItems is List) {
      for (final raw in rawItems.whereType<Map<String, dynamic>>()) {
        final id = raw['menu_item_id'];
        final quantity = raw['quantity'];
        final dish = id is num
            ? latestById[id.toInt()] ?? _dishSnapshotFrom(raw['menu_item'])
            : null;
        if (dish == null || quantity is! num || quantity < 1) continue;
        lines.add(
          CartLine(
            dish: dish,
            quantity: quantity.toInt(),
            note: raw['special_instructions'] as String? ?? '',
          ),
        );
      }
    }
    final key = saved['idempotency_key'];
    final signature = saved['signature'];
    if (lines.isEmpty || key is! String || signature is! String) {
      await widget.pendingDraftStore.clear();
      return;
    }
    final orderId = saved['order_id'];
    final draft = CartDraft(
      lines: lines,
      orderInstructions: saved['order_instructions'] as String? ?? '',
      guestCount: (saved['guest_count'] as num?)?.toInt() ?? 1,
    );
    setState(() {
      _cart
        ..clear()
        ..addEntries(lines.map((line) => MapEntry(line.dish.id, line)));
      _orderInstructions = draft.orderInstructions;
      _guestCount = draft.guestCount;
      _pendingDraft = draft;
      _pendingIdempotencyKey = key;
      _pendingSignature = signature;
      _pendingOrderId = orderId is num ? orderId.toInt() : null;
      _retryPending = true;
    });
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text(
          'An unconfirmed order was restored. Retry the same submission to check its result.',
        ),
      ),
    );
  }

  String _signatureFor(CartDraft draft, {required int? targetOrderId}) {
    final items = [...draft.lines]
      ..sort((a, b) => a.dish.id.compareTo(b.dish.id));
    return jsonEncode({
      'table_id': widget.table.id,
      'guest_count': targetOrderId == null ? draft.guestCount : null,
      'special_instructions': draft.orderInstructions,
      'items': items
          .map(
            (line) => {
              'menu_item_id': line.dish.id,
              'quantity': line.quantity,
              'special_instructions': line.note,
            },
          )
          .toList(growable: false),
    });
  }

  Future<void> _submitDraft(
    CartDraft draft, {
    bool reusePendingKey = false,
  }) async {
    if (_isSubmitting || draft.lines.isEmpty) return;
    var targetOrderId = reusePendingKey && _pendingIdempotencyKey != null
        ? _pendingOrderId
        : widget.table.activeOrderId;
    var signature = _signatureFor(draft, targetOrderId: targetOrderId);
    if (!reusePendingKey ||
        _pendingSignature != signature ||
        _pendingIdempotencyKey == null) {
      targetOrderId = widget.table.activeOrderId;
      signature = _signatureFor(draft, targetOrderId: targetOrderId);
      _pendingSignature = signature;
      _pendingIdempotencyKey = widget.orderRepository.createIdempotencyKey();
      _pendingOrderId = targetOrderId;
    }
    _pendingDraft = draft;
    await widget.pendingDraftStore.write({
      'table_id': widget.table.id,
      'order_id': targetOrderId,
      'idempotency_key': _pendingIdempotencyKey,
      'signature': signature,
      'guest_count': draft.guestCount,
      'order_instructions': draft.orderInstructions,
      'items': draft.lines
          .map(
            (line) => {
              'menu_item_id': line.dish.id,
              'quantity': line.quantity,
              'special_instructions': line.note,
              'menu_item': {
                'id': line.dish.id,
                'category_id': line.dish.categoryId,
                'name': line.dish.name,
                'code': line.dish.code,
                'price': line.dish.price,
                'menu_type': line.dish.menuType,
                'availability': _availabilityValue(line.dish.availability),
                'stock_tracking_enabled': line.dish.stockTrackingEnabled,
                'stock_configured': line.dish.stockConfigured,
                'is_orderable': line.dish.isOrderable,
                'description': line.dish.description,
                'image_path': line.dish.imagePath,
                'prepared_stock_quantity': line.dish.preparedStockQuantity,
              },
            },
          )
          .toList(growable: false),
    });

    setState(() {
      _isSubmitting = true;
      _retryPending = false;
    });

    try {
      final confirmation = targetOrderId == null
          ? await widget.orderRepository.createOrder(
              tableId: widget.table.id,
              guestCount: draft.guestCount,
              orderInstructions: draft.orderInstructions,
              lines: draft.lines,
              idempotencyKey: _pendingIdempotencyKey!,
            )
          : await widget.orderRepository.addItems(
              orderId: targetOrderId,
              tableId: widget.table.id,
              orderInstructions: draft.orderInstructions,
              lines: draft.lines,
              idempotencyKey: _pendingIdempotencyKey!,
            );

      await widget.pendingDraftStore.clear();
      if (!mounted) return;
      setState(() {
        _cart.clear();
        _orderInstructions = '';
        _pendingSignature = null;
        _pendingIdempotencyKey = null;
        _pendingDraft = null;
        _pendingOrderId = null;
        _retryPending = false;
      });
      await _showOrderConfirmation(confirmation);
      if (mounted) widget.onOrderSubmitted(confirmation);
    } on ApiException catch (error) {
      if (!mounted) return;
      if (error.requiresSignIn) {
        await widget.pendingDraftStore.clear();
        await widget.onSessionExpired();
        return;
      }
      final uncertain = error.statusCode >= 500;
      setState(() {
        _retryPending = uncertain;
        if (!uncertain) {
          _pendingSignature = null;
          _pendingIdempotencyKey = null;
          _pendingDraft = null;
        }
      });
      if (!uncertain) await widget.pendingDraftStore.clear();
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(error.message)));
    } on ApiTransportException catch (error) {
      if (!mounted) return;
      setState(() => _retryPending = true);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            '${error.message} Retry to safely check the same submission.',
          ),
        ),
      );
    } catch (_) {
      if (!mounted) return;
      setState(() => _retryPending = true);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'The order result is not confirmed. Retry the same submission.',
          ),
        ),
      );
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  MenuDish? _dishSnapshotFrom(Object? value) {
    if (value is! Map<String, dynamic>) return null;
    final id = value['id'];
    final categoryId = value['category_id'];
    final price = value['price'];
    if (id is! num || categoryId is! num || price is! num) return null;
    return MenuDish(
      id: id.toInt(),
      categoryId: categoryId.toInt(),
      name: value['name'] as String? ?? 'Removed menu item',
      code: value['code'] as String? ?? '',
      price: price.toDouble(),
      menuType: value['menu_type'] as String? ?? '',
      availability: menuAvailabilityFromValue(
        value['availability'] as String? ?? 'unknown',
      ),
      stockTrackingEnabled: value['stock_tracking_enabled'] == true,
      stockConfigured: value['stock_configured'] == true,
      isOrderable: false,
      description: value['description'] as String?,
      imagePath: value['image_path'] as String?,
      preparedStockQuantity: (value['prepared_stock_quantity'] as num?)
          ?.toInt(),
    );
  }

  String _availabilityValue(MenuAvailability availability) =>
      switch (availability) {
        MenuAvailability.available => 'available',
        MenuAvailability.temporarilyUnavailable => 'temporarily_unavailable',
        MenuAvailability.soldOut => 'sold_out',
        MenuAvailability.unknown => 'unknown',
      };

  Future<void> _showOrderConfirmation(OrderConfirmation confirmation) {
    return showDialog<void>(
      context: context,
      barrierDismissible: false,
      builder: (context) => AlertDialog(
        icon: const Icon(
          Icons.check_circle_outline_rounded,
          color: Color(0xFF397451),
          size: 40,
        ),
        title: Text('Order ${confirmation.orderNumber} sent'),
        content: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 380),
          child: SingleChildScrollView(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  confirmation.replayed
                      ? 'The earlier request was already accepted; no duplicate was created.'
                      : 'Kitchen tickets were created for Table ${widget.table.name}.',
                ),
                const SizedBox(height: 12),
                Text(
                  'Reference total  ${_money(confirmation.totalAmount)}',
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
                if (confirmation.kotTickets.isNotEmpty) ...[
                  const SizedBox(height: 16),
                  const Text(
                    'KITCHEN TICKETS',
                    style: TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
                      letterSpacing: 0.7,
                    ),
                  ),
                  const SizedBox(height: 7),
                  for (final ticket in confirmation.kotTickets)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 5),
                      child: Text('${ticket.kitchenName}  ·  ${ticket.number}'),
                    ),
                ],
              ],
            ),
          ),
        ),
        actions: [
          FilledButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Back to tables'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final snapshot = _snapshot;
    return Scaffold(
      backgroundColor: AppColors.canvas,
      appBar: AppBar(
        backgroundColor: AppColors.canvas,
        leading: IconButton(
          onPressed: _isSubmitting || _retryPending ? null : widget.onBack,
          tooltip: 'Back to tables',
          icon: const Icon(Icons.arrow_back_rounded),
        ),
        titleSpacing: 0,
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Table ${widget.table.name}',
              style: const TextStyle(fontSize: 16),
            ),
            Text(
              widget.table.activeOrderNumber == null
                  ? 'New order'
                  : 'Add to ${widget.table.activeOrderNumber}',
              style: Theme.of(context).textTheme.bodySmall
                  ?.copyWith(color: AppColors.mutedInk, fontSize: 11),
            ),
          ],
        ),
        actions: [
          IconButton(
            onPressed: _isLoading || _isSubmitting || _retryPending
                ? null
                : _loadMenu,
            tooltip: 'Refresh menu and stock',
            icon: const Icon(Icons.refresh_rounded),
          ),
          IconButton(
            onPressed: _isSubmitting || _retryPending ? null : widget.onSignOut,
            tooltip: 'Sign out',
            icon: const Icon(Icons.logout_rounded),
          ),
          const SizedBox(width: 4),
        ],
      ),
      body: _isLoading && snapshot == null
          ? const Center(child: CircularProgressIndicator())
          : _errorMessage != null && snapshot == null
          ? _MenuErrorState(message: _errorMessage!, onRetry: _loadMenu)
          : snapshot == null
          ? _MenuErrorState(
              message: 'The menu is unavailable for this branch.',
              onRetry: _loadMenu,
            )
          : _buildMenu(snapshot),
      bottomNavigationBar: _cart.isEmpty
          ? null
          : SafeArea(
              top: false,
              minimum: const EdgeInsets.fromLTRB(16, 8, 16, 12),
              child: FilledButton(
                onPressed: _isSubmitting
                    ? null
                    : _retryPending
                    ? _retrySubmission
                    : _reviewCart,
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    if (_isSubmitting)
                      const SizedBox.square(
                        dimension: 18,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: AppColors.white,
                        ),
                      )
                    else
                      const Icon(Icons.shopping_bag_outlined, size: 19),
                    const SizedBox(width: 9),
                    Text(
                      _isSubmitting
                          ? 'Sending order…'
                          : _retryPending
                          ? 'Retry same submission'
                          : 'Review & ${widget.table.activeOrderId == null ? 'submit' : 'add'} $_itemCount ${_itemCount == 1 ? 'item' : 'items'}',
                    ),
                    const Spacer(),
                    Text(_money(_cartTotal)),
                  ],
                ),
              ),
            ),
    );
  }

  Widget _buildMenu(MenuSnapshot snapshot) {
    final dishes = _visibleDishes;
    return RefreshIndicator(
      onRefresh: _loadMenu,
      color: AppColors.terracotta,
      child: CustomScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        slivers: [
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(18, 12, 18, 0),
            sliver: SliverToBoxAdapter(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Build the order',
                    style: Theme.of(context).textTheme.headlineMedium,
                  ),
                  const SizedBox(height: 5),
                  Text(
                    '${snapshot.branchName}  ·  ${widget.table.seatingCapacity} seats',
                    style: Theme.of(context).textTheme.bodyMedium,
                  ),
                  if (_retryPending) ...[
                    const SizedBox(height: 12),
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: const Color(0xFFFFF5E8),
                        borderRadius: BorderRadius.circular(13),
                        border: Border.all(color: const Color(0xFFE9C98F)),
                      ),
                      child: const Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Icon(
                            Icons.info_outline_rounded,
                            color: Color(0xFF9B6418),
                            size: 19,
                          ),
                          SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              'The server has not confirmed this submission. Retry it with the same request so it cannot create a duplicate.',
                              style: TextStyle(
                                color: Color(0xFF76501D),
                                fontSize: 12,
                                height: 1.4,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                  const SizedBox(height: 17),
                  TextField(
                    controller: _searchController,
                    onChanged: (value) => setState(() => _searchText = value),
                    textInputAction: TextInputAction.search,
                    decoration: InputDecoration(
                      hintText: 'Search dishes',
                      prefixIcon: const Icon(Icons.search_rounded),
                      suffixIcon: _searchText.isEmpty
                          ? null
                          : IconButton(
                              onPressed: () {
                                _searchController.clear();
                                setState(() => _searchText = '');
                              },
                              tooltip: 'Clear search',
                              icon: const Icon(Icons.close_rounded),
                            ),
                    ),
                  ),
                  const SizedBox(height: 14),
                  _CategoryChips(
                    categories: snapshot.categories,
                    selectedId: _selectedCategoryId,
                    onSelected: (id) =>
                        setState(() => _selectedCategoryId = id),
                  ),
                  const SizedBox(height: 14),
                  Row(
                    children: [
                      Text(
                        _selectedCategoryId == null
                            ? 'All dishes'
                            : _selectedCategoryName(snapshot),
                        style: Theme.of(context).textTheme.titleMedium,
                      ),
                      const Spacer(),
                      Text(
                        '${dishes.length} ${dishes.length == 1 ? 'dish' : 'dishes'}',
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
          if (dishes.isEmpty)
            const SliverFillRemaining(
              hasScrollBody: false,
              child: _NoDishesState(),
            )
          else
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(16, 10, 16, 20),
              sliver: SliverList.separated(
                itemCount: dishes.length,
                separatorBuilder: (_, _) => const SizedBox(height: 10),
                itemBuilder: (context, index) {
                  final dish = dishes[index];
                  return _DishCard(
                    dish: dish,
                    quantity: _cart[dish.id]?.quantity ?? 0,
                    locked: _retryPending || _isSubmitting,
                    imageUri: widget.resolveAssetUri(dish.imagePath),
                    onAdd: () => _changeQuantity(dish, 1),
                    onRemove: () => _changeQuantity(dish, -1),
                  );
                },
              ),
            ),
        ],
      ),
    );
  }

  String _selectedCategoryName(MenuSnapshot snapshot) {
    for (final category in snapshot.categories) {
      if (category.id == _selectedCategoryId) return category.name;
    }
    return 'Dishes';
  }
}

class _CategoryChips extends StatelessWidget {
  const _CategoryChips({
    required this.categories,
    required this.selectedId,
    required this.onSelected,
  });

  final List<MenuCategory> categories;
  final int? selectedId;
  final ValueChanged<int?> onSelected;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 42,
      child: ListView(
        scrollDirection: Axis.horizontal,
        children: [
          _CategoryChip(
            label: 'All',
            selected: selectedId == null,
            onTap: () => onSelected(null),
          ),
          for (final category in categories)
            _CategoryChip(
              label: category.name,
              selected: selectedId == category.id,
              onTap: () => onSelected(category.id),
            ),
        ],
      ),
    );
  }
}

class _CategoryChip extends StatelessWidget {
  const _CategoryChip({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(right: 8),
      child: ChoiceChip(
        label: Text(label),
        selected: selected,
        onSelected: (_) => onTap(),
        backgroundColor: AppColors.white,
        selectedColor: AppColors.ink,
        labelStyle: TextStyle(
          color: selected ? AppColors.white : AppColors.ink,
          fontSize: 12,
          fontWeight: FontWeight.w600,
        ),
        side: BorderSide(color: selected ? AppColors.ink : AppColors.line),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(22)),
        showCheckmark: false,
        padding: const EdgeInsets.symmetric(horizontal: 8),
      ),
    );
  }
}

class _DishCard extends StatelessWidget {
  const _DishCard({
    required this.dish,
    required this.quantity,
    required this.locked,
    required this.imageUri,
    required this.onAdd,
    required this.onRemove,
  });

  final MenuDish dish;
  final int quantity;
  final bool locked;
  final Uri? imageUri;
  final VoidCallback onAdd;
  final VoidCallback onRemove;

  @override
  Widget build(BuildContext context) {
    final canOrder = dish.isOrderable && dish.maximumQuantity > 0 && !locked;
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColors.line),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _DishImage(uri: imageUri),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: Text(
                        dish.name,
                        style: const TextStyle(
                          fontWeight: FontWeight.w700,
                          fontSize: 15,
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Text(
                      _money(dish.price),
                      style: const TextStyle(fontWeight: FontWeight.w800),
                    ),
                  ],
                ),
                if (dish.description?.isNotEmpty == true) ...[
                  const SizedBox(height: 4),
                  Text(
                    dish.description!,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.bodySmall
                        ?.copyWith(height: 1.35),
                  ),
                ],
                const SizedBox(height: 8),
                Wrap(
                  spacing: 7,
                  runSpacing: 6,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  children: [
                    _FoodTypeLabel(type: dish.menuType),
                    _AvailabilityLabel(dish: dish),
                    if (dish.stockTrackingEnabled) _StockLabel(dish: dish),
                  ],
                ),
                const SizedBox(height: 9),
                Align(
                  alignment: Alignment.centerRight,
                  child: quantity > 0
                      ? _QuantityStepper(
                          quantity: quantity,
                          enabled: canOrder,
                          canIncrement: quantity < dish.maximumQuantity,
                          onAdd: onAdd,
                          onRemove: onRemove,
                        )
                      : FilledButton.tonalIcon(
                          onPressed: canOrder ? onAdd : null,
                          icon: const Icon(Icons.add_rounded, size: 17),
                          label: Text(canOrder ? 'Add' : 'Unavailable'),
                          style: FilledButton.styleFrom(
                            minimumSize: const Size(112, 38),
                            padding: const EdgeInsets.symmetric(horizontal: 12),
                            visualDensity: VisualDensity.compact,
                          ),
                        ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _DishImage extends StatelessWidget {
  const _DishImage({required this.uri});

  final Uri? uri;

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(13),
      child: SizedBox(
        width: 76,
        height: 76,
        child: uri == null
            ? const _ImagePlaceholder()
            : Image.network(
                uri.toString(),
                fit: BoxFit.cover,
                errorBuilder: (_, _, _) => const _ImagePlaceholder(),
                loadingBuilder: (context, child, progress) => progress == null
                    ? child
                    : const _ImagePlaceholder(isLoading: true),
              ),
      ),
    );
  }
}

class _ImagePlaceholder extends StatelessWidget {
  const _ImagePlaceholder({this.isLoading = false});

  final bool isLoading;

  @override
  Widget build(BuildContext context) {
    return ColoredBox(
      color: const Color(0xFFF0EEE8),
      child: Center(
        child: isLoading
            ? const SizedBox.square(
                dimension: 18,
                child: CircularProgressIndicator(strokeWidth: 2),
              )
            : const Icon(
                Icons.restaurant_rounded,
                color: AppColors.mutedInk,
                size: 25,
              ),
      ),
    );
  }
}

class _FoodTypeLabel extends StatelessWidget {
  const _FoodTypeLabel({required this.type});

  final String type;

  @override
  Widget build(BuildContext context) {
    final normalized = type.toLowerCase();
    final color = normalized.contains('egg')
        ? const Color(0xFFB7791F)
        : normalized.contains('non')
        ? AppColors.error
        : const Color(0xFF397451);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 4),
      decoration: BoxDecoration(
        border: Border.all(color: color.withValues(alpha: 0.45)),
        borderRadius: BorderRadius.circular(6),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.circle, size: 7, color: color),
          const SizedBox(width: 4),
          Text(
            type,
            style: TextStyle(
              color: color,
              fontSize: 10,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _AvailabilityLabel extends StatelessWidget {
  const _AvailabilityLabel({required this.dish});

  final MenuDish dish;

  @override
  Widget build(BuildContext context) {
    final label = !dish.stockConfigured
        ? 'Stock not configured'
        : dish.availability.label;
    final color = dish.isOrderable
        ? const Color(0xFF397451)
        : AppColors.mutedInk;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 4),
      decoration: BoxDecoration(
        color: const Color(0xFFF5F4F0),
        borderRadius: BorderRadius.circular(6),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: color,
          fontSize: 10,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}

class _StockLabel extends StatelessWidget {
  const _StockLabel({required this.dish});

  final MenuDish dish;

  @override
  Widget build(BuildContext context) {
    final quantity = dish.preparedStockQuantity;
    final label = !dish.stockConfigured
        ? 'Set stock in menu management'
        : quantity == 0
        ? 'No portions left'
        : '$quantity ${quantity == 1 ? 'portion' : 'portions'} left';
    return Text(
      label,
      style: Theme.of(context).textTheme.bodySmall?.copyWith(
        color: quantity == 0 ? AppColors.error : AppColors.mutedInk,
        fontSize: 10,
      ),
    );
  }
}

class _QuantityStepper extends StatelessWidget {
  const _QuantityStepper({
    required this.quantity,
    required this.enabled,
    required this.canIncrement,
    required this.onAdd,
    required this.onRemove,
  });

  final int quantity;
  final bool enabled;
  final bool canIncrement;
  final VoidCallback onAdd;
  final VoidCallback onRemove;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        IconButton(
          onPressed: onRemove,
          tooltip: 'Decrease quantity',
          visualDensity: VisualDensity.compact,
          style: IconButton.styleFrom(backgroundColor: const Color(0xFFF3F0EA)),
          icon: const Icon(Icons.remove_rounded, size: 18),
        ),
        SizedBox(
          width: 28,
          child: Text(
            '$quantity',
            textAlign: TextAlign.center,
            style: const TextStyle(fontWeight: FontWeight.w700),
          ),
        ),
        IconButton(
          onPressed: enabled && canIncrement ? onAdd : null,
          tooltip: 'Increase quantity',
          visualDensity: VisualDensity.compact,
          style: IconButton.styleFrom(backgroundColor: const Color(0xFFF3F0EA)),
          icon: const Icon(Icons.add_rounded, size: 18),
        ),
      ],
    );
  }
}

class _NoDishesState extends StatelessWidget {
  const _NoDishesState();

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(
              Icons.search_off_rounded,
              size: 42,
              color: AppColors.mutedInk,
            ),
            const SizedBox(height: 12),
            Text(
              'No dishes found',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: 5),
            Text(
              'Try another search or category.',
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodyMedium,
            ),
          ],
        ),
      ),
    );
  }
}

class _MenuErrorState extends StatelessWidget {
  const _MenuErrorState({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(28),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(
              Icons.restaurant_menu_rounded,
              size: 42,
              color: AppColors.mutedInk,
            ),
            const SizedBox(height: 12),
            Text(
              'Menu unavailable',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: 6),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 18),
            FilledButton.icon(
              onPressed: onRetry,
              icon: const Icon(Icons.refresh_rounded),
              label: const Text('Retry'),
            ),
          ],
        ),
      ),
    );
  }
}

class CartReviewSheet extends StatefulWidget {
  const CartReviewSheet({
    super.key,
    required this.tableName,
    required this.initialLines,
    required this.initialInstructions,
    required this.initialGuestCount,
    required this.maxGuests,
    required this.showGuestCount,
  });

  final String tableName;
  final List<CartLine> initialLines;
  final String initialInstructions;
  final int initialGuestCount;
  final int maxGuests;
  final bool showGuestCount;

  @override
  State<CartReviewSheet> createState() => _CartReviewSheetState();
}

class _CartReviewSheetState extends State<CartReviewSheet> {
  late final TextEditingController _instructionsController;
  late final Map<int, CartLine> _lines;
  late int _guestCount;

  @override
  void initState() {
    super.initState();
    _instructionsController = TextEditingController(
      text: widget.initialInstructions,
    );
    _lines = {for (final line in widget.initialLines) line.dish.id: line};
    _guestCount = widget.initialGuestCount.clamp(1, widget.maxGuests).toInt();
  }

  @override
  void dispose() {
    _instructionsController.dispose();
    super.dispose();
  }

  Future<void> _editItemNote(CartLine line) async {
    final note = await showDialog<String>(
      context: context,
      builder: (_) =>
          _ItemNoteDialog(itemName: line.dish.name, initialNote: line.note),
    );
    if (!mounted || note == null) return;
    setState(() => _lines[line.dish.id] = line.copyWith(note: note));
  }

  void _changeLine(CartLine line, int delta) {
    final quantity = (line.quantity + delta)
        .clamp(0, line.dish.maximumQuantity)
        .toInt();
    setState(() {
      if (quantity == 0) {
        _lines.remove(line.dish.id);
      } else {
        _lines[line.dish.id] = line.copyWith(quantity: quantity);
      }
    });
  }

  double get _subtotal =>
      _lines.values.fold(0, (sum, line) => sum + line.total);

  @override
  Widget build(BuildContext context) {
    final bottomInset = MediaQuery.viewInsetsOf(context).bottom;
    return FractionallySizedBox(
      heightFactor: 0.92,
      child: Container(
        decoration: const BoxDecoration(
          color: AppColors.canvas,
          borderRadius: BorderRadius.vertical(top: Radius.circular(26)),
        ),
        child: Padding(
          padding: EdgeInsets.fromLTRB(18, 12, 18, 14 + bottomInset),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Center(
                child: Container(
                  width: 38,
                  height: 4,
                  decoration: BoxDecoration(
                    color: AppColors.line,
                    borderRadius: BorderRadius.circular(8),
                  ),
                ),
              ),
              const SizedBox(height: 17),
              Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Review order',
                          style: Theme.of(context).textTheme.headlineMedium
                              ?.copyWith(fontSize: 24),
                        ),
                        const SizedBox(height: 3),
                        Text(
                          'Table ${widget.tableName}  ·  ${_lines.length} dishes',
                        ),
                      ],
                    ),
                  ),
                  IconButton(
                    onPressed: () => Navigator.pop(context),
                    tooltip: 'Close',
                    icon: const Icon(Icons.close_rounded),
                  ),
                ],
              ),
              if (widget.showGuestCount) ...[
                const SizedBox(height: 6),
                DropdownButtonFormField<int>(
                  initialValue: _guestCount,
                  decoration: const InputDecoration(
                    labelText: 'Number of guests',
                    prefixIcon: Icon(Icons.people_outline_rounded),
                  ),
                  items: [
                    for (var count = 1; count <= widget.maxGuests; count++)
                      DropdownMenuItem(
                        value: count,
                        child: Text(
                          '$count ${count == 1 ? 'guest' : 'guests'}',
                        ),
                      ),
                  ],
                  onChanged: (value) {
                    if (value != null) setState(() => _guestCount = value);
                  },
                ),
              ],
              const SizedBox(height: 12),
              Expanded(
                child: _lines.isEmpty
                    ? const Center(
                        child: Text(
                          'Your cart is empty. Add dishes from the menu.',
                        ),
                      )
                    : ListView.separated(
                        itemCount: _lines.length,
                        separatorBuilder: (_, _) => const SizedBox(height: 9),
                        itemBuilder: (context, index) {
                          final line = _lines.values.elementAt(index);
                          return _CartLineCard(
                            line: line,
                            onIncrement: () => _changeLine(line, 1),
                            onDecrement: () => _changeLine(line, -1),
                            onEditNote: () => _editItemNote(line),
                          );
                        },
                      ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _instructionsController,
                maxLength: 1000,
                maxLines: 2,
                decoration: const InputDecoration(
                  labelText: 'Order instructions',
                  hintText: 'For example: serve dishes together',
                  alignLabelWithHint: true,
                ),
              ),
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 10),
                child: Row(
                  children: [
                    const Text(
                      'Reference subtotal',
                      style: TextStyle(fontWeight: FontWeight.w600),
                    ),
                    const Spacer(),
                    Text(
                      _money(_subtotal),
                      style: const TextStyle(
                        fontWeight: FontWeight.w800,
                        fontSize: 17,
                      ),
                    ),
                  ],
                ),
              ),
              FilledButton(
                onPressed: () => Navigator.pop(
                  context,
                  CartDraft(
                    lines: _lines.values.toList(growable: false),
                    orderInstructions: _instructionsController.text.trim(),
                    guestCount: _guestCount,
                  ),
                ),
                child: Text(
                  widget.showGuestCount ? 'Submit order' : 'Add items to order',
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _CartLineCard extends StatelessWidget {
  const _CartLineCard({
    required this.line,
    required this.onIncrement,
    required this.onDecrement,
    required this.onEditNote,
  });

  final CartLine line;
  final VoidCallback onIncrement;
  final VoidCallback onDecrement;
  final VoidCallback onEditNote;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(15),
        border: Border.all(color: AppColors.line),
      ),
      child: Column(
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  line.dish.name,
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
              ),
              Text(
                _money(line.total),
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
            ],
          ),
          const SizedBox(height: 3),
          Row(
            children: [
              Text(
                '${_money(line.dish.price)} each',
                style: Theme.of(context).textTheme.bodySmall,
              ),
              const Spacer(),
              IconButton(
                onPressed: onDecrement,
                tooltip: 'Decrease quantity',
                visualDensity: VisualDensity.compact,
                icon: const Icon(Icons.remove_circle_outline_rounded),
              ),
              Text(
                '${line.quantity}',
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
              IconButton(
                onPressed: line.quantity < line.dish.maximumQuantity
                    ? onIncrement
                    : null,
                tooltip: 'Increase quantity',
                visualDensity: VisualDensity.compact,
                icon: const Icon(Icons.add_circle_outline_rounded),
              ),
            ],
          ),
          Align(
            alignment: Alignment.centerLeft,
            child: TextButton.icon(
              onPressed: onEditNote,
              style: TextButton.styleFrom(
                padding: EdgeInsets.zero,
                visualDensity: VisualDensity.compact,
              ),
              icon: const Icon(Icons.edit_note_rounded, size: 18),
              label: Text(
                line.note.isEmpty ? 'Add item note' : 'Note: ${line.note}',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _ItemNoteDialog extends StatefulWidget {
  const _ItemNoteDialog({required this.itemName, required this.initialNote});

  final String itemName;
  final String initialNote;

  @override
  State<_ItemNoteDialog> createState() => _ItemNoteDialogState();
}

class _ItemNoteDialogState extends State<_ItemNoteDialog> {
  late final TextEditingController _controller;

  @override
  void initState() {
    super.initState();
    _controller = TextEditingController(text: widget.initialNote);
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: Text('Note for ${widget.itemName}'),
    content: TextField(
      controller: _controller,
      autofocus: true,
      maxLength: 255,
      maxLines: 3,
      decoration: const InputDecoration(hintText: 'For example: less spicy'),
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

String _money(double amount) => '₹${amount.toStringAsFixed(2)}';
