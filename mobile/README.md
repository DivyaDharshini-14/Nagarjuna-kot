# Nagarjuna order-entry test client

This Flutter app is for temporary testing. The client's existing application places production orders through the KOT API. The API endpoints and authentication are documented in the [project README](../README.md#nagarjuna-order-integration).

## Test coverage

The app covers staff sign-in, branch-scoped floor and table selection, menu browsing, cart review, order submission and additions, order progress, and waiter-side order completion. Completing an order after its kitchen tickets are ready or served releases the table. The app stores the API bearer token in platform secure storage.

Waiters with cancellation permission can cancel individual quantities or a full order with a required reason. Cancellations apply immediately unless the branch requires manager approval, in which case authorized managers can approve or reject them in the mobile app or web queue. A branch can require a different approver, including for requests made by users with approval permission. Dish instructions can be edited while every linked KOT remains new or accepted, additional items can be added from the order-progress screen or occupied table, and ready dishes can be marked served after every assigned kitchen has marked them ready. The waiter also persists an uncertain order submission's draft and idempotency key in secure storage so a retry can recover after app restart. Stock is reversed for tracked portions whose KOT work has not started; later cancellations are logged as wastage. Order actions are shown only when the signed-in role has the required permission.

From **Edit order**, choose **Add extra** portions or **Reduce line**. Extra portions create new line/KOT work and leave the selected original line unchanged. The app saves the exact addition payload and request key per staff identity before sending; navigation or restart recovers that same request, and other mutations wait for reconciliation. Reductions are reasoned cancellations and retain approval/history/stock/wastage policy. The floor refreshes every 12 seconds while visible and on resume. An open review sheet reacts to current menu/stock changes and requires acknowledgment before submitting altered quantities.

## Run on an Android device or emulator

From this directory:

```powershell
flutter pub get
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
```

`10.0.2.2` reaches the host computer from the standard Android emulator. Start Laravel so it can accept emulator/phone connections with `php artisan serve --host=0.0.0.0 --port=8000`. For a physical phone, use the computer's LAN IP address and ensure the phone can reach the Laravel server. Release builds must use HTTPS. The debug Android manifest allows HTTP for local development only.

To build a debug APK:

```powershell
flutter build apk --debug --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
```

To check Dart code and lint issues:

```powershell
flutter analyze
```

## Live waiter updates

The order-progress screen subscribes to a private order channel when Reverb is available. Configure Reverb in Laravel and keep both the Reverb server and a queue worker running. Without those services, the screen continues to refresh order status every 12 seconds while open.

From the Laravel project root, run `php artisan reverb:install` once, set `BROADCAST_CONNECTION=reverb`, then start `php artisan queue:work` and `php artisan reverb:start --host=0.0.0.0` in separate terminals. For the standard Android emulator, launch the waiter app with the matching local Reverb values:

```powershell
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1 --dart-define=REVERB_APP_KEY=your-public-app-key --dart-define=REVERB_HOST=10.0.2.2 --dart-define=REVERB_PORT=8080 --dart-define=REVERB_SCHEME=http
```

The order screen displays whether live updates are connected. The 12-second API refresh remains active as a fallback and catches up after reconnect. Firebase Cloud Messaging sends kitchen-ready alerts in the background after the app registers its device token; foreground ready events appear in the app. Configure the test Firebase project and this app's `google-services.json` to exercise background alerts.


## Manual Android acceptance checklist

Run these checks on the agreed Android phone/emulator against staging or a designated test branch with test stock:

1. Sign in with an active waiter account; confirm the branch name and assigned floors are correct.
2. Close and reopen the app to confirm session restoration. Sign out, then confirm the app returns to sign-in and the old token no longer works.
3. Verify available, occupied, reserved, and cleaning tables; open a new table and resume an occupied table with an active order.
4. Search/filter the menu; verify unavailable and sold-out dishes cannot be added; review item notes, guest count, and order instructions.
5. Submit an order and confirm the server order number/KOT. Simulate a lost response and retry the same submission; confirm only one order and one stock deduction exist.
6. Add items to an active order; confirm a new KOT is created and existing lines remain visible.
7. In Kitchen Mobile, accept a ticket with multiple dishes and mark one dish ready. Confirm that the order shows Partially Ready and the ready dish disappears from Kitchen Display. Mark the remaining dishes ready. Confirm the Ready alert in the test client and mark the dishes served.
8. Complete an order from the waiter app after every active kitchen ticket is ready or served and any cancelled ticket is terminal; verify the table becomes Available and a new order can be started there. Confirm completion is rejected while an active KOT is still in progress.
9. Disable network during menu/status refresh, order completion, and a pending submission; restore it and verify retry/reconnect behavior without losing the current order or creating duplicates.
10. Check small and large display sizes, keyboard visibility, system text scaling, screen reader labels, and sign-in validation.

