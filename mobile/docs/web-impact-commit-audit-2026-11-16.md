# Nagarjuna commit audit: mobile scope and web impact

Reviewed on 16 November 2026 (Asia/Calcutta).

Repository snapshot: Anees-Dev at 32aa02153d17a7788ba5ea428ac25c3094e28742.

**Finding: Anees work was not limited to the mobile applications and their APIs.** Two feature commits changed web screens/controllers/routes; two other commits changed shared backend behavior. The latest rollback removes the web/backend expansion from 23f00bd, but does not remove the older Anees changes.

This report reviews scope and attribution, not whether each feature is correct or has passed device testing.

## Coverage and method

- Reviewed all 39 commits reachable from the current HEAD: 17 non-merge commits and 22 merge commits.
- Reviewed the five Anees-authored non-merge commits in detail, including the backend patches and their web/mobile callers.
- Reviewed every other non-merge commit's changed paths and web areas to distinguish other developers' work.
- Inspected merge resolution differences. A merge importing main is not treated as newly authored Anees web work.
- Excluded three stash backup objects from project history: 90a58a6, 0cc3fd3 and 6200c2d.
- Compared the current non-mobile tree with a4e1f61, the version immediately before 23f00bd, and with the web team's a3524c9 baseline.
- No application files, database records, migrations, branches, or commits were changed during this audit. This Markdown report is the only new file.
- No automated tests, builds, database queries, or network fetches were performed for this review.

The working tree was clean when the review began. Both HEAD and the local origin/Anees-Dev tracking reference point to 32aa021. This establishes the local tracking state; a fresh remote/server state was not checked.

## Anees feature and rollback commits

File counts compare each non-merge commit with its parent. "Mobile paths" means files inside mobile/ or kitchen_mobile/, including their READMEs and generated tracked files.

| Commit | Date | Files / mobile paths | Web impact | Current source status |
|---|---|---:|---|---|
| 49f4eab | 11 Nov | 89 / 40 | Direct web screens, controllers and routes; shared services, schema and permissions, alongside mobile/API work. | Older changes remain in the shared baseline, with subsequent web-team modifications. |
| 2d83cbe | 11 Nov | 7 / 2 | No web screen edits. Shared cancellation approval behavior and restaurant-manager permission defaults changed. | Not undone by 32aa021. |
| 4f7b1dc | 12 Nov | 16 / 10 | No web screen, schema or permission edits. Shared order status logic used by web callers changed. | Not undone by 32aa021. |
| 23f00bd | 16 Nov | 84 / 21 | Extensive web/backend changes: reports, settings, audit, backup, browser KDS, masters, stock and shared order logic. | All 63 paths outside the mobile apps reversed by 32aa021. Its 21 mobile paths remain. |
| 32aa021 | 16 Nov | 63 / 0 | Reverses the 63 non-mobile paths from 23f00bd. | Current committed rollback. No mobile files changed by this commit. |

None of the four feature commits above can accurately be described as strictly "mobile app code and isolated mobile API creation only."

## 49f4eab: the earliest substantial scope expansion

Full commit: 49f4eaba58a40cd3467e8061cc4a39b82e4c0877.

Title: completed Kitchen mobile application workflow.

The 89 changed files comprise 40 mobile paths, three documentation files and 46 backend/web/configuration/database paths. Those 46 include 14 direct web files: six non-API controllers, seven Blade views and routes/web.php.

| Web feature | Changed files | What changed |
|---|---|---|
| Branch cancellation policy | BranchController.php; branches/form.blade.php | Added validation and a checkbox for whether cancellations require manager approval. |
| Cancellation approval website | New CancellationRequestController.php; new cancellations/index.blade.php; partials/sidebar-nav.blade.php | Added a pending cancellation page, approve/reject actions, rejection reasons and navigation. |
| Kitchen TV registration | KitchenController.php; kitchens/edit.blade.php | Added creation, replacement and revocation of token-based browser TV URLs. |
| Registered browser KOT display | KotController.php; kots/display.blade.php | Added token-authenticated display/status operations, waiting time, sound controls, WebSocket reconnect, remaining/cancelled quantities and recent cancellations. |
| Web menu management | MenuItemController.php; menu-items/form.blade.php | Added stock/availability reasons, ledger writes and menu-change broadcasts. |
| Web order status | OrderController.php; orders/show.blade.php | Removed direct cancelled-status selection and validation in favor of the new cancellation workflow. |
| Browser routing | routes/web.php | Added TV registration/display/broadcast/status and cancellation review/decision routes. |

Controller names above are under app/Http/Controllers/. View names are under resources/views/.

