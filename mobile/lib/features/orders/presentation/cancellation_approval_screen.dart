import 'package:flutter/material.dart';

import '../../../app/app_theme.dart';
import '../../../core/network/mobile_api_client.dart';
import '../data/mobile_order_repository.dart';
import '../domain/cancellation_request.dart';

class CancellationApprovalScreen extends StatefulWidget {
  const CancellationApprovalScreen({
    super.key,
    required this.repository,
    required this.onBack,
    required this.onSessionExpired,
  });

  final MobileOrderRepository repository;
  final VoidCallback onBack;
  final Future<void> Function() onSessionExpired;

  @override
  State<CancellationApprovalScreen> createState() =>
      _CancellationApprovalScreenState();
}

class _CancellationApprovalScreenState
    extends State<CancellationApprovalScreen> {
  List<CancellationRequest> _requests = const [];
  String? _error;
  bool _loading = true;
  String? _busyBatch;

  @override
  void initState() {
    super.initState();
    _refresh();
  }

  Future<void> _refresh() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final requests = await widget.repository.pendingCancellations();
      if (!mounted) return;
      setState(() {
        _requests = requests;
        _loading = false;
      });
    } on ApiException catch (error) {
      if (!mounted) return;
      if (error.requiresSignIn) {
        await widget.onSessionExpired();
        return;
      }
      setState(() {
        _error = error.message;
        _loading = false;
      });
    } on ApiTransportException catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error.message;
        _loading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _error = 'Could not load cancellation requests. Try again.';
        _loading = false;
      });
    }
  }

  Future<void> _decide(
    CancellationRequest request, {
    required bool approve,
  }) async {
    String? note;
    if (!approve) {
      note = await _askRejectionReason();
      if (note == null || !mounted) return;
    } else {
      final confirmed = await showDialog<bool>(
        context: context,
        builder: (context) => AlertDialog(
          title: const Text('Approve cancellation?'),
          content: Text(
            'Approve the cancellation for ${request.orderNumber} at ${request.tableName}? The kitchen will be updated immediately.',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(context).pop(false),
              child: const Text('Review later'),
            ),
            FilledButton(
              onPressed: () => Navigator.of(context).pop(true),
              child: const Text('Approve'),
            ),
          ],
        ),
      );
      if (confirmed != true || !mounted) return;
    }

    setState(() => _busyBatch = request.batch);
    try {
      await widget.repository.decideCancellation(
        batch: request.batch,
        approve: approve,
        decisionNote: note,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            approve ? 'Cancellation approved.' : 'Request rejected.',
          ),
        ),
      );
      await _refresh();
    } on ApiException catch (error) {
      if (!mounted) return;
      if (error.requiresSignIn) {
        await widget.onSessionExpired();
      } else {
        _showError(error.message);
      }
    } on ApiTransportException catch (error) {
      if (mounted) _showError(error.message);
    } catch (_) {
      if (mounted) _showError('Could not update the cancellation request.');
    } finally {
      if (mounted) setState(() => _busyBatch = null);
    }
  }

  Future<String?> _askRejectionReason() => showDialog<String>(
    context: context,
    builder: (_) => const _RejectionReasonDialog(),
  );

  void _showError(String message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: AppColors.canvas,
    appBar: AppBar(
      backgroundColor: AppColors.canvas,
      leading: IconButton(
        onPressed: widget.onBack,
        icon: const Icon(Icons.arrow_back_rounded),
        tooltip: 'Back to tables',
      ),
      title: const Text('Cancellation approvals'),
      actions: [
        IconButton(
          onPressed: _loading ? null : _refresh,
          icon: const Icon(Icons.refresh_rounded),
          tooltip: 'Refresh requests',
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
                  OutlinedButton.icon(
                    onPressed: _refresh,
                    icon: const Icon(Icons.refresh_rounded),
                    label: const Text('Try again'),
                  ),
                ],
              ),
            ),
          )
        : _requests.isEmpty
        ? const Center(child: Text('No cancellation requests need approval.'))
        : RefreshIndicator(
            onRefresh: _refresh,
            child: ListView.separated(
              padding: const EdgeInsets.all(18),
              itemCount: _requests.length,
              separatorBuilder: (_, _) => const SizedBox(height: 12),
              itemBuilder: (context, index) => _RequestCard(
                request: _requests[index],
                busy: _busyBatch == _requests[index].batch,
                disabled: _busyBatch != null,
                onApprove: () => _decide(_requests[index], approve: true),
                onReject: () => _decide(_requests[index], approve: false),
              ),
            ),
          ),
  );
}

class _RequestCard extends StatelessWidget {
  const _RequestCard({
    required this.request,
    required this.busy,
    required this.disabled,
    required this.onApprove,
    required this.onReject,
  });

  final CancellationRequest request;
  final bool busy;
  final bool disabled;
  final VoidCallback onApprove;
  final VoidCallback onReject;

  @override
  Widget build(BuildContext context) => Card(
    color: AppColors.white,
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            '${request.orderNumber} · Table ${request.tableName}',
            style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16),
          ),
          const SizedBox(height: 4),
          Text(
            'Requested by ${request.requestedBy}${request.requestedAt == null ? '' : ' · ${_dateTime(request.requestedAt!)}'}',
            style: const TextStyle(color: AppColors.mutedInk, fontSize: 12),
          ),
          const SizedBox(height: 12),
          for (final item in request.items) ...[
            Text(
              '${item.quantity}× ${item.name}',
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
            Padding(
              padding: const EdgeInsets.only(left: 14, top: 2, bottom: 8),
              child: Text('Reason: ${item.reason}'),
            ),
          ],
          if (!request.canApprove)
            const Padding(
              padding: EdgeInsets.only(bottom: 8),
              child: Text(
                'Branch policy requires a different approver to approve this request.',
                style: TextStyle(color: AppColors.mutedInk, fontSize: 12),
              ),
            ),
          if (busy) ...[
            const SizedBox(height: 8),
            const LinearProgressIndicator(),
          ],
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            children: [
              FilledButton.icon(
                onPressed: disabled || !request.canApprove ? null : onApprove,
                icon: const Icon(Icons.check_rounded),
                label: const Text('Approve'),
              ),
              OutlinedButton.icon(
                onPressed: disabled ? null : onReject,
                icon: const Icon(Icons.close_rounded),
                label: const Text('Reject'),
              ),
            ],
          ),
        ],
      ),
    ),
  );

  String _dateTime(DateTime value) =>
      '${value.day.toString().padLeft(2, '0')}/${value.month.toString().padLeft(2, '0')} ${value.hour.toString().padLeft(2, '0')}:${value.minute.toString().padLeft(2, '0')}';
}

class _RejectionReasonDialog extends StatefulWidget {
  const _RejectionReasonDialog();

  @override
  State<_RejectionReasonDialog> createState() => _RejectionReasonDialogState();
}

class _RejectionReasonDialogState extends State<_RejectionReasonDialog> {
  final _controller = TextEditingController();

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: const Text('Reject cancellation'),
    content: TextField(
      controller: _controller,
      autofocus: true,
      minLines: 2,
      maxLines: 4,
      maxLength: 500,
      decoration: const InputDecoration(labelText: 'Reason for rejection'),
    ),
    actions: [
      TextButton(
        onPressed: () => Navigator.of(context).pop(),
        child: const Text('Keep pending'),
      ),
      FilledButton(
        onPressed: () {
          final reason = _controller.text.trim();
          if (reason.length >= 3) Navigator.of(context).pop(reason);
        },
        child: const Text('Reject request'),
      ),
    ],
  );
}
