import 'package:flutter/material.dart';

import '../app/kitchen_theme.dart';
import '../core/api_client.dart';
import '../data/kitchen_repository.dart';
import '../domain/kitchen_models.dart';
import 'kitchen_page_controls.dart';

class KitchenStockScreen extends StatefulWidget {
  const KitchenStockScreen({
    super.key,
    required this.repository,
    required this.canManageStock,
    required this.onBack,
    this.userId,
  });

  final KitchenRepository repository;
  final bool canManageStock;
  final VoidCallback onBack;
  final int? userId;

  @override
  State<KitchenStockScreen> createState() => _KitchenStockScreenState();
}

class _KitchenStockScreenState extends State<KitchenStockScreen> {
  List<KitchenStockItem> _items = const [];
  bool _loading = true;
  String? _error;
  final Set<int> _busyItems = {};
  int _page = 1;
  int _lastPage = 1;
  int _total = 0;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load({int? page}) async {
    setState(() {
      _loading = true;
      _error = null;
      _page = page ?? _page;
    });
    try {
      var result = await widget.repository.getStockPage(page: _page);
      if (result.currentPage > result.lastPage) {
        result = await widget.repository.getStockPage(page: result.lastPage);
      }
      if (!mounted) return;
      setState(() {
        _items = result.items;
        _page = result.currentPage;
        _lastPage = result.lastPage;
        _total = result.total;
        _loading = false;
      });
    } on KitchenApiException catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error.message;
        _loading = false;
      });
    } on KitchenApiTransportException catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error.message;
        _loading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _error = 'Could not load prepared stock. Retry when connected.';
        _loading = false;
      });
    }
  }

  Future<void> _adjust(KitchenStockItem item, String type) async {
    if (_busyItems.contains(item.id)) return;
    final userId = widget.userId;
    final store = widget.repository.tokens;
    setState(() => _busyItems.add(item.id));
    try {
      if (userId != null) {
        final saved = await store.readUnconfirmedStock(userId, item.id);
        if (!mounted) return;
        if (saved != null) {
          await _reviewUnconfirmedStock(item, saved);
          return;
        }
      }
      final result =
          await showDialog<({int quantity, String reason, String direction})>(
            context: context,
            builder: (_) => _StockAdjustmentDialog(
              title: _adjustmentTitle(type, item.name),
              allowDirection: type == 'manual_adjustment',
            ),
          );
      if (result == null || !mounted) return;

      if (userId != null) {
        await store.saveUnconfirmedStock(
          userId,
          item.id,
          '${_adjustmentTitle(type, item.name)} • ${result.quantity} portions'
          '${type == 'manual_adjustment' ? ' • ${result.direction}' : ''}'
          '\n${result.reason}',
        );
      }
      if (!mounted) return;
      KitchenStockItem updated;
      try {
        updated = await widget.repository.adjustStock(
          menuItemId: item.id,
          type: type,
          quantity: result.quantity,
          reason: result.reason,
          direction: type == 'manual_adjustment' ? result.direction : null,
        );
      } on KitchenApiException catch (error) {
        if (!const {400, 401, 403, 404, 422}.contains(error.statusCode)) {
          rethrow;
        }
        if (userId != null) await store.clearUnconfirmedStock(userId, item.id);
        _message(error.message);
        return;
      }
      if (userId != null) await store.clearUnconfirmedStock(userId, item.id);
      _replaceItem(updated);
      _message('Stock movement recorded.');
    } catch (_) {
      _message(
        'Could not confirm the stock movement. Check stock history before submitting it again.',
      );
    } finally {
      if (mounted) setState(() => _busyItems.remove(item.id));
    }
  }

  Future<void> _reviewUnconfirmedStock(
    KitchenStockItem item,
    String saved,
  ) async {
    await _showHistory(item);
    if (!mounted) return;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Unconfirmed stock movement'),
        content: Text(
          '$saved\n\nConfirm the outcome in stock history or with a manager before clearing this notice.',
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
    if (confirmed == true && mounted) {
      await widget.repository.tokens.clearUnconfirmedStock(
        widget.userId!,
        item.id,
      );
      if (mounted) await _load();
    }
  }

  Future<void> _setAvailability(KitchenStockItem item, String value) async {
    final reason = await showDialog<String>(
      context: context,
      builder: (_) => const _AvailabilityReasonDialog(),
    );
    if (reason == null || !mounted) return;

    setState(() => _busyItems.add(item.id));
    try {
      final updated = await widget.repository.setAvailability(
        menuItemId: item.id,
        availability: value,
        reason: reason,
      );
      _replaceItem(updated);
      _message('Dish availability updated.');
    } on KitchenApiException catch (error) {
      _message(error.message);
    } on KitchenApiTransportException catch (error) {
      _message(error.message);
    } finally {
      if (mounted) setState(() => _busyItems.remove(item.id));
    }
  }

  Future<void> _showHistory(KitchenStockItem item) async {
    await showDialog<void>(
      context: context,
      builder: (_) =>
          _StockHistoryDialog(repository: widget.repository, item: item),
    );
  }

  void _replaceItem(KitchenStockItem updated) {
    if (!mounted) return;
    setState(() {
      _items = [
        for (final item in _items)
          if (item.id == updated.id) updated else item,
      ];
    });
  }

  void _message(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context)
        .showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    bottomNavigationBar: _lastPage > 1
        ? SafeArea(
            child: KitchenPageControls(
              currentPage: _page,
              lastPage: _lastPage,
              total: _total,
              loading: _loading || _busyItems.isNotEmpty,
              onPageChanged: (page) => _load(page: page),
            ),
          )
        : null,
    appBar: AppBar(
      leading: IconButton(
        onPressed: _busyItems.isEmpty ? widget.onBack : null,
        icon: const Icon(Icons.arrow_back_rounded),
      ),
      toolbarHeight: 36 + MediaQuery.textScalerOf(context).scale(20),
      title: const Text(
        'Prepared stock',
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
      ),
    ),
    body: _loading
        ? const Center(child: CircularProgressIndicator())
        : _error != null
        ? SafeArea(
            child: Center(
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(_error!, textAlign: TextAlign.center),
                    const SizedBox(height: 12),
                    FilledButton(onPressed: _load, child: const Text('Retry')),
                  ],
                ),
              ),
            ),
          )
        : SafeArea(
            top: false,
            child: RefreshIndicator(
              onRefresh: _load,
              child: _items.isEmpty
                  ? ListView(
                      physics: const AlwaysScrollableScrollPhysics(),
                      children: const [
                        SizedBox(height: 150),
                        Padding(
                          padding: EdgeInsets.all(24),
                          child: Text(
                            'No active dishes are mapped to your assigned kitchens.',
                            textAlign: TextAlign.center,
                          ),
                        ),
                      ],
                    )
                  : ListView.separated(
                      physics: const AlwaysScrollableScrollPhysics(),
                      padding: const EdgeInsets.all(16),
                      itemCount: _items.length,
                      separatorBuilder: (_, _) => const SizedBox(height: 10),
                      itemBuilder: (context, index) => _itemCard(_items[index]),
                    ),
            ),
          ),
  );

  Widget _itemCard(KitchenStockItem item) {
    final busy = _busyItems.contains(item.id);
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            LayoutBuilder(
              builder: (context, constraints) {
                final details = Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      item.name,
                      style: const TextStyle(
                        fontWeight: FontWeight.w800,
                        fontSize: 16,
                      ),
                    ),
                    Text(
                      item.kitchens.join(', '),
                      style: const TextStyle(
                        color: KitchenColors.muted,
                        fontSize: 11,
                      ),
                    ),
                  ],
                );
                final quantity = Text.rich(
                  TextSpan(
                    children: [
                      TextSpan(
                        text: item.quantity?.toString() ?? '—',
                        style: const TextStyle(
                          fontSize: 23,
                          fontWeight: FontWeight.w900,
                          color: KitchenColors.green,
                        ),
                      ),
                      const TextSpan(
                        text: ' portions',
                        style: TextStyle(
                          fontSize: 10,
                          color: KitchenColors.muted,
                        ),
                      ),
                    ],
                  ),
                );
                // Stack metadata when text or portion counts need more room.
                if (constraints.maxWidth < 320 ||
                    MediaQuery.textScalerOf(context).scale(14) > 18 ||
                    (item.quantity?.toString().length ?? 1) > 5) {
                  return Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [details, const SizedBox(height: 8), quantity],
                  );
                }
                return Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(flex: 2, child: details),
                    const SizedBox(width: 12),
                    Flexible(child: quantity),
                  ],
                );
              },
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                Expanded(
                  child: InputDecorator(
                    isEmpty: false,
                    decoration: const InputDecoration(
                      labelText: 'Availability',
                      floatingLabelBehavior: FloatingLabelBehavior.always,
                      isDense: true,
                      contentPadding: EdgeInsets.symmetric(
                        horizontal: 10,
                        vertical: 8,
                      ),
                    ),
                    child: DropdownButtonHideUnderline(
                      child: DropdownButton<String>(
                        value: item.availability,
                        isExpanded: true,
                        isDense: false,
                        itemHeight: null,
                        borderRadius: BorderRadius.circular(14),
                        items: const [
                          DropdownMenuItem(
                            value: 'available',
                            child: Text('Available'),
                          ),
                          DropdownMenuItem(
                            value: 'temporarily_unavailable',
                            child: Text('Temporarily unavailable'),
                          ),
                          DropdownMenuItem(
                            value: 'sold_out',
                            child: Text('Sold out'),
                          ),
                        ],
                        onChanged: widget.canManageStock && !busy
                            ? (value) {
                                if (value != null &&
                                    value != item.availability) {
                                  _setAvailability(item, value);
                                }
                              }
                            : null,
                      ),
                    ),
                  ),
                ),
                IconButton(
                  onPressed: busy ? null : () => _showHistory(item),
                  tooltip: 'Stock history',
                  icon: const Icon(Icons.history_rounded),
                ),
              ],
            ),
            if (widget.canManageStock && item.stockTrackingEnabled) ...[
              const SizedBox(height: 7),
              Wrap(
                spacing: 7,
                runSpacing: 5,
                children: [
                  _action('Stock in', () => _adjust(item, 'stock_in')),
                  _action('Wastage', () => _adjust(item, 'wastage')),
                  _action('Damaged', () => _adjust(item, 'damaged')),
                  _action(
                    'Correction',
                    () => _adjust(item, 'manual_adjustment'),
                  ),
                ],
              ),
            ] else if (!item.stockTrackingEnabled)
              const Padding(
                padding: EdgeInsets.only(top: 7),
                child: Text(
                  'Stock tracking must be enabled in menu management before adjustments are available.',
                  style: TextStyle(color: KitchenColors.muted, fontSize: 11),
                ),
              )
            else if (item.quantity == null)
              const Padding(
                padding: EdgeInsets.only(top: 7),
                child: Text(
                  'Add opening stock to configure the available portion count.',
                  style: TextStyle(color: KitchenColors.muted, fontSize: 11),
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _action(String label, VoidCallback onPressed) => OutlinedButton(
    onPressed: _busyItems.isNotEmpty ? null : onPressed,
    style: OutlinedButton.styleFrom(
      visualDensity: VisualDensity.compact,
      padding: const EdgeInsets.symmetric(horizontal: 10),
    ),
    child: Text(label),
  );

  String _adjustmentTitle(String type, String dish) => switch (type) {
    'stock_in' => 'Add $dish stock',
    'wastage' => 'Record $dish wastage',
    'damaged' => 'Record damaged $dish',
    _ => 'Correct $dish stock',
  };
}

class _StockHistoryDialog extends StatefulWidget {
  const _StockHistoryDialog({required this.repository, required this.item});

  final KitchenRepository repository;
  final KitchenStockItem item;

  @override
  State<_StockHistoryDialog> createState() => _StockHistoryDialogState();
}

class _StockHistoryDialogState extends State<_StockHistoryDialog> {
  KitchenPage<KitchenStockTransaction>? _entries;
  bool _loading = true;
  String? _error;
  int _requestedPage = 1;

  @override
  void initState() {
    super.initState();
    _load(1);
  }

  Future<void> _load(int page) async {
    setState(() {
      _loading = true;
      _error = null;
      _requestedPage = page;
    });
    try {
      final entries = await widget.repository.getStockTransactionPage(
        widget.item.id,
        page: page,
      );
      if (mounted) setState(() => _entries = entries);
    } on KitchenApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } on KitchenApiTransportException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } catch (_) {
      if (mounted) {
        setState(
          () => _error = 'Could not load stock history. Retry when connected.',
        );
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final entries = _entries;
    return _StockDialog(
      title: Text('${widget.item.name} stock history'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _loading
              ? const Padding(
                  padding: EdgeInsets.all(24),
                  child: Center(child: CircularProgressIndicator()),
                )
              : _error != null
              ? Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(_error!, textAlign: TextAlign.center),
                    TextButton(
                      onPressed: () => _load(_requestedPage),
                      child: const Text('Retry'),
                    ),
                  ],
                )
              : entries == null || entries.items.isEmpty
              ? const Center(child: Text('No stock movements recorded.'))
              : Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    for (final entry in entries.items) ...[
                      Padding(
                        padding: const EdgeInsets.symmetric(vertical: 12),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              '${entry.type.replaceAll('_', ' ')}'
                              '${entry.quantity > 0 ? ' · ${entry.quantity} portion${entry.quantity == 1 ? '' : 's'}' : ''}',
                              style: const TextStyle(
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            Text(
                              '${entry.stockBefore} → ${entry.stockAfter} portions',
                            ),
                            const SizedBox(height: 4),
                            Text(entry.reason),
                            Text('${entry.user} · ${entry.createdAt ?? ''}'),
                          ],
                        ),
                      ),
                      const Divider(height: 1),
                    ],
                  ],
                ),
          if (entries != null && entries.lastPage > 1)
            KitchenPageControls(
              currentPage: entries.currentPage,
              lastPage: entries.lastPage,
              total: entries.total,
              loading: _loading,
              onPageChanged: _load,
            ),
        ],
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: const Text('Close'),
        ),
      ],
    );
  }
}