The registered TV URL is a separate browser feature. A valid bearer URL permits that kitchen's display/status operations without a normal account login; registration/revocation require kitchen-edit permission. Tokens are stored hashed and checked against the relevant kitchen/company/branch state.

The same commit also changed shared backend behavior:

- OrderService introduced cancellation requests and decisions, stock reversal versus wastage, order total adjustments, table release and cancellation broadcasts.
- Existing web order creation/addition gained stock ledger writes. Direct cancellation was blocked and completion with pending cancellations rejected.
- Shared status aggregation was changed to handle cancelled tickets and mixed ready/served work.
- RolePermissionSeeder added cancellation to waiter/captain-waiter, stock adjustment to manager/branch-manager and kitchen-manager, and menu viewing to kitchen staff. These are global role defaults and can affect web access when seeded.
- OrderDemoSeeder broadened two demo kitchen users' assignments to active kitchens in their branch.
- Shared models, events and channel authorization changed.

Six migrations were added:

1. 2026_11_11_090000_create_stock_transactions_and_item_cancellations_tables.php
2. 2026_11_11_100000_add_registered_display_credentials_to_kitchens.php
3. 2026_11_11_110000_add_stock_reversed_quantity_to_item_cancellations.php
4. 2026_11_11_120000_create_mobile_device_tokens_table.php
5. 2026_11_11_130000_add_wastage_quantity_to_item_cancellations.php
6. 2026_11_11_140000_add_cancellation_approval_setting_to_branches.php

The commit also contains legitimate mobile support: enhanced mobile order/kitchen APIs, push-token registration, FCM service/listener/configuration, mobile broadcast-token protection, live order/menu events and both Flutter applications.

**Assessment:** this commit combined mobile/API work with independent web development and shared business-rule changes. Reverting the whole commit would also remove mobile functionality and backend dependencies used by later work.

## 2d83cbe: shared cancellation and role changes

Full commit: 2d83cbe4d0b5b51dc4a3a8d3c661d891c139a09b.

Title: Update mobile order management and progress tracking.

Seven files changed:

- app/Http/Controllers/Api/MobileOrderController.php
- app/Http/Controllers/Api/OrderApiController.php
- app/Services/OrderService.php
- database/seeders/RolePermissionSeeder.php
- docs/mobile-app-plan.md
- mobile/README.md
- mobile/lib/features/orders/presentation/order_progress_screen.dart

No Blade views, browser assets or routes changed.

The shared cancellation policy changed from requiring both an approver permission and disabled branch approval to auto-approving whenever branch approval is disabled:

~~~php
// Before
$autoApprove = $requester->hasPermission('order.approve') && ! $approvalRequired;

// After
$autoApprove = ! $approvalRequired;
~~~

Both the mobile controller and the existing OrderApiController call this shared method.

RolePermissionSeeder also added order.view and order.approve to restaurant-manager. When seeded, these defaults can grant access to existing browser cancellation approval pages/actions.

The API edits in this commit changed response messages; they did not create new API endpoints.

**Assessment:** no web screen work, but more than mobile API creation. Shared cancellation behavior and global role defaults changed.

## 4f7b1dc: mobile workflows with shared order status changes

Full commit: 4f7b1dc60824e545492538b4b5ef8c72208ccd6b.

Title: Complete mobile order editing and kitchen workflows.

Sixteen files changed: ten mobile paths, two documentation files, two mobile API controllers, the shared OrderService and routes/api.php.

There were no changes to web views/assets, routes/web.php, database migrations, permissions or configuration.

Mobile/API additions included:

- A served-dish endpoint: POST /api/v1/mobile/orders/{order}/items/{item}/served.
- Mobile serving validation and can_mark_served response data.
- Active/completed/cancelled kitchen API views.
- Order editing, progress and kitchen workflow changes in both apps.
- Broadcasts when mobile completion changes an order.

The shared OrderService also changed:

- addItemsToOrder() now recalculates the order status from its kitchen tickets.
- syncOrderStatusFromTickets() now sets new or accepted when those stages are present.
- The existing web OrderController calls addItemsToOrder(); web KDS/KOT status operations also use the shared synchronization path.

**Assessment:** no direct web screen edits, but shared backend behavior used by the web application changed.

## 23f00bd and 32aa021: expansion followed by source rollback

Feature commit: 23f00bd4ad34373a87cda820f3a4b44a1c0345f8.

Rollback commit: 32aa02153d17a7788ba5ea428ac25c3094e28742.

The feature commit changed 84 paths: 21 mobile paths and 63 outside the mobile apps.

