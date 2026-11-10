# Nagarjuna Waiter Mobile App Plan

## Scope

Build the Android waiter/captain application in Flutter. It will use the existing Laravel application for authentication, branch-scoped restaurant data, order submission, order status, and kitchen-ready updates. Billing, payments, customer ordering, and raw-material inventory are outside the waiter app scope.

The attached Nagarjuna requirements document is the product specification. Existing repository notes and code describe the current implementation and local development constraints; they do not replace the product requirements.

The user has also confirmed a separate kitchen mobile application as part of their project work. The requirements document describes the Kitchen Display System as a browser-based display, so the mobile kitchen client is an added deliverable alongside that KDS and the waiter app. Its separate implementation plan is in [`kitchen-mobile-app-plan.md`](kitchen-mobile-app-plan.md).

## Current repository baseline

- The repository is a Laravel 12 web application with existing company, branch, kitchen, floor, table, category, menu type, menu item, kitchen mapping, user, role, order, and KOT code.
- Mobile staff authentication now uses Laravel Sanctum bearer tokens. The mobile token is limited to the `waiter:mobile` ability and expires after seven days.
- Mobile login, branch-scoped floor/table, menu, order submission/addition, and order-status endpoints are implemented under `/api/v1`; authenticated real-time kitchen broadcasting remains a deployment dependency.
- The existing `OrderService` creates orders and kitchen-wise KOT tickets. Preserve and reuse it when API work is authorized; do not duplicate its order logic in Flutter.
- A standalone Flutter app now exists in `mobile/`, targeting Android and iOS project scaffolds. Flutter is the required mobile framework.
- The separate Android Flutter kitchen app is being built in `kitchen_mobile/`; it has its own Sanctum token ability and assigned-kitchen API scope.
- The workspace notes ask contributors not to write or run automated tests. Use static analysis/build checks where available and keep a clear manual acceptance checklist for the project owner.

## Phase 1 progress

- Created the Flutter app shell and Material 3 theme in `mobile/lib/`.
- Added the responsive staff sign-in screen with email/password validation, password visibility, keyboard-aware scrolling, and loading/error presentation.
- Connected sign-in to Laravel Sanctum. The app stores only the bearer token using platform secure storage, validates saved sessions on startup, and supports server-side token revocation on sign-out.
- Removed the generated Flutter counter test so the app starts without irrelevant demo/test code, in line with the workspace's manual-testing instruction.
- `flutter analyze` completed with no issues, and the debug APK builds with the API URL supplied at launch.
- No Android virtual device is configured in this workspace, so the screen has not yet had a handset/emulator visual review. Live sign-in requires applying the Sanctum token migration and configuring a reachable API URL.

## Phase 2 progress

- Added the floor/table domain models using the existing Laravel fields and status values (`available`, `occupied`, `reserved`, and `cleaning`).
- Added the floor/table selection screen with floor switching, status filters, table capacities, branch identity, live status counts, pull-to-refresh, loading/empty/error states, and a selected-table handoff callback.
- Only available tables and occupied tables that include an active order ID can be selected. Reserved, cleaning, inactive, and unknown-status tables remain unavailable for ordering.
- Connected the screen to `GET /api/v1/floors`; Laravel derives the branch from the authenticated waiter and returns active floors, active tables, their statuses, and active order identifiers.
- Added `GET /api/v1/tables?floor_id={id}` with the same branch scope. Neither endpoint accepts a client-selected branch.
- `flutter analyze` completed with no issues, and `flutter build apk --debug` succeeded after integration. A device/emulator visual review is still pending.

## Phase 3 progress

- Added `GET /api/v1/menu`, restricted to the authenticated waiter's company and branch and protected by category, menu-type, and menu-item view permissions.
- The response includes active categories and dishes, menu type, reference price, image path, availability, and prepared-stock quantity where stock tracking is enabled.
- Added a nullable prepared-stock quantity to menu items and exposed it in menu management. Tracked items without a configured quantity or with zero portions are unavailable in the mobile app.
- Table selection now opens the menu composer. Waiters can search, filter categories, adjust quantities, refresh menu/stock, add item notes, and add order instructions before reviewing the cart.
- The cart caps quantities at the latest stock value returned by the API. Order submission and server-side stock deduction are handled in Phase 4.
- `flutter analyze` reports no issues, and the Android debug APK builds with the emulator API URL configured. Device/emulator visual review remains pending.

