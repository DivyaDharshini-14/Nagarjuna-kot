# Nagarjuna operator and deployment guide

Billing software integration, payment collection and invoicing are excluded. This guide covers the waiter app, kitchen app, browser displays and management features implemented in this repository.

## Staff workflows

**Administrator or branch manager:** create active companies/branches, floors/tables, categories/menu types, dishes and kitchens. Classify kitchen type, map every orderable dish to its preparation station(s), and enable prepared-stock tracking where needed. Create staff accounts with the correct company/branch and active roles. Assign explicit kitchen stations to kitchen accounts. A kitchen account with no assignments sees no kitchen work.

**Waiter or captain:** sign in, select a floor and available table, choose dishes, quantities and notes, review, then submit. An occupied table with an active order resumes that order. The menu and open review reflect availability/stock changes. Extra portions create a new submitted batch; the **Add extra** action labels this explicitly. Reducing an existing line requests cancellation and requires a reason. Ready dishes can be marked Served after all assigned preparation stations are Ready/Served. Complete the order only after the guests have left and the table is clear.

If submission is uncertain, use the saved request's retry/check action. Do not start another equivalent submission with a new key. Pending quantity additions survive navigation, restart and signing in again as the same staff account. Other changes are blocked until the saved addition is reconciled. A server success followed by local-storage failure also retains the original key, so replay remains safe.

**Kitchen staff:** sign in and select an assigned station or all assigned stations. Use New, Accepted, Preparing, Ready, Completed and Cancelled views. Advance New → Accepted → Preparing → Ready; dispatch/Served is available in the browser or waiter workflow. Cards show received clock time, elapsed age, remaining quantities, cancelled quantities and notes. Enable the sound icon for foreground incoming KOT alerts; Android volume/channel settings and branch sound permission also apply. View-only staff can open stock and paged history but cannot adjust it.

**Kitchen manager or authorized stock operator:** open Stock and record stock-in, wastage, damaged portions or a directional correction with a reason. Set Available, Temporarily Unavailable or Sold Out. Tracking must be enabled in web menu management before mobile stock-in. Negative balances fail. All station work for a dish shares one branch stock quantity.

**Cancellation approver:** open the cancellation queue in the waiter app or web application. Review the requested quantities and reason, approve or reject with a decision note. When branch approval is enabled, a different authorized person must approve. The policy restores stock only when none of the assigned kitchens has started preparation; otherwise the cancelled customer quantity is recorded once as wastage. Both decisions and quantities remain in history.

## Management settings and reports

Branch settings at `/settings` require `settings.view`; saving requires `settings.edit`. Authorized managers can select only accessible branches. Controls include order/KOT prefixes and digit counts, daily versus continuous KOT sequence, sound permission, low-stock threshold and separate cancellation approval. Existing numbers remain unchanged and counters cannot be manually reset. Company/branch contact details, dish tracking and kitchen display registration remain on their master pages.

Reports at `/reports` require `report.view`. Available reports: orders, KOTs, kitchen summary, dish quantities, stock-in/opening stock, stock deductions, adjustments, wastage/damage, cancellations, preparation times and user activity. Use date, company, branch, kitchen, order number and status where applicable. User activity uses an action filter instead of order/kitchen/status and requires `audit-log.view`. CSV export requires `report.export`, plus `audit-log.export` for activity. Every query/export retains tenant and station scope. Dish quantities count customer lines once even when several kitchens prepare them. Stock kitchen filtering uses current dish mappings.

Audit capture starts with deployment of the observer; earlier activity is not fabricated. The database retains detailed changes and status history. Low-stock thresholds affect the web stock classifications, filters and summary counts.

## Browser displays

Friendly URLs are derived from branch and kitchen codes, such as `/{branchCode}/{kitchenCode}`. They require a signed-in authorized account. The all-station screen remains limited to accessible kitchens. Both friendly and registered displays have stage sections, completed/cancelled history, remaining quantities, connection state, live updates and fallback refresh.

For an unattended TV, open its kitchen editor and register a display. Copy the generated `/kds/{token}` URL; it is shown once and acts as a kitchen-scoped bearer credential. Rotate or revoke it when the display changes or the URL is exposed. The URL cannot select another station. Keep it private. Sound needs operator activation to satisfy browser audio restrictions. Serve `public/js/kds-screen.js` with the deployed application.

