# Nagarjuna project context

Laravel 12 / PHP 8.2 / MySQL / Eloquent / Blade with Android Flutter waiter and kitchen clients. The browser KDS remains a separate required surface. Billing integration is excluded from the current remediation.

## Development rules

- Inspect and reuse existing architecture and services; keep operational rules in Laravel.
- Preserve permissions and enforce company/branch/station access server-side.
- Validate related IDs and preserve dependency-protected soft deletes.
- Do not run destructive migrations or delete data without approval.
- Do not write or run automated tests. The project owner tests manually. Use relevant static analysis, build checks and a documented acceptance walkthrough.

## Current implementation

Masters, staff/roles, order/KOT workflows, stock ledger, controlled cancellation and live status are implemented. The November 2026 remediation adds multi-station routing, terminal cancellation states, atomic numbering, persistent quantity-addition recovery, live cart reconciliation, paginated kitchen history/sound, scoped TV endpoints, reports/settings and audit/status history. Kitchen type, GST number, employee code and designation are stored in masters.

Read [API.md](API.md), [DATABASE.md](DATABASE.md) and [OPERATIONS.md](OPERATIONS.md) before changing contracts, transaction locking, numbering or deployment behavior. The [requirements audit](mobile-requirements-audit-2026-11-13.md) preserves the original findings with a current remediation status at the beginning.

Production readiness is established by the target environment and manual acceptance, not by historical planning statements. Keep deployment prerequisites and verification results explicit.
