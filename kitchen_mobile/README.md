# Nagarjuna Kitchen Mobile

This is a separate Android Flutter app for kitchen staff. It uses the Laravel API under `/api/v1/kitchen-mobile`; the waiter app remains in `mobile/` and the browser Kitchen Display System remains available.

Apply Laravel migrations through the project's normal deployment process before launching the app in another environment. The current development database already has the kitchen assignment and permission migrations.

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

The API remains the source of ticket data. The app subscribes to a private channel for each assigned station, reconnects after network loss, and continues polling every 12 seconds as a fallback.

Kitchen accounts need the `kitchen-staff` or `kitchen-manager` role, an active company and branch, and the `kot.view` and `kot.edit` permissions. A manager must assign active kitchen stations to the account in the web user editor. Ticket reads and updates are restricted to those assigned stations. Without Reverb configuration, tickets refresh every 12 seconds; background push notifications are not configured.

The initial kitchen board supports ticket acceptance, preparation, and ready status. The browser KDS continues to be the project's existing large-screen kitchen display.

## Android release setup

Release builds require an HTTPS API URL ending in `/api/v1`, a real production host, and an owner-managed upload keystore. The API client rejects HTTP, emulator/localhost, placeholder, and malformed release addresses. Gradle stops release builds when signing configuration or the referenced keystore is missing; it never uses the debug key.

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
