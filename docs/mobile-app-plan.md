# Nagarjuna waiter mobile implementation

The Flutter app in `mobile/` supplies the waiter/captain workflows in specification section 5. Company/menu/user masters and reports/settings are managed in Laravel; billing integration is excluded. The separate kitchen app supplements the required browser KDS.

## Implemented workflows

- Registered credential login, seven-day waiter-only Sanctum tokens, secure storage, session restore/revocation and permission-controlled actions.
- Assigned-branch floor/table selection with status, capacity, existing-order resume, 12-second foreground refresh and resume refresh.
- Category browsing/search, dish images/type/reference price/stock, sold-out/unmapped disabling, item notes and order instructions.
- Reviewable initial/additional orders; open reviews reconcile current stock/availability and require acknowledgment after changes.
- Transactional API submission with persisted uncertain composer payload/key; server confirmation includes generated order and kitchen KOT numbers.
- Per-item preparation status, private order WebSocket updates, fallback refresh and Firebase foreground/background Ready handlers.
- Reasoned partial/full cancellation, separate-manager approval, requester/decision/stock/wastage history, pre-preparation note changes and Served actions.
- Explicit Add extra portions or Reduce line operations. Extra portions are new submitted work, with immutable payload/key saved per staff identity before sending and recovered after navigation/restart. Other mutations wait for reconciliation.
- Completion only after active tickets are Ready/Served and pending decisions resolved; table release is transactional.

Each dish is routed to every active assigned kitchen; customer stock is accounted for once. The item is Ready only when every station is Ready/Served. Never replace this server authority with local quantity/status calculations.

## Validation and release

Use static analysis/build checks and the manual scenarios in [OPERATIONS.md](OPERATIONS.md). Debug or earlier read-only emulator checks do not prove stock, retry, push or cross-app acceptance. Android requests network/notification access and disables app data backup; secure storage holds tokens and saved request identities, not passwords.

Current local signing/Firebase files exist; their production ownership/validity must be verified. Release builds require a real HTTPS API host, TLS Reverb defines, the app's upload key and Firebase registration. Do not overwrite an existing keystore. Keep Reverb, queue workers and FCM server configuration operational.

The authoritative endpoint/payload/error/recovery contract is [API.md](API.md); setup/build commands remain in [mobile/README.md](../mobile/README.md). Do not write/run automated tests under the workspace's manual-testing guidance.