## Phase 4 progress

- Added `POST /api/v1/mobile/orders` and `POST /api/v1/mobile/orders/{id}/items`, protected by the waiter token ability and `order.add` permission. The app chooses create versus add based on the selected table's active order.
- Both operations require a UUID `Idempotency-Key`. A stored request hash rejects key reuse with changed content; retries return the original order without repeating item insertion or stock deduction.
- Reused `OrderService` for prices, table status, order rows, and kitchen-wise KOT generation. It now validates active branch menu relationships, locks tracked menu stock and table/order rows, revalidates availability and stock, and deducts portions in the same transaction.
- The app submits the reviewed guest count, order instructions, quantities, and item notes, then displays the server order number and generated KOT ticket numbers. Uncertain network/server outcomes retain the same draft and request key for safe retry.
- `flutter analyze` reports no issues, and the Android debug APK builds with the emulator API URL configured. Device/emulator transaction UAT remains pending.

## Phase 5 progress

- Added `GET /api/v1/mobile/orders/{id}`. Laravel scopes every lookup to the signed-in user's company and branch and requires the `order.view` permission.
- Added an order-progress screen reachable after submitting an order and from an occupied table. It shows the order state, each dish state, kitchen tickets, and item notes.
- The screen refreshes status every 12 seconds while foregrounded, refreshes when Android resumes the app, supports pull-to-refresh, preserves the last successful view during connection errors, and displays an in-app alert when an item changes to Ready.
- `order.view` is now part of mobile sign-in eligibility; the existing waiter and captain roles include this permission.
- `dart analyze lib` reports no issues. PHP syntax checks and Laravel route registration pass; the Android Gradle debug build succeeds. A read-only emulator check loaded an existing order and its dish/KOT statuses; order submission, kitchen state transitions, and retry behavior still need manual UAT.
- The kitchen mobile client now has a private Reverb subscription path. The waiter client still uses foreground polling; it has not yet been connected to a real-time channel, and background push remains pending.

## Phase 6 progress

- Changed the Android launcher label to `Nagarjuna Waiter` and replaced the generated Flutter launcher mark with a Nagarjuna terracotta `N` icon for adaptive and legacy Android launchers.
- Removed debug-key signing from the release build. Release signing now loads `mobile/android/key.properties` when the owner supplies it; Android's existing ignore rules exclude the key file and keystore from source control.
- The API client rejects invalid base URLs, local/placeholder release hosts, and non-HTTPS release API addresses; menu image URLs must also use HTTPS in release mode. Plain HTTP remains enabled only by the debug Android manifest for local emulator use.
- Reviewed the Android manifest: it requests network access only and disables app data backup; Sanctum tokens remain in `flutter_secure_storage`.
- `dart analyze lib` reports no issues, and the waiter and kitchen Android debug APKs build. Release builds now fail closed unless each app has its owner-managed signing file and keystore; no signed production bundle can be built until those owner keys are supplied.
- Installed the updated debug app on the connected Android emulator. The branded icon appeared in the app drawer, the saved session restored, and the assigned branch/floor/table data loaded from Laravel. Tracking an existing occupied table also loaded its order, dish, and KOT status from the new endpoint. This was read-only; no order was submitted.
- Floor/table and order-progress screens have had an emulator visual review. Release signing and the production API host remain owner/environment inputs; sign-in/menu/cart visual review and the full waiter UAT checklist remain pending.

## Delivery phases

### Phase 1 — Flutter foundation and sign-in experience

- Create a standalone Flutter app under `mobile/`; keep Laravel code in its current location.
- Establish the app structure, theme, navigation, loading/error patterns, and responsive layouts for common Android phone sizes.
- Implement the sign-in screen for registered waiter credentials with clear validation and accessible controls.
- Keep authentication behind an API/service interface, use the versioned Sanctum token contract, and store tokens with platform secure storage. Do not add a fake successful login or hard-code credentials.
- Acceptance: the Android project builds, sign-in calls Laravel, the app restores/revokes a session, and input validation and password visibility work.