/// The keyboard can leave very little vertical space in landscape. Keeping
/// title, fields, counters and actions in one scroll view lets them all grow.
class _StockDialog extends StatelessWidget {
  const _StockDialog({
    required this.title,
    required this.content,
    required this.actions,
  });

  final Widget title;
  final Widget content;
  final List<Widget> actions;

  @override
  Widget build(BuildContext context) => Dialog(
    insetPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 24),
    child: ConstrainedBox(
      constraints: const BoxConstraints(maxWidth: 560),
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            DefaultTextStyle.merge(
              style: Theme.of(context).textTheme.headlineSmall,
              child: title,
            ),
            const SizedBox(height: 24),
            content,
            const SizedBox(height: 24),
            OverflowBar(
              alignment: MainAxisAlignment.end,
              overflowAlignment: OverflowBarAlignment.end,
              spacing: 8,
              overflowSpacing: 8,
              children: actions,
            ),
          ],
        ),
      ),
    ),
  );
}

class _StockAdjustmentDialog extends StatefulWidget {
  const _StockAdjustmentDialog({
    required this.title,
    required this.allowDirection,
  });

  final String title;
  final bool allowDirection;

  @override
  State<_StockAdjustmentDialog> createState() => _StockAdjustmentDialogState();
}

class _StockAdjustmentDialogState extends State<_StockAdjustmentDialog> {
  final _formKey = GlobalKey<FormState>();
  final _quantityController = TextEditingController();
  final _reasonController = TextEditingController();
  String _direction = 'increase';

