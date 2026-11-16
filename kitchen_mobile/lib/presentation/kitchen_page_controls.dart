import 'package:flutter/material.dart';

class KitchenPageControls extends StatelessWidget {
  const KitchenPageControls({
    super.key,
    required this.currentPage,
    required this.lastPage,
    required this.total,
    required this.loading,
    required this.onPageChanged,
  });

  final int currentPage;
  final int lastPage;
  final int total;
  final bool loading;
  final ValueChanged<int> onPageChanged;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      IconButton(
        tooltip: 'Previous page',
        onPressed: loading || currentPage <= 1
            ? null
            : () => onPageChanged(currentPage - 1),
        icon: const Icon(Icons.chevron_left_rounded),
      ),
      Expanded(
        child: Text(
          'Page $currentPage of $lastPage · $total records',
          textAlign: TextAlign.center,
          style: Theme.of(context).textTheme.bodySmall,
        ),
      ),
      IconButton(
        tooltip: 'Next page',
        onPressed: loading || currentPage >= lastPage
            ? null
            : () => onPageChanged(currentPage + 1),
        icon: const Icon(Icons.chevron_right_rounded),
      ),
    ],
  );
}
