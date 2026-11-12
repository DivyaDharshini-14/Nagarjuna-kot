# Mobile production readiness

The waiter and kitchen apps share the Laravel API at `/api/v1`, but use separate token abilities and separate Android application IDs. The project code now rejects insecure or placeholder release API URLs and refuses to produce unsigned Android release artifacts.

## Required deployment values

The following values belong to the owner or deployment environment and must not be committed to this repository:

- The production API host with a valid HTTPS certificate, supplied to both apps as `https://<api-host>/api/v1`.
- A separate approved Android upload keystore and ignored `android/key.properties` for each app. Preserve the keys and passwords in approved secret storage and backups.
- When both signing files are present, the Gradle release checks reject identical keystore files for the waiter and kitchen apps.
- Reverb app ID, key, and secret on Laravel; public Reverb host, port, scheme, and app key supplied to both Android builds. The API and Reverb hosts must be reachable by the Android devices.
- Production database credentials, `APP_KEY`, `APP_URL`, mail provider settings, and queue/cache configuration in the hosting secret manager.
- A Firebase project with a separate Android app registration for each application ID, each app's `google-services.json`, and a server service-account file mounted outside the repository. Set `FCM_PROJECT_ID` and `FCM_SERVICE_ACCOUNT_PATH` in Laravel's secret manager.

## Laravel deployment

1. Set `APP_ENV=production`, `APP_DEBUG=false`, the public HTTPS `APP_URL`, and production database/secret values. Never deploy with demo credentials or the development `.env`.
2. Run `php artisan migrate --force` using the normal release process. Then run `php artisan db:seed --class=PermissionSeeder --force` followed by `php artisan db:seed --class=RolePermissionSeeder --force`. Do not run demo seeders in production. If a database was copied from development, audit and disable or reset any demo accounts already stored there.
3. Create the first super administrator interactively with `php artisan auth:create-admin`. Create real staff accounts through user management, assign each account a branch and required role/permissions, and assign kitchen stations to kitchen staff.
4. Configure an actual queue worker. For live kitchen and waiter updates, set `BROADCAST_CONNECTION=reverb`, configure production Reverb credentials, expose Reverb through TLS/WSS, and run Reverb as a managed service. `log` broadcasting only supports local development; both apps retain polling fallbacks.
5. Cache production configuration with the normal deployment process and verify logs, database backups, queue health, TLS renewal, and service restart policies.

## Build and acceptance gates

- Register `com.nagarjuna.nagarjuna_waiter` and `com.nagarjuna.nagarjuna_kitchen` as separate Android apps in Firebase. Place each matching `google-services.json` under that app's `android/app/`; release Gradle tasks fail if the file is absent. Verify notification permission and channel settings on target devices.
- For each app, configure its own Android upload signing file and build the obfuscated signed AAB using the commands in its README. Supply the production HTTPS API URL and TLS Reverb settings to both builds. Keep each build's `--split-debug-info` output with the release artifact.
- Run both apps on the target Android devices against staging. Verify secure login/session restore/logout, branch and kitchen assignment boundaries, waiter order creation, KOT generation, kitchen transitions, per-dish ready-to-served changes, waiter status refresh, completed/cancelled kitchen history, order completion only after active tickets are ready/served and cancelled tickets are terminal, table release, duplicate-safe retry, network loss/recovery, expired sessions, screen sizes, and accessibility.
- Confirm the kitchen board reports a live Reverb connection when configured and reconnects after interruption. Confirm the waiter order screen receives KOT status changes over its private order channel, reconnects after interruption or app resume, and retains polling as a fallback.
- The production domain, Firebase project/config files, upload keystores, Reverb deployment, and owner-run staging acceptance are deployment inputs; this workspace cannot supply or certify them.

## New workflow acceptance

Apply all pending migrations through `php artisan migrate --force`, then run the `PermissionSeeder` and `RolePermissionSeeder` classes to update role permissions. Deploy application code and migrations together. Run broader production seeders only after reviewing `DatabaseSeeder` and the target environment's existing data.

The workflow migrations include `2026_11_11_090000_create_stock_transactions_and_item_cancellations_tables.php`, `2026_11_11_100000_add_registered_display_credentials_to_kitchens.php`, `2026_11_11_110000_add_stock_reversed_quantity_to_item_cancellations.php`, `2026_11_11_130000_add_wastage_quantity_to_item_cancellations.php`, and `2026_11_11_140000_add_cancellation_approval_setting_to_branches.php`.

The FCM registration migration is `2026_11_11_120000_create_mobile_device_tokens_table.php`. Device tokens are encrypted at rest and tied to the current Sanctum token; revoking the session removes its registrations. KOT notifications are queued after database commit. Keep a monitored queue worker running and store the FCM service-account file in host secret storage, outside the source tree.

- Verify waiter partial-item and full-order cancellation with reasons, mobile and web manager approval/rejection, requester/decision audit history, KOT updates and alerts, stock return before preparation, and wastage logging after preparation starts.
- Enable the branch's separate-approver setting and verify even a user with approval permission cannot approve their own cancellation.
- Verify item-note modification updates the order and every linked KOT before preparation, and is rejected once preparation starts. Verify additional items create new KOT tickets without replacing existing tickets.
- Verify kitchen mobile stock-in, wastage, damaged portions, manual correction, reason-required availability changes, stock history, assigned-kitchen scope, and rejection of negative stock.
- Register a kitchen TV URL and test that it only accesses one kitchen. Verify live tickets, sound after the operator enables it, connection warnings, reconnect/fallback, then revoke the URL and confirm the old URL stops working.
- Force-close the waiter app during an uncertain order submission, reopen it, retry the saved submission, and verify it recovers the same order without duplicate KOTs or stock deduction.