| Non-mobile area | Paths |
|---|---:|
| Laravel application code | 25 |
| Blade views | 14 |
| Browser JavaScript | 1 |
| Route files | 3 |
| Database migrations and seeders | 6 |
| Configuration | 1 |
| Documentation under docs/ | 10 |
| Root documentation/environment example | 3 |
| Total | 63 |

The changes included independent management reports/exports, branch settings, operational audit/status history, database backup tooling/scheduling, browser KDS changes and master/stock/staff/role changes. Shared routing, cancellation, numbering, locking and API payload behavior also changed.

Three migrations were added and one existing web-team migration was modified:

- 2026_11_13_100000_add_operational_sequences_settings_and_audit.php added document_sequences, settings, audit_logs, order_status_history, KOT numbering_period and a branch/period/number unique index.
- 2026_11_13_100100_add_reporting_and_settings_permissions.php added report/audit/settings permissions and role grants.
- 2026_11_13_100200_add_kitchen_type.php added guarded kitchen-type schema handling.
- 2026_11_13_030000_add_kitchen_type_to_kitchens_table.php was modified to coordinate duplicate kitchen-type creation/rollback.

32aa021 reverses exactly the 63 non-mobile paths. It does not edit either mobile application.

Verified source comparisons:

~~~text
git diff a4e1f61 HEAD -- . ':(exclude)mobile/**' ':(exclude)kitchen_mobile/**'
Result: empty

git diff a3524c9 a4e1f61 -- . ':(exclude)mobile/**' ':(exclude)kitchen_mobile/**'
Result: empty

git diff 32aa021^ 32aa021 -- mobile kitchen_mobile
Result: empty
~~~

Therefore the current non-mobile source matches the web team's version immediately before 23f00bd.

This is a source-code rollback. Earlier records show the additive migrations had been applied locally; the rollback did not execute their down methods or remove database data. This audit did not inspect the current live database, so it does not certify that those schema additions or permission rows are absent.

## Anees merge commits: imported work, not new web authorship

All five Anees merge commits have empty remerge differences: no additional resolution changes compared with Git's reconstruction.

| Merge | Imported history | Attribution |
|---|---|---|
| 9b41e17 | Main through f556e9a | Imported other developers' KDS, dashboard and master work. |
| 79fa1ab | Main through 1c877bd | Imported dashboard/stock changes. |
| fa0408b | Main through ea110c2 | Integration only; backend tree matches both parents. |
| f410588 | Main through 9aff628 | Imported stock/master changes; backend tree matches the main parent. |
| a4e1f61 | Main through a3524c9 | Imported the latest web-team changes; non-mobile tree matches the main parent. |

A first-parent diff for these merges can contain many web files. Those files must not automatically be counted as new Anees-authored web development.

Three merges elsewhere in the history contain resolution changes:

| Merge | Git author | Resolution files |
|---|---|---|
| 34165c5 | DivyaDharshini-14 | KotController.php |
| fbdac88 | DivyaDharshini-14 | MenuItemController.php, sidebar-nav.blade.php, routes/web.php |
| 5056e9b | Yashaswini B | dashboard.blade.php and alerts.blade.php |

The remaining 19 merge commits have empty remerge differences.

## Complete 39-commit inventory

Order follows Git history from oldest to newest. Author labels are shortened here: Anees = anees-anhet1; Divya = the DivyaDharshini-14 / Divyadharshini-14 Git labels; Yashaswini includes the Yashaswini and Yashaswini B Git labels. Attribution refers to recorded Git metadata.