## Deploy the backend

1. Configure the intended environment's HTTPS host, MySQL connection, application key and timezone. Set `APP_ENV=production`, `APP_DEBUG=false` and `APP_TIMEZONE` to the restaurant's operating timezone, normally `Asia/Kolkata` for Indian branches. Keep the original encryption key when restoring an existing database.
2. Create and verify a database backup. Review pending migrations with `php artisan migrate:status`, then apply them with `php artisan migrate --force` during the planned deployment window. The November 13 remediation adds sequences/settings/audit/status-history tables, an index for new KOT numbering periods, permissions and kitchen type. It does not renumber legacy orders or erase operational data.
3. The permission migration adds the new report/settings permissions to management roles. For a fresh installation use the reviewed production-safe seeders. Re-running `RolePermissionSeeder` synchronizes role permissions and can replace custom role assignments; review changes before running it on a configured restaurant. Never run demo seeders in production.
4. Create real staff through management or `php artisan auth:create-admin`. Configure every dish mapping and kitchen staff assignment. Review any development accounts copied into a shared environment.
5. Configure `BROADCAST_CONNECTION=reverb`, production Reverb credentials and a TLS reverse proxy. Keep Reverb and `php artisan queue:work` supervised and monitored. Restart workers after code changes. Both broadcasting and FCM jobs need workers; polling is a fallback, not proof of live delivery.
6. Configure `FCM_PROJECT_ID` and `FCM_SERVICE_ACCOUNT_PATH` in deployment secret storage. The service account belongs on the server, outside source/public directories. Register the waiter and kitchen Android application IDs separately in Firebase and use each app's matching client configuration. Verify actual delivery on devices.
7. Build frontend assets if required by the existing deployment, expose uploaded dish images through the configured public storage link, and ensure writable private storage/cache/log directories. Run `php artisan config:cache` and `php artisan view:cache` only with correct target environment values. Check queue failures, TLS renewal and service restart policies.
8. Enable the backup scheduler only after the backup destination/binary have been configured and a restoration exercise has passed. Do the acceptance walkthrough below before releasing signed mobile artifacts.

## Backup and recovery

`php artisan database:backup` creates a consistent MySQL/MariaDB dump or file-based SQLite backup. The default destination is `storage/app/private/backups`, outside the public web root. A `.sha256` checksum accompanies each file. The command uses the configured DB connection, keeps the password out of process arguments, and removes incomplete output on failure. MySQL requires a compatible `mysqldump` and privileges for routines/triggers included by the dump. SQLite requires the sqlite3 extension.

Configuration:

```dotenv
DB_BACKUP_ENABLED=false
DB_BACKUP_TIME=03:00
DB_BACKUP_MYSQL_BINARY=mysqldump
DB_BACKUP_TIMEOUT=600
# Optional absolute path on the deployment host:
# DB_BACKUP_DIRECTORY=/private/nagarjuna-backups
```

For automatic daily backups, choose a private destination, enable `DB_BACKUP_ENABLED`, and run Laravel's scheduler through the host's scheduled task/cron/service. Execution time uses `APP_TIMEZONE`. The command does not delete older backups automatically. Agree retention, encrypted off-host copies, access controls, backup age monitoring and recovery targets with the restaurant operator.

A local MySQL backup and its checksum were successfully produced before the remediation migrations were applied on 13 November 2026. That confirms local backup execution; it is not evidence of off-host protection or successful restoration.

Restoration procedure:

1. Select the intended backup, verify its checksum and obtain the matching application encryption key from approved secret storage. Confirm which company/branch service period it covers.
2. Create a separate empty recovery database on a controlled host. Import the dump into that database using database administration tooling. Do not import into the live restaurant database for an exercise. This dump does not create or select an arbitrary replacement database.
3. Point an isolated application copy at the recovered database. Keep queue workers, FCM and externally reachable displays disabled so replayed jobs cannot notify live staff.
4. Check migration status, company/branch/user scope, order and stock totals, cancellation history, sequences and the newest records. Reconcile ledger balances to current stock and inspect a multi-kitchen order. Verify authentication and encrypted fields with the matching key.
5. Record duration, backup age, restored period, discrepancies and recovery sign-off. A real cutover requires a maintenance window, new backup of the current state, controlled worker shutdown/restart and staff communication. Revoke or rotate restored sessions/display credentials when required by the recovery plan.

