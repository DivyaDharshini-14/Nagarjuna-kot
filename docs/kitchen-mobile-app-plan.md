# Nagarjuna kitchen mobile implementation

The document specifies a browser KDS. `kitchen_mobile/` is an additional Android Flutter app carrying the kitchen queue/stock workflows; it does not replace the TV display or waiter app. Billing integration is excluded.

## Implemented workflows

Kitchen-only seven-day Sanctum authentication, secure session restore/logout and explicit active station assignments control every read/write. New/Accepted/Preparing/Ready filters plus paginated Completed/Cancelled views show table/order/KOT, dish quantities, notes, received clock time and elapsed duration. Cancelled portions are visibly separate and fully cancelled lines remain terminal.

Accept, Start preparing and Mark ready use the shared service's transaction-locked sequential rules. Private kitchen WebSockets reconnect with backoff and refresh pending work after subscription; 12-second polling remains a fallback. Firebase registration and foreground/background handlers exist. Foreground new-KOT audio uses a native Android notification tone, controlled by a saved operator preference and branch sound permission. Actual notification delivery still needs configured services and devices.

The Stock action is visible to staff with view permission. Stock dishes and transaction history are paginated. Authorized managers can add stock, record wastage/damage, correct portions and set Available/Temporarily Unavailable/Sold Out with a mandatory reason. The server rejects negative balances. A dish shared by several stations has one branch stock balance and one customer deduction/reversal.

Both browser TV paths now provide stages, cancelled/completed history, remaining quantities, live updates and sound. Friendly screens use session/station authorization; registered URLs bind one kitchen and can be rotated/revoked.

## Deployment and acceptance

Apply all pending migrations without resetting data. Set up active tenant/master records, map dishes and assign kitchen users. Configure the real API, Reverb TLS service, queue worker, FCM server credentials and distinct kitchen Firebase/signing identity. Current local files exist but do not certify deployment.

See [API.md](API.md), [DATABASE.md](DATABASE.md), [OPERATIONS.md](OPERATIONS.md) and [kitchen_mobile/README.md](../kitchen_mobile/README.md). Target-device UAT must verify station/tenant boundaries, every transition, cancellation quantities, background/foreground alerts, sound mute/settings, paging, offline recovery and release installation. Follow workspace guidance: static checks/builds and manual operational acceptance; no automated tests.
