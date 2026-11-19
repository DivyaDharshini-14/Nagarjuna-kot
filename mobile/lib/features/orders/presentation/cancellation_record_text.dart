import '../domain/order_status.dart';

String formatCancellationRecord(ItemCancellationProgress cancellation) {
  final lines = <String>[
    '${cancellation.quantity}× cancellation ${_cancellationStatusLabel(cancellation.status)} · ${cancellation.reason}',
  ];
  final requestedBy = cancellation.requestedBy;
  final decidedBy = cancellation.decidedBy;
  final directlyApplied =
      cancellation.status.toLowerCase() == 'approved' &&
      requestedBy != null &&
      requestedBy == decidedBy;
  if (requestedBy != null && !directlyApplied) {
    lines.add(
      'Requested by $requestedBy${cancellation.requestedAt == null ? '' : ' · ${_formatDateTime(cancellation.requestedAt!)}'}',
    );
  }
  if (decidedBy != null) {
    lines.add(
      '${directlyApplied ? 'Cancelled by' : 'Decided by'} $decidedBy${cancellation.decidedAt == null ? '' : ' · ${_formatDateTime(cancellation.decidedAt!)}'}',
    );
  }
  if (cancellation.stockReversedQuantity > 0) {
    lines.add(
      '${cancellation.stockReversedQuantity} portion(s) eligible for stock return',
    );
  }
  if (cancellation.wastageQuantity > 0) {
    lines.add('${cancellation.wastageQuantity} portion(s) recorded as wastage');
  }
  final decisionNote = cancellation.decisionNote?.trim() ?? '';
  if (decisionNote.isNotEmpty) lines.add('Decision: $decisionNote');
  return lines.join('\n');
}

String _cancellationStatusLabel(String status) =>
    switch (status.toLowerCase()) {
      'approved' => 'completed',
      'pending' => 'waiting for approval',
      'rejected' => 'rejected',
      _ => status.toLowerCase(),
    };

String _formatDateTime(DateTime value) {
  final local = value.toLocal();
  String two(int number) => number.toString().padLeft(2, '0');
  return '${two(local.day)}/${two(local.month)}/${local.year} ${two(local.hour)}:${two(local.minute)}';
}
