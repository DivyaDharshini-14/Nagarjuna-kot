# Nagarjuna Restaurant KOT Management

Laravel 12 / PHP 8.2 / MySQL backend, Flutter waiter/captain app, Flutter kitchen app and browser Kitchen Display System. The system manages tenant-scoped tables, prepared dishes, kitchen routing, stock, cancellations and live preparation status. Billing software integration, invoices and payments are excluded from this work.

## Project layout

| Location | Purpose |
|---|---|
| `app/`, `routes/`, `database/`, `resources/` | Laravel management, API, business rules, schema and browser KDS |
| `mobile/` | Waiter/captain Android Flutter app |
| `kitchen_mobile/` | Kitchen staff Android Flutter app |
| `public/js/kds-screen.js` | Friendly browser KDS live client |
| `docs/` | Requirements audit, API contract, data model and operations |

## Development setup

Use the repository's Composer and Flutter lockfiles. On a fresh installation, install Composer dependencies, copy `.env.example` to `.env`, configure the database and generate an application key. Never replace the encryption key of an existing database. Apply pending migrations without resetting restaurant data. Seed only the reviewed environment-appropriate roles/permissions, then create an administrator with `php artisan auth:create-admin`.

Run Laravel with `php artisan serve --host=0.0.0.0 --port=8000`. Live updates also need a configured Reverb service and `php artisan queue:work`. From either Flutter app, launch with `flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1`; see each app README for full Reverb and release defines. A physical phone uses the development computer's reachable LAN host.

Create active company/branch/floor/table/menu/kitchen records, map every dish to its stations and assign kitchen accounts explicitly. Enable stock tracking and enter prepared portions before ordering tracked dishes. Apply code and schema together: the remediation adds sequences, settings, audits, status history and kitchen classification.

## Behavior

A dish can require several stations. Each receives the full customer quantity, while stock is deducted once. The customer item waits for all stations to become ready. Additional portions create new KOT work; cancellations reach all stations and restore stock only when no station started preparation. The mobile submission key protects retries, including saved uncertain quantity additions after restart.

Both browser display paths and mobile APIs enforce company/branch/station permissions. Management includes filtered reports and CSV exports, branch settings and critical-operation histories. `php artisan database:backup` writes a private checksummed backup; scheduled/off-host backups and recovery exercises require deployment configuration.

## Documentation

- [API contract](docs/API.md)
- [Database and transaction design](docs/DATABASE.md)
- [Operator workflows deployment backup and manual acceptance](docs/OPERATIONS.md)
- [Requirements audit and current remediation status](docs/mobile-requirements-audit-2026-11-13.md)
- [Waiter setup](mobile/README.md) and [kitchen setup](kitchen_mobile/README.md)
- [Authentication and authorization](docs/WEB_AUTHENTICATION_AND_AUTHORIZATION.md)

The workspace guidance uses manual operational testing. Static analysis, PHP/Blade/JavaScript validation and Android builds do not replace staging/device acceptance. Production credentials, TLS hosting, signed release ownership, push delivery, backup restoration and UAT must be verified in the intended environment.
