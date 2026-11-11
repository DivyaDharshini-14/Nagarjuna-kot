import 'package:flutter/material.dart';

import '../app/kitchen_theme.dart';
import '../core/api_client.dart';
import '../data/kitchen_repository.dart';
import '../domain/kitchen_models.dart';

class KitchenStockScreen extends StatefulWidget {
  const KitchenStockScreen({
    super.key,
    required this.repository,
    required this.canManageStock,
    required this.onBack,
  });

  final KitchenRepository repository;
  final bool canManageStock;
  final VoidCallback onBack;

  @override
  State<KitchenStockScreen> createState() => _KitchenStockScreenState();
}

class _KitchenStockScreenState extends State<KitchenStockScreen> {
  List<KitchenStockItem> _items = const [];
  bool _loading = true;
  String? _error;
  final Set<int> _busyItems = {};

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final items = await widget.repository.getStockItems();
      if (!mounted) return;
      setState(() {
        _items = items;
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
    final result =
        await showDialog<({int quantity, String reason, String direction})>(
          context: context,
          builder: (_) => _StockAdjustmentDialog(
            title: _adjustmentTitle(type, item.name),
            allowDirection: type == 'manual_adjustment',
          ),
        );
    if (result == null) return;

    setState(() => _busyItems.add(item.id));
    try {
      final updated = await widget.repository.adjustStock(
        menuItemId: item.id,
        type: type,
        quantity: result.quantity,
        reason: result.reason,
        direction: type == 'manual_adjustment' ? result.direction : null,
      );
      _replaceItem(updated);
      _message('Stock movement recorded.');
    } on KitchenApiException catch (error) {
      _message(error.message);
    } on KitchenApiTransportException catch (error) {
      _message(error.message);
    } finally {
      if (mounted) setState(() => _busyItems.remove(item.id));
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
    try {
      final entries = await widget.repository.getStockTransactions(item.id);
      if (!mounted) return;
      await showDialog<void>(
        context: context,
        builder: (context) => AlertDialog(
          title: Text('${item.name} stock history'),
          content: SizedBox(
            width: 520,
            height: 420,
            child: entries.isEmpty
                ? const Center(child: Text('No stock movements recorded.'))
                : ListView.separated(
                    itemCount: entries.length,
                    separatorBuilder: (_, _) => const Divider(height: 1),
                    itemBuilder: (context, index) {
                      final entry = entries[index];
                      final delta = entry.quantityDelta;
                      return ListTile(
                        contentPadding: EdgeInsets.zero,
                        title: Text(
                          '${entry.type.replaceAll('_', ' ')} · ${delta > 0 ? '+' : ''}$delta',
                        ),
                        subtitle: Text(
                          '${entry.reason}\n${entry.user} · ${entry.createdAt ?? ''}',
                        ),
                        isThreeLine: true,
                        trailing: Text(
                          '${entry.stockBefore} → ${entry.stockAfter}',
                        ),
                      );
                    },
                  ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: const Text('Close'),
            ),
          ],
        ),
      );
    } on KitchenApiException catch (error) {
      _message(error.message);
    } on KitchenApiTransportException catch (error) {
      _message(error.message);
    }
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
    appBar: AppBar(
      leading: IconButton(
        onPressed: widget.onBack,
        icon: const Icon(Icons.arrow_back_rounded),
      ),
      title: const Text('Prepared stock'),
      actions: [
        IconButton(
          onPressed: _loading ? null : _load,
          tooltip: 'Refresh stock',
          icon: const Icon(Icons.refresh_rounded),
        ),
      ],
    ),
    body: _loading
        ? const Center(child: CircularProgressIndicator())
        : _error != null
        ? Center(
            child: Padding(
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
          )
        : RefreshIndicator(
            onRefresh: _load,
            child: _items.isEmpty
                ? ListView(
                    children: const [
                      SizedBox(height: 150),
                      Center(
                        child: Text(
                          'No active dishes are mapped to your assigned kitchens.',
                        ),
                      ),
                    ],
                  )
                : ListView.separated(
                    padding: const EdgeInsets.all(16),
                    itemCount: _items.length,
                    separatorBuilder: (_, _) => const SizedBox(height: 10),
                    itemBuilder: (context, index) => _itemCard(_items[index]),
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
            Row(
              children: [
                Expanded(
                  child: Column(
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
                  ),
                ),
                Text(
                  item.quantity?.toString() ?? '—',
                  style: const TextStyle(
                    fontSize: 23,
                    fontWeight: FontWeight.w900,
                    color: KitchenColors.green,
                  ),
                ),
                const SizedBox(width: 5),
                const Text(
                  'portions',
                  style: TextStyle(fontSize: 10, color: KitchenColors.muted),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                const Text(
                  'Availability',
                  style: TextStyle(fontWeight: FontWeight.w700, fontSize: 12),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: DropdownButtonFormField<String>(
                    initialValue: item.availability,
                    decoration: const InputDecoration(
                      isDense: true,
                      contentPadding: EdgeInsets.symmetric(
                        horizontal: 10,
                        vertical: 8,
                      ),
                    ),
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
                            if (value != null && value != item.availability) {
                              _setAvailability(item, value);
                            }
                          }
                        : null,
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
  Widget build(BuildContext context) => AlertDialog(
    title: Text(widget.title),
    content: Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        TextField(
          controller: _quantityController,
          keyboardType: TextInputType.number,
          decoration: const InputDecoration(labelText: 'Portion quantity'),
        ),
        if (widget.allowDirection)
          DropdownButtonFormField<String>(
            initialValue: _direction,
            decoration: const InputDecoration(labelText: 'Correction'),
            items: const [
              DropdownMenuItem(value: 'increase', child: Text('Add portions')),
              DropdownMenuItem(
                value: 'decrease',
                child: Text('Remove portions'),
              ),
            ],
            onChanged: (value) =>
                setState(() => _direction = value ?? 'increase'),
          ),
        TextField(
          controller: _reasonController,
          minLines: 2,
          maxLines: 3,
          maxLength: 500,
          decoration: const InputDecoration(labelText: 'Reason (required)'),
        ),
      ],
    ),
    actions: [
      TextButton(
        onPressed: () => Navigator.of(context).pop(),
        child: const Text('Back'),
      ),
      FilledButton(
        onPressed: () {
          final quantity = int.tryParse(_quantityController.text.trim());
          final reason = _reasonController.text.trim();
          if (quantity == null || quantity < 1 || reason.length < 3) return;
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
  final _reasonController = TextEditingController();

  @override
  void dispose() {
    _reasonController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: const Text('Why is availability changing?'),
    content: TextField(
      controller: _reasonController,
      autofocus: true,
      minLines: 2,
      maxLines: 4,
      maxLength: 500,
      decoration: const InputDecoration(labelText: 'Reason (required)'),
    ),
    actions: [
      TextButton(
        onPressed: () => Navigator.of(context).pop(),
        child: const Text('Back'),
      ),
      FilledButton(
        onPressed: () {
          final reason = _reasonController.text.trim();
          if (reason.length >= 3) Navigator.of(context).pop(reason);
        },
        child: const Text('Save change'),
      ),
    ],
  );
}