The order screen receives live WebSocket events when Reverb is configured and otherwise refreshes every 12 seconds while open. Background ready notifications use Firebase Cloud Messaging and open the relevant order when tapped.

Completed here: the updated debug app installed on the connected emulator, showed the Nagarjuna icon, restored its saved waiter session, loaded assigned branch/floor/table data, and displayed the order/dish/KOT statuses for an existing occupied table. Floor/table and order-progress layouts were visually checked. These were read-only checks; no order was submitted. The remaining checklist items still need manual UAT.

## Structure

- `lib/app/` — app theme and shared setup
- `lib/features/auth/` — authentication boundary and sign-in UI
- `lib/features/tables/` — floor/table models, repository boundary, and selection UI
- `lib/features/menu/` — branch-scoped menu, availability/stock presentation, and cart draft review
- `android/` and `ios/` — generated Flutter platform projects

## Connected Laravel endpoints

- `POST /api/v1/auth/login` accepts `email`, `password`, and `device_name`; it returns a seven-day Sanctum bearer token for active waiter/captain accounts assigned to a branch and permitted to view floors, tables, menus, and orders and add orders.
- `GET /api/v1/auth/me` validates the saved session and returns the signed-in staff and branch.
- `POST /api/v1/auth/logout` revokes the current token.
- `GET /api/v1/floors` returns the account's assigned branch, active floors, and active tables with status and active order details.
- `GET /api/v1/tables?floor_id={id}` returns tables in the user's branch, optionally limited to one floor. The server does not accept a branch selector from the app.
- `GET /api/v1/menu` returns active categories and dishes for the assigned branch, including menu type, reference price, image path, availability, and tracked prepared-stock quantity.
- `POST /api/v1/mobile/orders` submits a new table order.
- `POST /api/v1/mobile/orders/{id}/items` adds dishes to the active table order.
- `GET /api/v1/mobile/orders/{id}` returns order, dish, and kitchen-ticket status when the order belongs to the signed-in user's branch.
- `POST /api/v1/mobile/orders/{id}/items/{item}/served` marks a fully ready dish as served across all kitchen tickets that contain it.
- `POST /api/v1/mobile/orders/{id}/complete` closes an eligible order and releases its table when no other active order uses it.

Order submission sends a UUID `Idempotency-Key`; the server validates the request hash and safely returns the original result when a request is retried. Laravel rechecks branch scope, menu availability, prices, and tracked stock inside its transaction, then reuses `OrderService` to create the order and kitchen tickets. The app shows the generated order and KOT numbers. The menu manager can enable prepared-stock tracking and enter the current portions available. Items with tracked stock set to zero (or with no quantity configured) cannot be added to a mobile cart.

Before using a new environment, apply all Laravel migrations using the normal project deployment procedure. Do not reset a database containing project data.

The order-progress screen listens for authenticated KOT events over the private order channel when Reverb is configured. It also refreshes every 12 seconds while open and when the app returns to the foreground, and shows an in-app Ready alert. Live delivery requires configured Reverb, a queue worker, reachable WSS in production, and the launch defines shown above. Background Ready alerts require Firebase and server configuration. See the current [API contract](../docs/API.md) and [operations guide](../docs/OPERATIONS.md); local configuration or static checks do not establish running production services.

Apply the cancellation, stock-ledger, registered-display, and mobile-device-token migrations in each environment, then seed updated role permissions. Configure `FCM_PROJECT_ID` and a protected service-account file path on Laravel, and provide this app's Firebase Android config as `android/app/google-services.json`. See [`docs/mobile-production-readiness.md`](../docs/mobile-production-readiness.md) for deployment and staging acceptance steps.

The cancellation endpoint is `POST /api/v1/mobile/orders/{id}/cancellations`; send a required `reason` and either `cancel_all: true` or item/quantity lines. A user with `order.cancel` cancels immediately unless the branch requires manager approval; when approval is required, a user with `order.approve` can approve or reject from the mobile cancellation queue or web cancellation queue. Item instructions can be edited through `PATCH /api/v1/mobile/orders/{order}/items/{item}/instructions` before preparation starts. Apply the cancellation-history and branch approval-setting migrations with the other mobile workflow migrations in each environment.