  @override
  void dispose() {
    _quantityController.dispose();
    _reasonController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => _StockDialog(
    title: Text(widget.title),
    content: Form(
      key: _formKey,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          TextFormField(
            controller: _quantityController,
            keyboardType: TextInputType.number,
            textInputAction: TextInputAction.next,
            decoration: const InputDecoration(
              labelText: 'Portion quantity',
              errorMaxLines: 3,
            ),
            validator: (value) {
              final quantity = int.tryParse(value?.trim() ?? '');
              return quantity == null || quantity < 1
                  ? 'Enter a whole number of at least 1.'
                  : null;
            },
          ),
          const SizedBox(height: 16),
          if (widget.allowDirection) ...[
            DropdownButtonFormField<String>(
              initialValue: _direction,
              isExpanded: true,
              isDense: false,
              itemHeight: null,
              decoration: const InputDecoration(labelText: 'Correction'),
              items: const [
                DropdownMenuItem(
                  value: 'increase',
                  child: Text('Add portions'),
                ),
                DropdownMenuItem(
                  value: 'decrease',
                  child: Text('Remove portions'),
                ),
              ],
              onChanged: (value) =>
                  setState(() => _direction = value ?? 'increase'),
            ),
            const SizedBox(height: 16),
          ],
          TextFormField(
            controller: _reasonController,
            minLines: 2,
            maxLines: 3,
            maxLength: 500,
            decoration: const InputDecoration(
              labelText: 'Reason (required)',
              errorMaxLines: 3,
            ),
            validator: _validateReason,
          ),
        ],
      ),
    ),
    actions: [
      TextButton(
        onPressed: () => Navigator.of(context).pop(),
        child: const Text('Back'),
      ),
      FilledButton(
        onPressed: () {
          if (!_formKey.currentState!.validate()) return;
          final quantity = int.parse(_quantityController.text.trim());
          final reason = _reasonController.text.trim();
          Navigator.of(context)
              .pop((quantity: quantity, reason: reason, direction: _direction));
        },
        child: const Text('Save movement'),
      ),
    ],
  );
}

