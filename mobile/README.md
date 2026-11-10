# Nagarjuna Waiter

Flutter mobile client for Nagarjuna's waiter and captain workflow. The Laravel web application remains in the repository root; this app lives separately in `mobile/`.

## Current phase: Phase 6 release readiness

The app contains staff sign-in, branch-scoped floor/table selection, menu browsing, cart review, order submission/additions, and order progress. Credentials are sent only to the configured Laravel API, and the returned bearer token is stored with platform secure storage. The app never stores the password. Android release setup is documented below; signing and full manual UAT remain pending.

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

## Release build setup

Release builds require an HTTPS API URL ending in `/api/v1` and an owner-managed Android upload keystore. The release client rejects HTTP, emulator/localhost, placeholder, or malformed API addresses. Gradle now stops a release build if `android/key.properties` or the referenced keystore is missing; it never falls back to the debug key.

Create `android/key.properties` locally (it is ignored by Git) with the values for your upload keystore:

```properties
storePassword=<keystore password>
keyPassword=<key password>
keyAlias=upload
storeFile=../upload-keystore.jks
```

Keep `upload-keystore.jks` and its passwords in the project's approved secret storage and backup. Do not commit them. Confirm the final Android application ID (`com.nagarjuna.nagarjuna_waiter`) and version with the project owner before publishing; changing the application ID after release creates a different app listing.

If the owner has not supplied an upload keystore, create one locally from the `mobile/` directory and store its passwords securely:

```powershell
keytool -genkey -v -keystore android/upload-keystore.jks -keyalg RSA -keysize 2048 -validity 10000 -alias upload
```

Increment the `version` build number in `pubspec.yaml` for each store release. Build an obfuscated, signed release bundle after configuring the HTTPS API host and local signing file. Keep the generated symbols with the matching release for crash analysis:

```powershell
flutter build appbundle --release --dart-define=API_BASE_URL=https://api.your-domain.example/api/v1 --obfuscate --split-debug-info=build/symbols/waiter-1.0.0
```

The signed Play Store bundle is written to `build/app/outputs/bundle/release/app-release.aab`. Replace the example domain with the production API host. The bundle cannot be built until the upload keystore is configured.

See [`docs/mobile-production-readiness.md`](../docs/mobile-production-readiness.md) for the shared Laravel/Reverb deployment gates and cross-app acceptance checklist.

## Manual Android acceptance checklist

Run these checks on the agreed Android phone/emulator against staging or a designated test branch with test stock:

1. Sign in with an active waiter account; confirm the branch name and assigned floors are correct.
2. Close and reopen the app to confirm session restoration. Sign out, then confirm the app returns to sign-in and the old token no longer works.
3. Verify available, occupied, reserved, and cleaning tables; open a new table and resume an occupied table with an active order.
4. Search/filter the menu; verify unavailable and sold-out dishes cannot be added; review item notes, guest count, and order instructions.
5. Submit an order and confirm the server order number/KOT. Simulate a lost response and retry the same submission; confirm only one order and one stock deduction exist.
6. Add items to an active order; confirm a new KOT is created and existing lines remain visible.
7. Change KOT item states in the kitchen workflow through New, Accepted, Preparing, Ready, and Served; confirm each state appears in the waiter view and the Ready alert appears while that view is open.
8. Disable network during menu/status refresh and during a pending submission; restore it and verify retry/reconnect behavior without losing the current order or creating duplicates.
9. Check small and large display sizes, keyboard visibility, system text scaling, screen reader labels, and sign-in validation.
10. On the release candidate, verify HTTPS succeeds, HTTP is rejected, the Nagarjuna launcher icon/name appear, and the bundle is signed with the approved upload key.

Background ready notifications and WebSocket delivery are not available until a broadcaster is configured; item 7 currently refreshes while the progress screen is open.

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

Order submission sends a UUID `Idempotency-Key`; the server validates the request hash and safely returns the original result when a request is retried. Laravel rechecks branch scope, menu availability, prices, and tracked stock inside its transaction, then reuses `OrderService` to create the order and kitchen tickets. The app shows the generated order and KOT numbers. The menu manager can enable prepared-stock tracking and enter the current portions available. Items with tracked stock set to zero (or with no quantity configured) cannot be added to a mobile cart.

Before using a new environment, apply the Laravel Sanctum `personal_access_tokens`, prepared-stock quantity, and mobile API idempotency migrations using the normal project deployment procedure. All three are applied to the connected development database. Do not reset a database containing project data.

The order-progress screen refreshes every 12 seconds while it is open and refreshes again when the app returns to the foreground. It can show an in-app Ready alert while the screen is active. This Laravel project does not yet have a configured broadcaster/WebSocket service, so real-time delivery and background notifications require that infrastructure and an authenticated mobile connection.