| # | Commit | Date | Author | Scope / result |
|---:|---|---|---|---|
| 1 | fc64fd6 | 10 Nov | DishashettyA | Initial repository: web application, APIs and both mobile apps already present. Pre-import authorship cannot be established from this root commit alone. |
| 2 | bb55c87 | 11 Nov | Yashaswini | Browser KDS controller, views, navigation and web routes. |
| 3 | 2eb652b | 11 Nov | Divya | PR #1 merge of KDS work; no extra resolution delta. |
| 4 | 49f4eab | 11 Nov | Anees | Mobile/API work plus direct web features, shared services, schema and permissions. |
| 5 | 53f3016 | 11 Nov | Divya | PR #2 merge of 49f4eab; no extra resolution delta. |
| 6 | 080dcd6 | 11 Nov | Yashaswini | Browser KDS/KOT, timing migration, logo, shared order logic and web routes. |
| 7 | 34165c5 | 11 Nov | Divya | Main integration; KotController resolution. |
| 8 | c49396f | 11 Nov | Divya | PR #3 merge; no extra resolution delta. |
| 9 | 990292f | 11 Nov | Divya | Web dashboard/masters, nullable floor-code migration and web routes. |
| 10 | fbdac88 | 11 Nov | Divya | Main integration; menu controller, sidebar and web route resolutions. |
| 11 | f556e9a | 11 Nov | Divya | PR #4 merge; no extra resolution delta. |
| 12 | 2d83cbe | 11 Nov | Anees | Mobile progress/API messages, shared cancellation policy and restaurant-manager permissions. |
| 13 | 9b41e17 | 12 Nov | Anees | Imports main through f556e9a; no extra resolution delta. |
| 14 | 4f7b1dc | 12 Nov | Anees | Mobile editing/kitchen APIs and shared order status logic. |
| 15 | 95d00ae | 12 Nov | Divya | Web dashboard/stock, stock permission migration and shared order logic. |
| 16 | 1c877bd | 12 Nov | Divya | PR #5 merge; no extra resolution delta. |
| 17 | 79fa1ab | 12 Nov | Anees | Imports main through 1c877bd; no extra resolution delta. |
| 18 | ea110c2 | 12 Nov | Divya | PR #6 merge of mobile workflow work; no extra resolution delta. |
| 19 | fa0408b | 12 Nov | Anees | Main integration; backend matches both parents. |
| 20 | 2ebd9f8 | 13 Nov | Divya | Stock, company/user masters, mobile auth/kitchen APIs, shared order logic, GST/employee/KOT numbering schema. |
| 21 | 9aff628 | 13 Nov | Divya | PR #7 merge; no extra resolution delta. |
| 22 | f410588 | 13 Nov | Anees | Imports main through 9aff628; no extra resolution delta. |
| 23 | e04d3f6 | 13 Nov | Yashaswini | Existing web ReportController, report views, sidebar/dashboard and web routes. |
| 24 | 5056e9b | 13 Nov | Yashaswini | Main integration; dashboard/alerts resolutions. |
| 25 | 7bebeee | 13 Nov | Yashaswini | PR #8 merge; no extra resolution delta. |
| 26 | 3e2a531 | 13 Nov | Divya | Web kitchen/menu/user masters, kitchen-type migration, theme and numerous web views. |
| 27 | 48d76ba | 13 Nov | Divya | Main integration; no extra resolution delta. |
| 28 | 0849b48 | 13 Nov | Divya | PR #9 merge; no extra resolution delta. |
| 29 | 0e27eeb | 16 Nov | Yashaswini | Browser display screen, KdsController and Order model. |
| 30 | 42f5e4b | 16 Nov | Divya | PR #10 merge; no extra resolution delta. |
| 31 | 329c153 | 16 Nov | Divya | Dashboard/KOT monitoring views and shared order logic. |
| 32 | b11cd63 | 16 Nov | Divya | PR #11 merge; no extra resolution delta. |
| 33 | 9bd963b | 16 Nov | Divya | Role-based web menus/master actions and report access. |
| 34 | 5995af9 | 16 Nov | Divya | Application/database timezone configuration. |
| 35 | a3524c9 | 16 Nov | Divya | PR #12 merge; web baseline immediately before 23f00bd. |
| 36 | a4e1f61 | 16 Nov | Anees | Imports a3524c9; non-mobile tree matches main. |
| 37 | 23f00bd | 16 Nov | Anees | Mobile fixes plus extensive 63-path web/backend/documentation expansion. |
| 38 | 4ee2870 | 16 Nov | Divya | PR #13 merge of 23f00bd; no extra resolution delta. |
| 39 | 32aa021 | 16 Nov | Anees | Reverses all 63 non-mobile paths from 23f00bd; keeps both mobile apps. |

## What remains and the boundary for future work

The current source still contains older Anees contributions from 49f4eab, 2d83cbe and 4f7b1dc. Examples present in the current tree include:

- Branch cancellation approval configuration and the browser cancellation approval page/routes.
- Registered TV display credentials, registration controls and token-based browser routes.
- Shared cancellation auto-approval rules and restaurant-manager order permissions.
- Shared status synchronization after adding dishes and changing KOT stages.
- Mobile API, event, stock, cancellation and push infrastructure from those commits.

Later web-team changes modified some of these areas. Restoring a pre-Anees file wholesale would also discard their work. A future selective cleanup would need to separate direct web features from mobile API dependencies and preserve later web-team changes; no such additional cleanup was performed in this review.

The user-defined scope going forward is the waiter app in mobile/ and kitchen app in kitchen_mobile/, consuming existing backend APIs. New web screens, schema, shared business rules and global permission changes belong to backend/web ownership and require a separately agreed scope.

The earlier statement that the rollback was "local, uncommitted, and not pushed" described the previous turn's state. At this review's snapshot the rollback is committed as 32aa021 and the local origin/Anees-Dev tracking reference matches it.
