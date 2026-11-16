# Nagarjuna authentication and authorization

Administrators provision staff accounts; public registration is disabled. Active user, company and matching active branch are checked on protected requests. Inactive roles grant no permissions. The custom `roles`, `permissions`, `user_roles` and `role_permissions` tables implement module/action access.

## Sessions and mobile tokens

Web login/logout uses Laravel's session guard and CSRF protection. Login is throttled to six attempts per minute and regenerates the session ID. Create the first administrator interactively with `php artisan auth:create-admin`; development seed accounts are not deployment credentials.

Mobile authentication is implemented with Sanctum. Waiter and kitchen login endpoints issue separate `waiter:mobile` and `kitchen:mobile` abilities with seven-day expiry. The phone stores the bearer token in platform secure storage and never persists the password. Logout/revocation removes token-associated push registrations. Every route enforces ability and relevant module permission independently of hidden UI controls.

## Scope

Super Admin/Admin manage all tenants. Company Admin is confined to its company. Other branch staff use their assigned branch. Kitchen staff additionally require explicit active station assignments for queues, TV/session endpoints, history, stock and channel authorization; an empty assignment set grants no kitchen work.

The mobile APIs derive tenant context from the account; changing request parameters cannot select another tenant. Related floor/table/menu/kitchen IDs must belong to that scope. Friendly browser KDS endpoints, legacy session KOT APIs and direct availability endpoints apply the same assignment/tenant guards. Registered TV bearer URLs authorize only their one active kitchen, with rotate/revoke controls.

## Role permissions

Waiter/Captain roles receive floor/table/menu view and order view/add/edit/cancel permissions. Kitchen Staff receive kitchen/KOT/menu view and KOT edit; Kitchen Manager also receives stock adjustment. Managers receive their branch management permissions and cancellation approval; Company Admin and management receive scoped report/settings capabilities. Exact defaults are defined in `RolePermissionSeeder`; roles remain configurable through authorized management.

New report/settings permissions are `report.view/export`, `audit-log.view/export` and `settings.view/edit`. User activity needs the audit permission in addition to report access. Stock mutations require `menu-item.stock-adjustment`, including browser availability actions; read-only stock/history require menu view. Cancellations require order.cancel and a reason; decisions require order.approve and, when configured, a different approver.

Avoid re-running the role synchronization seeder blindly on customized restaurant roles: it can replace their permission assignments. The remediation permission migration adds the new management capabilities without replacing other assignments. Production demo seeders are blocked.

## Operational integrity

The shared order service locks/reloads authoritative state, rejects skipped/backwards/closed preparation transitions and never revives fully cancelled items. Multi-station stock and cancellation accounting happens once per customer quantity. Mobile create/add submissions require a UUID request key and payload hash; uncertain writes retain that exact identity across retries.

Critical model changes, role/permission/station assignments, settings and status transitions are persisted in scoped audit/history tables without password/display-secret fields. API credentials and device tokens are not returned in reports. Configure HTTPS/WSS, private service credentials, managed workers and production logging in the deployment environment.

See [API.md](API.md) for endpoint authorization/error details and [OPERATIONS.md](OPERATIONS.md) for setup and manual acceptance. No claim of completed production UAT follows from static source review.