class _AvailabilityReasonDialog extends StatefulWidget {
  const _AvailabilityReasonDialog();

  @override
  State<_AvailabilityReasonDialog> createState() =>
      _AvailabilityReasonDialogState();
}

class _AvailabilityReasonDialogState extends State<_AvailabilityReasonDialog> {
  final _formKey = GlobalKey<FormState>();
  final _reasonController = TextEditingController();

  @override
  void dispose() {
    _reasonController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => _StockDialog(
    title: const Text('Why is availability changing?'),
    content: Form(
      key: _formKey,
      child: TextFormField(
        controller: _reasonController,
        autofocus: true,
        minLines: 2,
        maxLines: 4,
        maxLength: 500,
        decoration: const InputDecoration(
          labelText: 'Reason (required)',
          errorMaxLines: 3,
        ),
        validator: _validateReason,
      ),
    ),
    actions: [
      TextButton(
        onPressed: () => Navigator.of(context).pop(),
        child: const Text('Back'),
      ),
      FilledButton(
        onPressed: () {
          if (!_formKey.currentState!.validate()) return;
          final reason = _reasonController.text.trim();
          Navigator.of(context).pop(reason);
        },
        child: const Text('Save change'),
      ),
    ],
  );
}

String? _validateReason(String? value) => (value?.trim().length ?? 0) < 3
    ? 'Enter a reason with at least 3 characters.'
    : null;
