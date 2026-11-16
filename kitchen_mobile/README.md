# Nagarjuna Kitchen Mobile

This is a separate Android Flutter app for kitchen staff. It uses the Laravel API under `/api/v1/kitchen-mobile`; the waiter app remains in `mobile/` and the browser Kitchen Display System remains available.

Apply Laravel migrations through the project's normal deployment process before launching the app in another environment. The current development database already has the kitchen assignment and permission migrations.

Kitchen managers can manage assigned-dish availability, record stock-in/wastage/damage/corrections, and inspect stock history. Kitchen staff can view stock and history with menu-view permission. Every stock movement and availability change requires a reason; negative balances are rejected. Approved waiter cancellations appear as cancelled quantities on KOTs.

Cards show received clock time and elapsed age. Enable the sound icon for foreground new-KOT alerts; the preference is saved on the device and branch settings may disable sound. Completed/cancelled orders, stock dishes and transaction history have page controls instead of fixed history limits. Fully cancelled dishes display Cancelled and cannot be revived by preparation actions. A dish mapped to several stations remains one customer quantity and one stock deduction; its overall Ready state waits for all stations.

## Run on the Android emulator

Start Laravel so it listens on `0.0.0.0:8000`, then run:

```powershell
cd kitchen_mobile
flutter pub get
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
```

For a physical Android phone, use the development computer's reachable LAN address instead of `10.0.2.2`. Release builds require HTTPS.

## Live kitchen updates

From the Laravel project root, run `php artisan reverb:install` once to create local Reverb credentials and set `BROADCAST_CONNECTION=reverb`. Keep `php artisan queue:work` running so after-commit KOT events are delivered, and start Reverb in a second terminal with `php artisan reverb:start --host=0.0.0.0`.

For an Android emulator, pass the public Reverb app key from `.env` and the emulator-reachable host when launching Flutter:

```powershell
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1 --dart-define=REVERB_APP_KEY=your-public-app-key --dart-define=REVERB_HOST=10.0.2.2 --dart-define=REVERB_PORT=8080 --dart-define=REVERB_SCHEME=http
```

The API remains the source of ticket data. The kitchen board opens on **All assigned** so staff see KOTs from every station assigned to their account; station chips can narrow the board when needed. The aggregate `/kitchen-mobile/kot` endpoint and live subscriptions cover those assigned stations, and the app continues polling every 12 seconds as a fallback.

Kitchen accounts need the `kitchen-staff` or `kitchen-manager` role, an active company and branch, and the `kot.view` and `kot.edit` permissions. A manager must assign active kitchen stations to the account in the web user editor. Ticket reads and updates are restricted to those assigned stations. Without Reverb configuration, tickets refresh every 12 seconds. Firebase Cloud Messaging alerts staff about new and cancelled tickets while the app is in the background; foreground alerts refresh the board. Configure this app's Firebase Android registration and `android/app/google-services.json` before release.

The kitchen board supports ticket acceptance, preparation, and ready status; each dish also shows its own current state and cancelled quantity. Separate filters let staff review active queues, recently completed orders, and orders or items with cancellation history. The history endpoints remain scoped to the staff member's assigned kitchens. The browser KDS continues to be the project's existing large-screen kitchen display.

## Android release setup

Release builds require an HTTPS API URL ending in `/api/v1`, a TLS Reverb host and public app key, an owner-managed upload keystore, and this app's Firebase `google-services.json`. The API client rejects HTTP, emulator/localhost, placeholder, and malformed release addresses. Gradle stops release builds when signing configuration, the referenced keystore, Firebase configuration, or valid WSS settings are missing; it never uses the debug key.

Create `android/key.properties` locally with the values for this app's upload keystore. This file and the keystore are ignored by Git:

```properties
storePassword=<keystore password>
keyPassword=<key password>
keyAlias=upload
storeFile=../upload-keystore.jks
```

Use the approved upload key for this application ID (`com.nagarjuna.nagarjuna_kitchen`). Keep the keystore and passwords in approved secret storage. Do not reuse an existing Play Store application's key or commit signing files. Increment the `version` build number in `pubspec.yaml` for each store release.

Build an obfuscated, signed Play Store bundle after setting the production API URL and signing file. Keep the generated symbols with the matching release for crash analysis:

```powershell
flutter build appbundle --release --dart-define=API_BASE_URL=https://api.your-domain.example/api/v1 --dart-define=REVERB_APP_KEY=your-public-app-key --dart-define=REVERB_HOST=realtime.your-domain.example --dart-define=REVERB_PORT=443 --dart-define=REVERB_SCHEME=https --obfuscate --split-debug-info=build/symbols/kitchen-1.0.0
```

The signed bundle is written to `build/app/outputs/bundle/release/app-release.aab`. Replace both example hosts with the deployed HTTPS API and WSS Reverb hosts. Live KOT delivery also requires production Reverb credentials, a running queue worker, and a TLS-terminated Reverb service. Without those services, the kitchen app falls back to 12-second polling.

See [`docs/mobile-production-readiness.md`](../docs/mobile-production-readiness.md) for the shared Laravel deployment gates and cross-app acceptance checklist.

## UI release check

Before release, complete the [kitchen UI acceptance walkthrough](../docs/kitchen-mobile-ui-acceptance.md) on the service phones, including large font/display settings, narrow screens, landscape, and open keyboards. Repeat it after changing shared typography, inputs, cards or dialogs. Static analysis and an APK build do not replace this visual check.

Keep availability controls expanded with variable item heights, and keep dialog titles, fields, counters and actions inside their scrollable body. Stock availability displays the persisted server value until a reason is saved successfully.

## Browser KDS display

Both browser KDS paths subscribe to live updates, show stage sections and paginated completed/cancelled history, and retain automatic fallback refresh. Remaining/cancelled quantities and station/tenant authorization are shared across paths. In the web app, open a kitchen editor and register its TV display. The generated URL is shown once and permits viewing/updating only that station. Keep it private; rotate/revoke when exposed or retired. Deploy `public/js/kds-screen.js` with Laravel. See [API.md](../docs/API.md) and [OPERATIONS.md](../docs/OPERATIONS.md) for current contracts, setup and manual acceptance.
