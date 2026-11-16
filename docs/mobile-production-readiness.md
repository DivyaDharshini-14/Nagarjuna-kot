# Nagarjuna mobile production readiness

The current application source includes the waiter/kitchen flows, multi-station routing, cancellation/stock rules, persistent recovery, private WebSockets, Firebase hooks, scoped browser KDS, management reports/settings and audit history. These are implementation claims; production acceptance requires the target environment.

## Completed locally during remediation

- A private MySQL backup and its SHA256 checksum were created and verified before schema changes.
- Pending migrations through sequences/settings/audit/history, reporting permissions and kitchen type were applied without resetting operational data.
- Static PHP/Blade/JavaScript validation and mobile analysis/build results are recorded in the [current remediation status](mobile-requirements-audit-2026-11-13.md#current-remediation-status).
- Existing local Android signing properties, referenced keystores and Firebase client configurations were found. Presence does not certify ownership, certificate validity or delivery.

## Target environment gates

Use the deployment and acceptance procedure in [OPERATIONS.md](OPERATIONS.md). Supply/verify the real HTTPS API host, APP_TIMEZONE, TLS/WSS Reverb service and managed queue worker, FCM project/service account, app-specific Android registration/signing, uploaded image hosting, private backups and scheduler. Do not replace existing keys or run destructive migration resets. Review role seeding before synchronizing customized permissions.

Build signed release artifacts with actual hosts and each app's key, preserve crash symbols, install on target devices and record the manual cross-app scenarios. Verify force-close/lost-response recovery, multi-kitchen quantity/status/cancellation semantics, low-stock concurrency, station/branch authorization across every endpoint, sound/notifications, paging, reconnect, table release, reports and a recovery exercise.

A debug APK, source audit or configuration file cannot establish signed production release, running services, successful background delivery, backup restoration, performance or owner UAT sign-off. Billing integration remains excluded.