### Phase 2 — Floor and table selection

- Load only the signed-in user's assigned branch and floors.
- Show table number, capacity, and Available/Occupied/Reserved/Cleaning status where configured.
- Allow an eligible waiter to open an available table and resume its active order when one exists.
- Acceptance: branch scope is enforced by the server, table states are clear, and empty/loading/error states are designed.

### Phase 3 — Menu and order composition

- Browse categories; search dishes; show image, reference selling price, Veg/Non-Veg/Egg type, current prepared-dish stock, and availability.
- Disable unavailable or sold-out dishes and prevent quantities above the latest available stock.
- Build a reviewable cart with item-level notes and order-level special instructions.
- Acceptance: the waiter can change quantities and notes before submission, and stock changes or stale menu data are handled visibly.

### Phase 4 — Submit and extend orders

- Submit a new table order and add items to an existing active order.
- Use an idempotency/request identifier so retries cannot duplicate an order or stock deduction.
- Show the server-generated order number and KOT confirmation returned by Laravel.
- Acceptance: Flutter sends item identifiers and quantities; Laravel remains authoritative for validation, stock, pricing references, kitchen mapping, and KOT creation.

### Phase 5 — Order progress and ready notifications

- Show each submitted item's New, Accepted, Preparing, Ready, Served, or Cancelled state.
- Receive kitchen-ready updates in real time when the backend broadcaster is configured; refresh pending state safely after reconnect.
- Acceptance: live updates do not create duplicate items or lose the current order view.

Current implementation refreshes while the order-progress screen is in the foreground and immediately after app resume. A configured broadcaster is still required for WebSocket delivery and background notifications.

### Phase 6 — Release readiness

- Review accessibility, network-loss behavior, authentication expiry, secure token storage, Android permissions, app identity, icon, and release configuration.
- Walk through the requirements document's waiter scenarios with the project owner and document remaining backend/provider dependencies.
- Acceptance: a release build and the agreed manual UAT checklist pass on the target Android devices.

The manual device checklist and release signing instructions are in `mobile/README.md`. Use a staging branch and test stock for order-submission UAT.

## Remaining API prerequisites for production order flows

The sign-in, floor/table, menu, order submission/addition, and branch-scoped status retrieval slices are implemented. Production readiness still depends on:

- Deploy and configure Reverb plus a queue worker for live kitchen updates. The development `.env` intentionally uses the log broadcaster; the waiter app currently refreshes order progress by foreground polling.
- Supply the production HTTPS API host and owner-managed signing keys, then complete the manual UAT and release steps in [`mobile-production-readiness.md`](mobile-production-readiness.md).

The mobile schema migrations are `database/migrations/2026_11_07_053308_create_personal_access_tokens_table.php`, `database/migrations/2026_11_08_090000_add_prepared_stock_quantity_to_menu_items_table.php`, and `database/migrations/2026_11_08_110000_create_mobile_api_idempotency_keys_table.php`. All three have been applied to the connected development database. Apply them through the project's normal Laravel deployment/migration procedure in other environments. Do not use a destructive migration reset on a database with project data.

The mobile API base URL must point to the Laravel `/api/v1` root and is supplied with `--dart-define=API_BASE_URL=...`; see `mobile/README.md` for emulator/device examples.

Every endpoint must authorize the user's company and branch on the server. Flutter must not choose another branch by changing a request parameter. The final billing integration remains separate from mobile billing/payment behavior.

## Antigravity working rules

- Work only on the current phase; inspect the existing files before editing.
- Preserve completed Laravel master modules and the existing `OrderService`.
- Do not make a backend endpoint look implemented by returning sample or hard-coded production data.
- Do not add credentials, production URLs, or secrets to source control.
- After each phase, report changed files, checks actually run, and backend dependencies still open. Never claim “100% bug-free”; report concrete validation evidence instead.