No destructive restore or production cutover was performed by the remediation work.

## Android builds

Use the SDK/dependency versions recorded in each app. Debug builds can target the local emulator API; they are development artifacts. Release builds need a real HTTPS API host, public Reverb application key, TLS/WSS endpoint and each app's own upload signing key and Firebase registration. Existing local signing files must be validated and backed up, not overwritten. Do not commit keystores, passwords, service-account keys or device tokens.

From each app directory, use its README's `flutter build appbundle --release` command with real deployment values. Preserve obfuscation symbols with the matching version. Gradle rejects missing signing/Firebase data, non-HTTPS hosts and invalid Reverb release settings. Debug build success does not validate production certificates, push delivery or target-device behavior.

## Manual acceptance walkthrough

The repository asks contributors not to write or run automated tests. Static analysis/build checks are used here; operational acceptance is a manual staging exercise. Record device/build/API version, branch/accounts, result and evidence for every case.

| Case | Required outcome |
|---|---|
| Tenant setup | Multiple companies/branches and several kitchens; users cannot query another tenant or an unassigned station through mobile, session API, KDS feed or channel auth. Empty kitchen assignments reveal no work. |
| Three-kitchen order | One submission with three mapped dishes creates only the correct stations' work; order/KOT confirmation is correct. |
| Several stations for one dish | Every mapped station receives the full quantity; stock falls once. One station Ready does not make the customer dish Ready until all are ready. |
| Missing/inactive mapping | Dish is unavailable in the waiter menu; direct submission fails atomically without creating order work or deducting stock. |
| Add and edit | Extra portions create new lines/KOTs with explicit labels. Pre-preparation instructions update every station. Reductions use the reason/approval policy. |
| Uncertain writes | Drop the response after server commit, navigate/restart, sign in as the same staff member and check/retry the saved operation. Original key/payload returns the same result; no extra line, ticket or deduction. Repeat for main composer and Add extra. |
| Numbering concurrency | Concurrent orders in different branches and simultaneous additions to different kitchens allocate distinct appropriate sequence numbers. Daily reset is interpreted in configured timezone. |
| Kitchen progression | Accept/Prepare/Ready are sequential, repeat-safe and reject stale/backwards/closed-order requests. Concurrent cancellation cannot revive cancelled work. |
| Cancellation | Partial/full, immediate/approved/rejected cases retain reason and actors. Fully cancelled dishes stay Cancelled through later ticket changes. Every station cancels the quantity. Stock returns once only if none started; otherwise one wastage record, no stock return. |
| Available portions | Two waiters compete for last stock; no negative balance. Open review reacts to menu changes and requires acknowledgment; stale web dish edits cannot overwrite a concurrent deduction. |
| Stock/history | Authorized stock-in/wastage/damage/correction and all three availability states work with reasons. Read-only staff can page stock/history but cannot mutate. More than 100 completed/cancelled tickets remain browsable. |
| TV paths | Friendly and registered displays show remaining/cancelled quantities, stage separation and history. Sound requires operator enable and branch permission. A revoked TV credential stops working. |
| Live delivery | Running Reverb/queue delivers new work and Ready updates to phones/TVs without manual refresh. Background FCM works with app minimized and from notification-open. Denied Android permission fails visibly without blocking orders. |
| Reconnect | Interrupt device/network/Reverb, submit work elsewhere, restore connectivity and verify warning, backoff and complete catch-up without duplicates. Repeat after Android resume. |
| Serve and release | Ready/Served mixed station state can be served once. Completion fails while preparation or approval remains; eligible completion releases the table. |
| Reports/settings | Report filters and CSV obey tenant/permission scope; dish/customer quantities and stock totals reconcile. New numbering format, sound permission, low-stock threshold and approval setting take effect. Audit retains critical changes. |
| Backup/recovery | Restore a checksummed copy into an isolated DB and reconcile order/stock/history; record recovery time and age. |
| Device release | Login, expiry, logout, font scaling, small/large screens, keyboard, accessibility, signed artifact installation, HTTPS/WSS and production notification channels pass on target devices. |

Production domain, valid signing ownership, externally configured services and manual UAT sign-off remain environment acceptance responsibilities. They cannot be established by source inspection or a debug APK.
