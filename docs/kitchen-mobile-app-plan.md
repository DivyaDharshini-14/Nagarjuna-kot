# Nagarjuna Kitchen Mobile App Plan

## Scope

The requirements document specifies a browser-based Kitchen Display System. The project owner has additionally confirmed a separate mobile app for kitchen staff. This mobile app complements the browser KDS; it does not replace it or the waiter/captain app.

The mobile client is a standalone Android Flutter project in `kitchen_mobile/`. Laravel remains authoritative for staff access, kitchen assignment, KOT data, and ticket status. The app must never let a user select another company's or branch's kitchen by changing a request parameter.

## Requirements carried into the mobile app

- Kitchen staff sign in with their registered account.
- Show KOT tickets for the kitchen stations assigned to that staff account.
- Display new, accepted, preparing, ready, and recently cancelled tickets, including order/table, elapsed time, dishes, quantities, and notes.
- Let authorized staff accept a new ticket, start preparation, and mark it ready in order.
- Alert staff to incoming and cancelled tickets and show connection state.
- Keep browser KDS availability and its large-screen workflow.

## Phase 1 — API, assignment, and Android board

Status: the Android app, API, assignments, and migrations are implemented. The assigned-station query was corrected to qualify columns from its `kitchens`/`kitchen_user` join; Dart analysis and an Android debug build pass after the correction. The sign-in layout was reviewed on the emulator, but login and KOT transition UAT with an assigned kitchen account still need to be confirmed after that query fix.

- Add a kitchen-only Sanctum login with the `kitchen:mobile` ability, seven-day expiry, secure token storage, session restore, and sign-out.
- Add `kitchen_user` assignments and a kitchen-station selector in the web user form. The API only lists and serves stations assigned to the signed-in account within its active company and branch.
- Correct the kitchen role permission seeder so `kitchen-staff` and `kitchen-manager` retain `kot.view` and `kot.edit`.
- Add branch/station-scoped APIs for assigned kitchens, ticket queues, and guarded ticket transitions.
- Build the phone board with queue filters, waiting time, table/order context, dish quantities and notes, connection/error states, refresh, and status actions.
- Poll tickets every 12 seconds while the board is open. Show in-app alerts when a new ticket appears or a ticket is cancelled.

## Phase 2 — Real-time updates and operational alerts

- Added Laravel Reverb and broadcasting configuration, an after-commit KOT event, and private `kitchens.{id}` channels.
- Added `/api/broadcasting/auth` for kitchen-mobile tokens. Channel authorization checks the kitchen role, KOT permission, and explicit station assignment.
- The Flutter app authenticates its Pusher-protocol WebSocket subscription, refreshes tickets on KOT events, reconnects with backoff, and catches up after reconnect. It retains 12-second polling as a fallback.
- Add platform notification behavior only after the required Android notification permission and delivery flow are agreed for the deployment.
- Runtime acceptance still requires production Reverb credentials/host, a running Reverb service and queue worker, and live-device checks. Background push notifications are not implemented.

## Phase 3 — Manual kitchen UAT and release

- Apply migrations and seed/update the kitchen role permissions in the target environment. Production `DatabaseSeeder` skips demo users/orders; direct production runs of `UserSeeder` and `OrderDemoSeeder` are blocked.
- Assign kitchen stations to kitchen accounts in the web user editor.
- Verify login, tenant boundaries, ticket ordering, every allowed status transition, cancellation display, expired sessions, network loss, and multiple station assignments on Android devices.
- Configure the production HTTPS API address, Reverb WSS host, app icon/branding, and owner release signing key. Android release builds now fail if signing properties or the referenced keystore are missing.
- Acceptance: the owner completes the agreed kitchen workflow checklist on the target devices and signs the release build with the project's key.

## Known setup steps before the first run

1. Apply the `2026_11_08_130000_create_kitchen_user_table` and `2026_11_08_130100_grant_kot_permissions_to_kitchen_roles` migrations through the project's normal deployment process. Both are applied in the current development database.
2. Assign at least one active kitchen station to each kitchen account in the web user editor.
3. On a fresh database, the corrected `RolePermissionSeeder` also grants `kot.view` and `kot.edit` to kitchen roles.
4. Run `php artisan reverb:install` once, then keep `php artisan queue:work` and `php artisan reverb:start --host=0.0.0.0` running in development.
5. From `kitchen_mobile/`, run `flutter pub get` and launch with API/Reverb defines from `kitchen_mobile/README.md`.

Production deployment and manual cross-app acceptance gates are documented in [`mobile-production-readiness.md`](mobile-production-readiness.md).

The current code supports authenticated WebSocket delivery when Reverb is configured. The local `.env` still uses the log broadcaster; production TLS, queue operations, live KOT UAT, and background notifications remain unfinished.

## Additional implemented workflows

- Permission-scoped kitchen stock screen for mapped dishes: stock-in, wastage, damaged portions, manual corrections, availability changes, and transaction history. Changes require a reason, and the server blocks negative balances.
- Approved waiter cancellations update KOT quantities and display; prepared stock returns only for portions whose KOT work has not started.
- Browser KDS uses Reverb for live updates, offers an operator-enabled sound alert, shows elapsed waiting and connection state, reconnects, and refreshes every 15 seconds while disconnected. Kitchen editors can register, rotate, or revoke a kitchen-scoped TV URL.

These flows still need staging UAT alongside production API/Reverb hosting, signing keys, and the target-device release checks.
