# Mobile production readiness

The waiter and kitchen apps share the Laravel API at `/api/v1`, but use separate token abilities and separate Android application IDs. The project code now rejects insecure or placeholder release API URLs and refuses to produce unsigned Android release artifacts.

## Required deployment values

The following values belong to the owner or deployment environment and must not be committed to this repository:

- The production API host with a valid HTTPS certificate, supplied to both apps as `https://<api-host>/api/v1`.
- A separate approved Android upload keystore and ignored `android/key.properties` for each app. Preserve the keys and passwords in approved secret storage and backups.
- Reverb app ID, key, and secret on Laravel; public Reverb host, port, scheme, and app key supplied to the kitchen Android build. The API and Reverb hosts must be reachable by the Android devices.
- Production database credentials, `APP_KEY`, `APP_URL`, mail provider settings, and queue/cache configuration in the hosting secret manager.
- Firebase/APNs project credentials if background push alerts are required. Current app alerts run while the app is open; background push is not implemented.

## Laravel deployment

1. Set `APP_ENV=production`, `APP_DEBUG=false`, the public HTTPS `APP_URL`, and production database/secret values. Never deploy with demo credentials or the development `.env`.
2. Run `php artisan migrate --force` using the normal release process. Run `php artisan db:seed --force` only to install/update roles and permissions: `DatabaseSeeder` skips sample accounts and restaurant data in production. `UserSeeder` and `OrderDemoSeeder` reject production execution. If a database was copied from development, audit and disable or reset any demo accounts already stored there; the guard cannot remove previously seeded users.
3. Create the first super administrator interactively with `php artisan auth:create-admin`. Create real staff accounts through user management, assign each account a branch and required role/permissions, and assign kitchen stations to kitchen staff.
4. Configure an actual queue worker. For live kitchen updates, set `BROADCAST_CONNECTION=reverb`, configure production Reverb credentials, expose Reverb through TLS/WSS, and run Reverb as a managed service. `log` broadcasting only supports local development; polling continues as the kitchen app's fallback.
5. Cache production configuration with the normal deployment process and verify logs, database backups, queue health, TLS renewal, and service restart policies.

## Build and acceptance gates

- For each app, configure its own Android upload signing file and build the obfuscated signed AAB using the commands in its README. Keep each build's `--split-debug-info` output with the release artifact.
- Run both apps on the target Android devices against staging. Verify secure login/session restore/logout, branch and kitchen assignment boundaries, waiter order creation, KOT generation, all kitchen transitions, waiter status refresh, duplicate-safe retry, network loss/recovery, expired sessions, screen sizes, and accessibility.
- Confirm the kitchen board reports a live Reverb connection when configured and reconnects after interruption. Confirm polling remains functional if Reverb is unavailable.
- The production domain, upload keystores, Reverb deployment, background push credentials (if required), and owner-run staging acceptance are deployment inputs; this workspace cannot supply or certify them.
