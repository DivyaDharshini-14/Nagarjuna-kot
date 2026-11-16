# Nagarjuna mobile application requirements audit

## Current remediation status

Updated 13 November 2026. The working tree now implements corrections for **G01 through G13**, plus the missing management reports/settings, critical-operation/status history, documentation and backup tooling identified in the original review. Billing System integration remains excluded. The original audit below is preserved as historical evidence at revision `f410588`; its original Missing/Partial rows and line numbers do not describe the revised working tree.

| Gap | Implemented correction | Main evidence |
|---|---|---|
| G01 | Full customer quantity routed to every active mapped station; parent item waits for all stations; stock and cancellation quantities accounted once. | [OrderService](../app/Services/OrderService.php) |
| G02 | Removed implicit first-kitchen fallback. Unmapped dishes are non-orderable in the mobile menu and rejected transactionally by submission. | [Menu API](../app/Http/Controllers/Api/MobileMenuController.php), OrderService |
| G03 | Fully cancelled station/customer lines remain terminal, active transitions skip cancelled quantities, and kitchen rendering defensively labels fully cancelled dishes. | OrderService, [kitchen board](../kitchen_mobile/lib/presentation/kitchen_board_screen.dart) |
| G04 | Shared transaction-level order/ticket locks, fresh-state checks and sequential transitions apply to mobile, TV and session endpoints. Closed orders and direct preparation cancellation are rejected. | OrderService, [legacy session API](../app/Http/Controllers/Api/OrderApiController.php) |
| G05 | Quantity additions save an immutable payload/key in secure storage per staff identity before sending. Startup/navigation reopens the affected order and reconciles the same request; other mutations wait. Uncertain HTTP/transport failures retain the key. | [Draft store](../mobile/lib/features/orders/data/pending_order_draft_store.dart), [order screen](../mobile/lib/features/orders/presentation/order_progress_screen.dart) |
| G06 | The quantity dialog explicitly distinguishes **Add extra** portions from **Reduce line**, explaining new submitted work rather than an apparent replacement total. | Order screen |
| G07 | Global order sequence uses a shared locked database counter, including legacy-number initialization and the existing unique-number backstop. | [DocumentNumbers](../app/Services/DocumentNumbers.php) |
| G08 | Kitchen cards display received clock time; a native Android notification tone supplies foreground incoming-KOT sound, controlled by operator preference and branch setting. | Kitchen board, [native sound channel](../kitchen_mobile/android/app/src/main/kotlin/com/nagarjuna/nagarjuna_kitchen/MainActivity.kt) |
| G09 | Floor polling/resume refresh preserves valid selection. Open cart reviews reconcile live menu/stock snapshots and require acknowledgment after changes; note editing preserves the latest line. | [Floor screen](../mobile/lib/features/tables/presentation/floor_table_screen.dart), [menu/review](../mobile/lib/features/menu/presentation/menu_composer_screen.dart) |
| G10 | View-only stock navigation is available to authorized staff; stock dishes/transactions and completed/cancelled KOT histories have real pagination and page controls. | [Kitchen API](../app/Http/Controllers/Api/MobileKitchenController.php), [stock screen](../kitchen_mobile/lib/presentation/kitchen_stock_screen.dart) |
| G11 | Both TV paths show remaining/cancelled portions, stage sections and paginated cancellation/completed history; friendly TV also has private WebSockets, reconnect and catch-up. | [KDS controller](../app/Http/Controllers/KdsController.php), [registered display](../app/Http/Controllers/KotController.php), [live client](../public/js/kds-screen.js) |
| G12 | Stock-adjustment permission, tenant/active mapping/station scope, required reasons, locked ledger writes and menu broadcasts protect every TV availability mutation. Legacy KOT endpoints and empty-assignment dashboard/stock cases were also corrected. | KDS controller, [scope helper](../app/Http/Controllers/Concerns/ScopesKitchenOperations.php), session API |
| G13 | Create and add-items share a branch/period KOT counter. A database unique index protects new branch/period/number combinations while legacy numbers remain unchanged. | DocumentNumbers, [schema migration](../database/migrations/2026_11_13_100000_add_operational_sequences_settings_and_audit.php) |

Wider documented gaps are implemented through [scoped reports and exports](../app/Http/Controllers/ReportsController.php), [branch settings](../app/Http/Controllers/SettingsController.php), [audit/status capture](../app/Observers/OperationalAuditObserver.php), a private checksummed [backup command](../app/Console/Commands/BackupDatabase.php), and [API](API.md), [database](DATABASE.md) and [operator/deployment/recovery documentation](OPERATIONS.md). Kitchen classification is now stored and editable. Web dish edits retain a stock snapshot to avoid overwriting a concurrent order deduction; low-stock classifications use configured branch thresholds.

Both Dart analyzers pass with no diagnostics, and both Android debug builds pass, including the new native kitchen sound code. PHP syntax validation checked 130 files with zero failures; Blade compilation, JavaScript syntax, documentation links and route registration pass. No automated tests were written or run under the repository's manual-testing guidance.

A private local MySQL backup and checksum were verified before applying the pending additive migrations; all remediation migrations have been applied to the local database. Other deployments must migrate with the code. Existing restaurant orders/stock were not reset and legacy numbers were not rewritten.

**Acceptance still required:** the manual staging scenarios in [OPERATIONS.md](OPERATIONS.md), including multi-station work, concurrent writes, lost-response recovery, target-device notifications, TV reconnect, report reconciliation, restore exercise and signed production installation. Production domain/TLS, managed workers/Reverb, FCM server credentials, valid release signing and off-host backup operation are environment requirements. These are not represented as passed by source checks or debug builds.

## Original audit at revision f410588

Review date: 13 November 2026  
Repository revision reviewed: `f410588` following recheck of changes from initial snapshot `fa0408b`  
Applications: `mobile/` waiter and captain app; `kitchen_mobile/` kitchen staff app  
Requirements source: `C:\Users\shaik\Downloads\NAGARJUNA – RESTAURANT KOT MANAGEMENT SYSTEM.docx`

The two applications implement most of the documented daily restaurant workflows, but they do **not yet match every applicable requirement**. The largest functional gap is that a dish configured for more than one kitchen reaches only the first kitchen. Cancellation status handling and uncertain quantity-increase recovery also need correction. Live delivery, stock integrity under concurrency, production configuration and the document's acceptance scenarios still require staging and device verification.

This is a requirements and source-code audit, not a production acceptance certificate. No application code was changed by this audit. Billing software integration is excluded throughout, including its endpoints, provider coordination, synchronization, integration logs and billing-specific acceptance scenario. Reference menu prices remain in scope because section 5.3 explicitly requires them.

## Reading the results

| Result | Meaning |
|---|---|
| Implemented | The relevant UI, API or service behavior is present in source. This does not mean device UAT passed. |
| Partial | The implementation has an identified behavioral gap or covers only part of the requirement. |
| Missing | The reviewed source does not provide the specified capability. |
| Verify | The result depends on running services, deployment, devices, performance or acceptance evidence unavailable to a source audit. |
| Web owned | The document assigns this capability to the web administration application. It is not a required screen in either mobile app. |
| Excluded | Billing integration or a feature expressly outside phase 1. |

Evidence links point to repository files. Line numbers in the text refer to the reviewed revision. The repository received an external merge during this audit; the changed authentication payloads, stock controller, master fields, migrations and KOT numbering were rechecked before completion. All 17 numbered specification sections were read, including the tables. Repository plans were treated as implementation claims and context, not as proof that requirements passed acceptance. Instructions inside the supplied specification were treated as project requirements, not as instructions to operate external systems.

## Findings that prevent a complete match

### G01 A dish assigned to several kitchens reaches only one kitchen

**Priority: high. Applies to specification 3.8, 5.5, 6.1 and 17.**

[OrderService.php](../app/Services/OrderService.php), lines 412-421, retrieves the dish mapping with `first()`. It adds that order line to only the selected kitchen at line 441. The schema allows several kitchen mappings for the same dish, but the routing service does not process all of them.

Example: configure one dish for both Main Kitchen and Starters Kitchen, then submit it from the waiter app. Only one mapped station receives a KOT. This is distinct from an order containing three different dishes, each with one kitchen; that simpler split is implemented.

Implement the intended multi-station preparation model, including how the final dish status is aggregated and how cancellation reaches every station while stock is deducted or reversed only once. Changing `first()` to `get()` alone would be insufficient: cancellation currently allocates a quantity across KOT lines, and status updates currently overwrite the parent item directly.

### G02 Missing mappings silently send work to an unrelated station

**Priority: high. Applies to 3.8 and 6.1.**

[OrderService.php](../app/Services/OrderService.php), lines 425-432, falls back to the first active kitchen in the branch when no active mapping exists. The waiter menu does not exclude unmapped dishes. A missing or disabled assignment can therefore send a dish to a kitchen that was never assigned to prepare it.

Require a valid mapping before accepting the dish, or implement an explicitly configured fallback policy. The current implicit first-kitchen fallback conflicts with the requirement that each kitchen receives its assigned dishes.

### G03 Cancelled dishes can display an active preparation status

**Priority: high. Applies to 5.7, 6.3 and 8.**

[OrderService.php](../app/Services/OrderService.php), line 888, increments a KOT line's cancelled quantity without setting a fully cancelled KOT line's `item_status` to `cancelled`. Lines 902-903 set only the parent order line's status. Later, a normal ticket transition updates **every** KOT line at line 513 and every linked order line at lines 516-517, including fully cancelled lines.

Example: a ticket contains dish A and dish B. Cancel all of dish A, leaving B active, and accept or start the ticket. A can be returned as Accepted or Preparing despite having zero remaining quantity. The kitchen UI reads the supplied status directly at [kitchen_board_screen.dart](../kitchen_mobile/lib/presentation/kitchen_board_screen.dart), lines 842-856, and can show an active status beside `0×` and a cancelled quantity. The waiter UI also trusts the parent line status.

Keep fully cancelled lines terminal, update only active quantities, and derive consistent line and ticket states. Preserve the cancellation history and the existing preparation-aware stock policy.

### G04 Ticket transition validation is vulnerable to stale concurrent requests

**Priority: high. Source-level concurrency risk; not reproduced against a running database. Applies to 6.3, 7.7, 8 and 13.**

[MobileKitchenController.php](../app/Http/Controllers/Api/MobileKitchenController.php), lines 206-222, checks allowed transitions on the route-bound ticket before calling the service. [OrderService.php](../app/Services/OrderService.php), lines 493-530, opens a transaction but does not lock and reload that ticket or revalidate its current status before updating it.

A request validated while the ticket is New can wait while another request cancels or advances it, then overwrite the newer state with Accepted. The registered TV endpoint has the same validation-before-service pattern. Several web KDS mutation endpoints accept broader status changes without the mobile transition guard.

Lock and reload the ticket inside the transaction, validate the transition there, reject closed parent orders, and use the same rules across mobile and TV endpoints. Include a concurrent cancellation-versus-preparation check in acceptance.

### G05 Quantity increases do not retain recovery identity after leaving the screen

**Priority: medium. Applies to 5.6, 7.7 and 13.**

The main order composer correctly persists uncertain submissions and their request key in secure storage. The separate quantity-adjustment path does not: [order_progress_screen.dart](../mobile/lib/features/orders/presentation/order_progress_screen.dart), line 58, stores pending increase keys only in widget memory; lines 365-379 create, use and remove those keys.

If the server commits an increase but the response is lost, the same screen can retry with the same key. Leaving that screen or terminating the app loses the key. Repeating the intended adjustment can then create another line, KOT and legitimate second stock deduction under a new key. The API's same-key deduplication does not solve the lost client operation identity.

Persist the pending quantity operation and payload before sending it, and reconcile it after navigation or restart using the original key.

### G06 The quantity editor's target can be mistaken for the dish's new total

**Priority: medium. UI correctness issue in an additional implemented workflow.**

[order_progress_screen.dart](../mobile/lib/features/orders/presentation/order_progress_screen.dart), lines 328-348, calculates the selected line's active quantity. The dialog says `New active quantity` at line 1465. However, [mobile_order_repository.dart](../mobile/lib/features/orders/data/mobile_order_repository.dart), lines 133-145, submits the delta through the add-items endpoint, which creates a separate line.

For an original line of 2, choosing 3 creates lines of 2 and 1. Reopening the original line still starts from 2; choosing 3 again adds another portion. Keeping previous KOTs unchanged is correct and the dialog explains new kitchen work, but its target terminology is misleading. Use an explicit Add quantity action or show an aggregate dish total while preserving individual submitted batches.

### G07 Global order numbering is protected only by a branch lock

**Priority: medium. Source-level concurrency risk; not reproduced against MySQL. Applies to 5.5 and 13.**

[OrderService.php](../app/Services/OrderService.php), line 67, locks only the current branch, while `generateOrderNumber()` at lines 1088-1115 searches the global order sequence and does a separate existence check. [The orders migration](../database/migrations/2026_11_06_150001_create_orders_table.php), line 13, makes that number globally unique.

Two simultaneous orders in different branches with different stock rows can calculate the same next number. The unique index prevents duplicate stored numbers, but one order can fail instead of both succeeding; this is not proof of duplicated stock. Use a global atomic sequence, a unique branch-prefixed sequence or a bounded collision retry that preserves idempotency.

### G08 Kitchen mobile omits received clock time and foreground sound

**Priority: medium for kitchen-mobile parity. Applies to the kitchen workflow in 6.2 and 6.4.**

The kitchen card displays elapsed duration from `createdAt` at [kitchen_board_screen.dart](../kitchen_mobile/lib/presentation/kitchen_board_screen.dart), line 612, but does not display the received clock time required alongside waiting duration. The model already retains the timestamp.

Foreground new-KOT and cancellation alerts use SnackBars in [main.dart](../kitchen_mobile/lib/main.dart), lines 426-427. Android background notification channels and Firebase hooks exist, but no foreground audio action was found. Add a visible received time and an operator-controlled foreground chime if the kitchen app is expected to carry the KDS sound requirement. The registered browser KDS already has received time and an Enable sound control, so these are not blanket claims that the entire system lacks those features.

### G09 Open review and floor screens can show stale availability

**Priority: low. Applies to 5.2 and 7.6.**

The waiter floor screen loads on entry and manual refresh; it has no timer or table-event subscription. Another waiter's table change can remain invisible until refresh. The server still locks and validates the table, so this does not establish duplicate table orders.

The menu list receives availability events and refreshes every 15 seconds, but the open cart review sheet copies its draft in `initState` at [menu_composer_screen.dart](../mobile/lib/features/menu/presentation/menu_composer_screen.dart), lines 1262-1268. It does not consume subsequent stock changes while open. The server still rejects insufficient stock and returns remaining quantity. Refresh on resume and keep the open review consistent with updated menu availability.

### G10 Kitchen history access is narrower than the complete stored record

**Priority: low. Limitation, not proof of lost history.**

[MobileKitchenController.php](../app/Http/Controllers/Api/MobileKitchenController.php), line 313, returns only the latest 50 stock transactions without pagination. Completed and cancelled ticket views each return at most 100 records at lines 178 and 184. The database retains historical records and the web stock history is paginated.

The kitchen stock screen supports a read-only mode, and the stock GET endpoint supports authorized kitchen staff, but [main.dart](../kitchen_mobile/lib/main.dart), line 548, exposes the navigation action only when `canManageStock` is true. Ordinary kitchen staff therefore cannot reach the documented read-only stock/history experience. Mutations remain correctly permission controlled. Add read-only navigation if that documented staff capability is intended; add pagination if complete historical browsing is required on the phone.

## Browser KDS dependencies affecting the mobile workflow

The specification requires a browser KDS in addition to the waiter app. The kitchen mobile app supplements it. Two browser implementations are routed in the current repository and provide different behavior; they should not be treated as interchangeable evidence of compliance.

| Area | Registered display `/kds/{displayToken}` | Friendly display `/{branchCode}/{kitchenCode}` |
|---|---|---|
| Main implementation | `KotController` and `kots/display.blade.php` | `KdsController` and `kds/screen.blade.php` |
| Kitchen binding | Hashed display token binds one active kitchen; rotate/revoke exists. | Signed-in user and branch/kitchen lookup; checks company/branch, but does not enforce explicit staff kitchen assignment. |
| Live delivery | Authenticated WebSocket, reconnect and disconnected polling fallback. | Feed polling every 3.5 seconds at `screen.blade.php:1009`; no WebSocket client found in this view. |
| New/preparing/ready separation | Counts, status colors and sorted cards; one mixed grid, not separate stage sections. | Three distinct stage sections. |
| Partial cancellation quantities | Shows remaining and cancelled quantities. | Feed supplies original quantity without `cancelled_quantity` (`KdsController:210-216`); view renders original quantity (`screen.blade.php:894-897`). |
| Fully cancelled tickets | Initial active-only condition at `KotController:136` excludes cancelled tickets even though a later nested condition tries to include recent cancellations. | Feed explicitly excludes cancelled tickets at `KdsController:147`; no cancelled-item history view. |
| Received time and sound | Both present; sound requires operator enabling. | Received time and new-order chime present. |

**G11 Cancellation visibility is incomplete on both browser paths. Priority: high for the complete documented workflow.** Full cancellation can disappear from the registered display because of the contradictory query filters. The friendly display can continue showing an original partially cancelled quantity. This can cause a kitchen to prepare cancelled portions even when the mobile app records the correct cancellation. Consolidate the paths or bring both to the same quantity, cancellation, security and real-time contract.

**G12 The friendly KDS availability endpoint bypasses tenant and stock authorization. Priority: high.** [routes/web.php](../routes/web.php), line 171, exposes `toggleDishAvailabilityDirect()` under `kot.edit`. [KdsController.php](../app/Http/Controllers/KdsController.php), lines 366-374, updates the route-bound menu item without authorizing its company, branch, kitchen mapping or `menu-item.stock-adjustment` permission. An authenticated web user with that route permission can address another menu item ID. This is a web endpoint defect, not a demonstrated bypass in the correctly scoped mobile API.

Both friendly KDS availability mutation methods also omit a stock ledger record and `MenuAvailabilityChanged` event, unlike the kitchen-mobile stock APIs. A TV availability change therefore does not immediately broadcast to the waiter menu and does not receive the same audit trail. Reuse the guarded availability workflow for all clients.

## Additional shared backend finding

### G13 The updated KOT sequence can collide between concurrent additions

**Priority: high. Source-level concurrency risk in the merge reviewed during this audit; not reproduced against a live database. Applies to KOT identification and reliable order submission.**

The current [OrderService.php](../app/Services/OrderService.php), lines 1118 onward, assigns a branch-wide daily `KOT-N` number by scanning existing tickets. Initial order creation locks its branch, but `addItemsToOrder()` at lines 140-154 locks only its own order and table. Ticket generation locks the selected kitchen rows at lines 444-449.

Two additions to different orders, using different dishes and different kitchens in the same branch, can therefore calculate the same next KOT number without a common lock. [The new KOT index migration](../database/migrations/2026_11_13_020000_remove_unique_from_kot_number_in_kot_tickets_table.php) removes the unique number constraint and adds an ordinary index. Once that migration is applied, the target schema has no uniqueness backstop for the intended branch/day sequence. This concerns duplicate displayed numbers for distinct tickets, not proof that one order request created duplicate ticket rows.

Serialize the sequence using a branch/day counter shared by creation and additions, and enforce its intended uniqueness in the database. Verify concurrent additions across different kitchens and daily sequence reset. Deployment migration status was not inspected.

## Waiter application requirement matrix

All Implemented rows remain subject to the acceptance scenarios later in this report.

| ID | Specification requirement | Result | Evidence and limits |
|---|---|---|---|
| W01 | 2.B and 5.1 Login with registered credentials | Implemented | `sanctum_auth_repository.dart:14`, sign-in validation and password masking. Backend rejects inactive/ineligible users. |
| W02 | 4 and 5.1 Company, branch and role access | Implemented | `MobileAuthController:89`, tenant-scoped floor/menu/order APIs; UI permissions from `mobile/main.dart:241-263`. |
| W03 | 5.2 Floor layout or table list | Implemented | `floor_table_screen.dart:209` and floor selector at 460. A table list is expressly allowed; a graphical floor plan is not mandatory. |
| W04 | 5.2 Show current table status | Implemented; freshness caveat | Available/Occupied/Reserved/Cleaning rendered; entry/manual refresh only. The source does not explicitly demand a table-event subscription. G09. |
| W05 | 5.2 Select available table and open order | Implemented | Table `canOpen` and composer flow; `OrderService:41-56` locks/revalidates availability. Occupied tables can resume an existing order. |
| W06 | 3.5 Optional Reserved and Cleaning states | Implemented | `floor_table.dart:1-10`; unavailable states cannot start an order. |
| W07 | 5.3 Category-wise menu | Implemented | `menu_repository.dart:23`; composer category groups. |
| W08 | 5.3 Search dishes | Implemented | `menu_composer_screen.dart:182-190`; name, code and description. |
| W09 | 5.3 Category filter | Implemented | Composer category chips and filter at 176-180. |
| W10 | 5.3 Dish images | Implemented | Network image and fallback at composer 970; API resolves stored image path. Actual image hosting: Verify. |
| W11 | 5.3 Selling price | Implemented | Dish card price at composer 913; reference cart subtotal. Billing integration remains excluded. |
| W12 | 5.3 Available stock | Implemented | API stock/configuration/orderability; composer stock label at 1091. |
| W13 | 5.3 Disable sold-out dishes | Implemented | Composer 200-205 and 882; server repeats availability/stock validation. |
| W14 | 5.3 Veg and Non-Veg indicators | Implemented | Composer food-type label at 1022; Egg indication also supported. |
| W15 | 5.4 Select dishes | Implemented | Composer add/remove cart actions at 200-218. |
| W16 | 5.4 Enter quantities | Implemented | Quantity steppers and stock caps. Current maximum is 99 per cart line; source sets no required maximum. |
| W17 | 5.4 Special instructions for order | Implemented | Cart review field at composer 1399; serialized to `special_instructions`. |
| W18 | 5.4 Item-level notes | Implemented | Cart line note editor; API serializes notes for each item. |
| W19 | 5.4 Review complete order | Implemented | Cart review list and reference subtotal; additions review the new batch and existing lines remain in progress view. Stale open-sheet caveat G09. |
| W20 | 5.4 Submit order | Implemented | Repository POST to `mobile/orders`; loading/error/confirmation states. |
| W21 | 5.5 Return unique order number | Implemented; reliability risk | Confirmation displays server order number and KOTs; unique index prevents duplicate stored order numbers. Concurrent requests can fail under G07. |
| W22 | 5.5 Save main order and individual items | Implemented | `OrderService:31-127`, database transaction. |
| W23 | 5.5 Identify assigned kitchen and split tickets | Partial | Kitchen grouping exists; multiple mappings ignored and unmapped fallback. G01/G02. |
| W24 | 5.5 Deduct stock after successful submission | Implemented | Stock validation/deduction and ledger participate in order transaction. Runtime rollback/concurrency: Verify. |
| W25 | 5.5 Deliver KOT to kitchen in real time | Implemented / Verify | After-commit broadcast events; both apps have private WebSocket clients. Deployed Reverb and queue delivery not proven here. |
| W26 | 5.5 Show confirmation and multiple KOT numbers | Implemented; number collision risk | `mobile_order_repository.dart:351-386`; composer confirmation at 499. Updated branch/day KOT sequence risk G13. |
| W27 | 5.6 Add items to existing table order | Implemented | Existing-order composer and POST `mobile/orders/{id}/items`. |
| W28 | 5.6 Additional KOTs without replacing previous work | Implemented | `OrderService:133-216` creates only newly submitted lines/tickets. Quantity-editor recovery remains Partial under G05. |
| W29 | 5.6 Deduct stock for additions | Implemented | `OrderService:161-162`; same validation/ledger transaction as initial order. |
| W30 | 5.7 Show New, Accepted, Preparing, Ready, Served and Cancelled per item | Partial | All six labels exist; cancellation status corruption G03 prevents reliable semantics. |
| W31 | 5.7 Real-time Ready status | Implemented / Verify | Order channel subscription; event refresh, 12-second polling and app-resume fallback. Live timing needs UAT. |
| W32 | 2.B View kitchen-ready notifications | Implemented / Verify | Firebase initialization, foreground notice, background notification and order-opening handlers; server queue/FCM configuration required. |
| W33 | 7.3 Warn about insufficient stock and show latest quantity | Implemented | Service error includes current remaining portions at 346-348; app shows error and allows editing/refresh. |
| W34 | 7.3 Prevent unavailable quantities and allow modification | Implemented | Local quantity cap plus authoritative server validation. |
| W35 | 7.6 Immediately reflect availability changes | Partial | Branch menu event refresh and 15-second fallback exist; open review sheet stays stale; friendly TV changes omit event. G09/G12. |
| W36 | 7.7 and 13 Duplicate prevention after retries | Partial | Normal composer persists draft/key; server has unique request ledger; quantity-edit recovery loses key after screen disposal. G05. |
| W37 | 8 Cancel individual dishes or quantities | Implemented | Reason/quantity dialog and cancellation API; downstream status issue G03. |
| W38 | 8 Cancel complete order | Implemented | `cancel_all` request and preparation-aware service workflow. |
| W39 | 8 Mandatory cancellation reason | Implemented | UI and API validation, trimmed reason retained in records. |
| W40 | 8 Authorized cancellation approval | Implemented | Mobile approval queue, accept/reject actions and server permission check; separate approver enforced when branch setting requires it. |
| W41 | 8 Cancellation history | Implemented | Shows requester, decision-maker, reason, timestamps, decision note, restored quantity and wastage. |
| W42 | 13 Mobile-friendly interaction | Implemented / Verify | Scrollable/responsive layouts and keyboard handling present; target sizes, accessibility and font scaling require device checks. |
| W43 | 13 Secure session and transport | Implemented / Verify | Secure token storage, seven-day server tokens, revocation and release HTTPS/WSS validation. Actual production certificates/configuration unverified. |

Additional implemented waiter capabilities include pre-preparation note editing, explicit Served actions, order completion/table release and mobile manager approvals. They are useful extensions; their presence does not replace the mandatory requirements above.

## Kitchen mobile application requirement matrix

The document describes these workflows for a browser KDS. They are applied here to assess kitchen-mobile parity. Browser-specific URL and display-registration requirements remain separate obligations.

| ID | Specification requirement | Result | Evidence and limits |
|---|---|---|---|
| K01 | 4 Authorized kitchen staff login | Implemented | Kitchen-only Sanctum token and account/role/permission checks in `MobileKitchenController:23-53,410-417`. |
| K02 | 4 and 6.1 Only assigned kitchens and dishes | Implemented for API access; routing Partial | Tenant and explicit station filters at controller 123-130,150-155,399-405. Which station receives work is affected by G01/G02. |
| K03 | 6.2 KOT number | Implemented | Ticket model and board card. |
| K04 | 6.2 Order number | Implemented | Ticket/order context on card. |
| K05 | 6.2 Table number | Implemented | Order table context on card. |
| K06 | 6.2 Dish name | Implemented | `_DishRow` in board. |
| K07 | 6.2 Quantity | Implemented | Remaining quantity and cancelled quantity are displayed separately. |
| K08 | 6.2 Special instructions | Implemented | Order and dish notes parsed and displayed. |
| K09 | 6.2 Order received clock time | Missing in kitchen mobile | Elapsed age shown; received timestamp not displayed. G08. Browser displays have clock time. |
| K10 | 6.2 Waiting duration | Implemented | Card elapsed duration from ticket creation, refreshed UI. |
| K11 | 6.2 Current status | Partial | Status pills and colors exist; fully cancelled lines can show active status. G03. |
| K12 | 6.3 Accept order | Implemented; concurrency Partial | New to Accepted action and server guard exist; G04. |
| K13 | 6.3 Start preparing | Implemented; concurrency Partial | Accepted to Preparing action and guard; G04. |
| K14 | 6.3 Mark ready | Implemented; concurrency Partial | Preparing to Ready action, after-commit notification; G04. |
| K15 | 6.3 View completed orders | Implemented | Completed filter includes served tickets or completed parent orders; latest 100. |
| K16 | 6.3 View cancelled items | Implemented with status caveat | Cancelled filter includes tickets with cancelled quantities; latest 100; G03. |
| K17 | 6.4 Separate New, Preparing and Ready views | Implemented | Stage filter chips, plus Accepted, Completed and Cancelled filters. |
| K18 | 6.4 Clear visual status indicators and new work | Implemented | Colored pills, counters, queue cards and new-ticket in-app alert. |
| K19 | 6.4 Sound on incoming KOT | Partial for kitchen-mobile parity | Background notification channel exists; no foreground chime. G08. Registered TV sound exists. |
| K20 | 6.5 WebSocket-based live updates | Implemented / Verify | `kitchen_live_connection.dart` authenticates private assigned-kitchen channels. Running service and latency require UAT. |
| K21 | 6.5 No manual refresh required | Implemented | Event-triggered fetch plus 12-second polling fallback in `kitchen_mobile/main.dart:262-265`. |
| K22 | 6.5 Connection warning and automatic reconnect | Implemented / Verify | Connection state, backoff and refresh on resubscribe; interruption/resume acceptance pending. |
| K23 | 6.5 Retrieve pending orders after reconnect | Implemented / Verify | Main connection callback forces refresh at 300-303. |
| K24 | 6.6 Branch and kitchen display registration | Web owned / Implemented | Staff app uses login and explicit assignments; browser token registration exists separately. |
| K25 | 7.1 Enter prepared-dish stock | Implemented | Permission-controlled stock-in; first positive initialization records Opening Stock. Tracking must be enabled in web menu management. |
| K26 | 7.4 Add and reduce stock | Implemented | Stock-in and directional manual adjustment; server lock and nonnegative check. |
| K27 | 7.4 Record wastage | Implemented | Wastage adjustment and cancellation wastage records. |
| K28 | 7.4 Record damaged or spoiled dishes | Implemented | Damaged adjustment with mandatory reason. |
| K29 | 7.4 Correct physical differences | Implemented | Manual increase/decrease with reason. |
| K30 | 7.4 Record user, date, time, quantity and reason | Implemented | Stock ledger stores user/timestamp/balance/delta/reason; history displays these. |
| K31 | 7.5 Complete stock history | Implemented persistence; limited mobile browsing | Latest 50 without paging; ordinary staff cannot reach read-only route. G10. |
| K32 | 7.6 Available, Temporarily Unavailable and Sold Out | Implemented | Availability selector and guarded endpoint; branch-menu broadcast for mobile changes. |
| K33 | 8 Notify kitchen of cancellation | Implemented / Verify | Cancellation event, changed quantities, in-app alert and push hooks; live delivery needs UAT. |
| K34 | 13 Secure session/transport | Implemented / Verify | Secure token storage, kitchen token ability, server tenant/station scope and release HTTPS/WSS guard. |

## Shared stock and cancellation rules

| ID | Specification requirement | Result | Backend evidence and qualification |
|---|---|---|---|
| S01 | 7 Prepared dishes only; no raw inventory | Implemented scope | Inventory operates on menu dishes and prepared portions. Raw/ingredient inventory is excluded. |
| S02 | 7.1 Opening stock and stock additions | Implemented | Mobile stock initialization and web opening-stock/stock-in flows; ledger transaction types. |
| S03 | 7.2 Deduct ordered quantity after successful submit | Implemented | `OrderService:290-356` consumes tracked quantity inside order transaction. |
| S04 | 7.3 Validate before accepting an order | Implemented | Dish activity, category/type activity, branch, availability and quantity revalidated server-side. |
| S05 | 7.3 Insufficient stock warning and current remaining amount | Implemented | `OrderService:341-348`; client displays validation response. |
| S06 | 7.3 Negative stock prohibited | Implemented | Orders compare requested quantity; kitchen adjustment aborts if after-balance is negative at controller 274. Real concurrency: Verify. |
| S07 | 7.4 Only authorized kitchen staff adjust stock | Implemented in mobile API | Stock-adjustment permission plus explicit mapping and tenant authorization. Friendly TV endpoint exception G12. |
| S08 | 7.4 All adjustment reasons and actors retained | Implemented in stock APIs | Mandatory reasons and ledger metadata. Alternate TV availability path lacks equivalent logging. |
| S09 | 7.5 Opening Stock history | Implemented | `opening_stock` records for initialized quantity. |
| S10 | 7.5 Stock In history | Implemented | `stock_in` records. |
| S11 | 7.5 Order Deduction history | Implemented | `OrderService:360-394`, `order_deduction`. |
| S12 | 7.5 Cancellation Reversal history | Implemented | Service 915-928, linked cancellation record and reversal quantity. |
| S13 | 7.5 Wastage history | Implemented | Adjustment and preparation-started cancellation history; cancellation wastage has zero additional delta because stock was already consumed. |
| S14 | 7.5 Manual Adjustment history | Implemented | Signed delta, before/after balances and reason. |
| S15 | 7.6 Availability immediately reaches waiter | Partial | Correct mobile stock events; open review and alternate TV mutation exceptions G09/G12. |
| S16 | 7.7 Deduct only once per successful submission | Implemented server contract; client recovery Partial | `MobileOrderController:347-413` atomically records unique user/key and payload hash. G05. |
| S17 | 7.7 Repeated same-key requests do not double-deduct | Implemented / Verify | Completed request replays order; changed payload with reused key rejected. Actual timeout/concurrent retry UAT absent. |
| S18 | 7.7 Cancellation reversal based on preparation policy | Implemented | `OrderService:880-896,910-956`; only New/Accepted quantities qualify for restoration. |
| S19 | 7.7 Prepared/dispatched food not automatically restored | Implemented | Preparing/Ready/Served quantities recorded as wastage without stock return. Transition consistency risk G03/G04 must be resolved. |
| S20 | 7.7 All stock movements recorded | Implemented main mobile paths; system Partial | Order/stock/cancellation ledgers exist. Alternate web mutation consistency must be addressed, including G12. |
| S21 | 7.7 Atomic stock and order creation | Implemented / Verify | Transaction and row locks; rollback/concurrency acceptance still required. |
| C01 | 8 Controlled individual cancellation | Implemented | Per-line quantity bounds exclude previously cancelled and pending quantities. |
| C02 | 8 Controlled complete-order cancellation | Implemented | Service computes remaining cancellable quantities. |
| C03 | 8 Mandatory reason | Implemented | API validation and persistent cancellation records. |
| C04 | 8 Approval permission | Implemented | Approval endpoints require `order.approve`; branch policy controls immediate versus pending decision. |
| C05 | 8 Separate approver when policy requires | Implemented | Service 818-824 rejects requester self-approval. |
| C06 | 8 Send cancellation to relevant kitchen | Partial overall | Correct after-commit event and mobile quantity refresh; browser visibility defects G11. |
| C07 | 8 Maintain cancellation history | Implemented | Quantity, requester, decider, timestamps, decision, reason, restored and wastage quantities retained. |
| C08 | 8 Restore stock only when policy permits | Implemented; transition risk | Preparation-aware policy exists; stale status transitions can undermine state consistency. G04. |
| C09 | 8 Prepared cancelled food recorded as wastage | Implemented | Linked wastage entry and cancellation quantities. |

## Coverage of every specification section

This table accounts for the complete document without requiring administration screens inside phone applications. Web master presence is recorded as a dependency, not certified as a complete field-by-field web audit.

| Source section | Ownership and assessment |
|---|---|
| 1 Project overview | Waiter ordering, kitchen-wise KOTs, multi-tenant configuration and prepared stock exist. Routing/cancellation gaps above prevent complete conformity. Billing exchange excluded. |
| 2 Application components | Admin web app, waiter app and browser KDS exist. Kitchen mobile is an added companion, documented in `kitchen-mobile-app-plan.md:5`; it does not replace browser KDS. |
| 3.1 Company master | Web owned. Company identity, code, address/contact/email/GST/status belong to administration; the apps consume company identity/scope. Company controller, model and migration exist. |
| 3.2 Branch master | Web owned. Branch code/company/address/contact/status and one-to-many organization are backend dependencies; apps use assigned branch. |
| 3.3 Kitchen master | Web owned. Kitchen identity/type/branch/display identifier/status and several kitchens per branch are configured in web. Kitchen app consumes assigned stations. |
| 3.4 Floor master | Web owned. Name/branch/description/status configured in web; active assigned-branch floors consumed by waiter app. |
| 3.5 Table master | Web owned CRUD; phone selection/status assessed W03-W06. Capacity and active-state guards exist. Reserved/Cleaning are optional in the source. |
| 3.6 Category master | Web owned CRUD/code/description/order/status; waiter consumes active category groups. |
| 3.7 Menu type master | Web owned CRUD/name/description/status; waiter displays configured dietary type. |
| 3.8 Menu/dish master | Web owns identity/code/category/type/applicability/price/image/description/preparation defaults/display order/tracking/status and mapping. Mobile display is covered W07-W18. Multi-kitchen preparation support is Partial, G01. |
| 4 User roles and permissions | Mobile auth, permission gating and server tenant/assigned-station enforcement present. The reviewed merge adds employee-code/designation storage and authentication payload fields; these are web-managed data, not required phone editing screens. Kitchen web endpoints have assignment/tenant exceptions described in G12/KDS comparison. |
| 5 Mobile order management | Detailed waiter matrix W01-W43. Most functionality exists; affected requirements are Partial. |
| 6 KOT and KDS | Kitchen mobile matrix K01-K24 plus mandatory browser comparison. Multiple-mapping, cancellation, clock-time/sound parity and browser path differences remain. |
| 7 Prepared dish stock | Detailed S01-S21 and kitchen stock rows K25-K32. Main transactional policy is implemented; live consistency/retry/history caveats remain. |
| 8 Cancellation and modification | Detailed C01-C09, G03/G04/G11. Approval, reasons, history and preparation-aware stock/wastage are present. |
| 9 Billing software integration | Excluded entirely at the user's request. No billing implementation requirement is counted as a failure. |
| 10 Reports and dashboard | Web owned. Dashboard counters, branch and kitchen summaries exist. Orders/KOT/stock/cancellation lists provide some reporting, but the complete named report suite is not established. See system obligations below. |
| 11 Settings | Web/system owned. Branch cancellation approval, display registration and environment-based services exist. General settings and configurable numbering/notification/stock settings are incomplete as a management module. |
| 12 Database design | Relational migrations/FKs/indexes/timestamps and scoped parents exist. Suggested table names are not mandatory: stock on menu items and display credentials on kitchens can implement equivalent behavior. Complete status/audit history and schema documentation remain incomplete. |
| 13 Non-functional requirements | Security and recovery mechanisms exist; remaining concurrency, audit, performance, backup and release verification listed below. |
| 14 Development phases | Planning sequence, not evidence of completed acceptance. Phase 7 billing excluded; final UAT/deployment phase unverified. |
| 15 Testing and acceptance | All applicable source scenarios mapped below; no completed cross-app acceptance run is claimed. |
| 16 Out of scope | Billing/invoices, payments, raw ingredients, recipes, purchasing/vendors/suppliers, accounting/GST filing, customer ordering and delivery integration are not missing mobile requirements. Last billing-integration inclusion is overridden by this audit's user exclusion. |
| 17 Expected deliverables | Source exists for web, Android waiter, browser KDS and additional kitchen app. Multi-kitchen routing, full reporting/settings/documentation and production/UAT obligations remain partial or unverified. Billing REST integration excluded. |

## Shared non-functional and wider system obligations

These matter to an end-to-end requirement match but are not missing phone screens.

| Requirement | Result | Evidence or remaining work |
|---|---|---|
| Responsive web application | Web owned / Verify | Blade/Bootstrap layouts exist; no browser/device visual acceptance performed here. |
| Mobile-friendly interfaces | Implemented / Verify | Responsive Flutter layouts; verify target devices, text scaling, keyboard use and accessibility. |
| Fast kitchen updates | Verify | No latency measurements or load evidence; polling fallback alone does not prove real-time requirement. |
| Secure authentication and authorization | Implemented mobile; system Partial | Sanctum, abilities, active-account checks, API scopes and secure storage exist. G12 is an alternate web authorization defect. |
| Branch-wise isolation | Implemented mobile; system Partial | Mobile controllers scope identity server-side. Friendly KDS direct menu mutation lacks equivalent scope. |
| Real-time communication | Implemented / Verify | WebSocket source and after-commit events exist. Friendly browser path remains polling-only. |
| Automatic kitchen reconnect | Implemented / Verify | Backoff and catch-up logic; confirm network loss, app resume and server restart on devices. |
| Reliable stock transactions | Implemented / Verify | Transactions/locks/ledger exist; concurrent low-stock orders and admin stock changes still require MySQL verification. |
| Duplicate order prevention | Partial | Same-key API dedupe and normal pending drafts exist; G05 weakens operation recovery. Order-number reliability G07 and KOT-number collision G13 are separate concerns. |
| Critical-operation audit logs | Partial | Stock and cancellation histories exist; no general audit log or persistent complete status-transition history was found. `order_status_history` and `audit_logs` suggested names are not required, but equivalent behavior is. |
| General API documentation | Partial | App READMEs/plans explain setup and endpoint usage; no comprehensive API request/response/error contract or OpenAPI file found. General API documentation remains in scope under 13/17. |
| Database backup and recovery | Verify | Readiness guide calls for verification; no schedule, restoration runbook or executed recovery evidence in repo. External backups may exist. |
| Secure device/display registration | Implemented / Verify | Scoped hashed TV credential, rotate/revoke and mobile push registration exist; verify revocation and branch/station isolation at runtime. |
| Scale to more branches/kitchens | Partial / Verify | Configurable relational organization exists; order/KOT sequence races and multi-kitchen mapping gap must be fixed, then load-tested. |
| Dashboard requested order totals | Implemented / Verify | `DashboardController:137` six order counters, branch breakdown at 164 and kitchen pending at 184. Reconcile to actual data. Status/order-number filters affect recent-order list rather than every summary. |
| Order/KOT/kitchen/dish quantity reports | Partial | Operational order and KOT lists exist. Complete dedicated dish-quantity and required-filter report suite not found. |
| Stock-in/deduction/adjustment/wastage/cancellation reports | Partial | Ledger and operational screens expose relevant records; full named reports and reconciliation acceptance not demonstrated. |
| Preparation-time and user-activity reports | Missing as complete report capabilities | Timing fields exist; no full preparation-time report or user-activity report module found. |
| Date/company/branch/kitchen/order/status reporting filters | Partial | Several list/dashboard filters exist; not consistently supplied across the complete report suite. |
| General, company/branch, KOT/order numbering settings | Partial | Masters/config files exist; order/KOT number formats hardcoded in `OrderService`; no general settings management module found. |
| Notification/sound/stock/cancellation settings | Partial | TV sound toggle, stock tracking and branch approval setting exist; not the complete settings module described in 11. |
| Database documentation | Partial | Migrations describe implementation; no complete schema, data dictionary or ERD found. |
| User and technical documentation | Partial | App READMEs/readiness plans are useful; several contradict current code and root README/CHANGELOG remain framework scaffold. |
| Testing, UAT, production deployment/configuration | Verify | Checklists exist; completed device UAT, release sign-off and running production services were not established. |

## Acceptance scenario traceability

The source document section 15 says these scenarios must be tested before production. Implemented source and an existing test method are not equivalent to a completed acceptance run. Billing synchronization is excluded.

| ID | Document acceptance scenario | Available evidence and acceptance needed |
|---|---|---|
| A01 | Multiple companies and branches | CRUD/scoping source and web RBAC fixtures exist. Verify real mobile accounts cannot cross boundaries. |
| A02 | Multiple kitchens in one branch | Models/mappings and legacy order fixtures exist. Verify assigned station selection and delivery. |
| A03 | Assign dishes to different kitchens | Mapping UI and kitchen grouping exist. Include absent/disabled mapping rejection and G02 policy. |
| A04 | One order with dishes from three kitchens | Exact single-submission three-kitchen scenario is not covered by existing test source; test across both apps and TV. |
| A05 | Every kitchen receives only assigned dishes | Verify API, WebSocket and push boundaries; also one dish requiring several kitchens, currently G01. |
| A06 | Same KOT is not duplicated | Retry identical create/add request with original key, concurrent retries and lost response; inspect tickets/stock ledger. Separately verify distinct tickets cannot receive the same branch/day KOT number, G13. |
| A07 | Add dishes to existing table order | Source/legacy tests cover additions; verify same order remains and original lines stay intact. |
| A08 | Additional items generate new KOTs | Source/legacy test assertions exist; verify latest kitchen work and one stock deduction per batch. |
| A09 | Update preparation from kitchen TV | Exercise registered and friendly routes; confirm same transition rules and cancelled-line preservation. |
| A10 | Waiter receives updated status | Verify foreground order screen, another screen, background notification and notification-open behavior. |
| A11 | Add prepared stock | Test opening stock, stock-in and multiple staff; inspect actor/reason/timestamps. |
| A12 | Deduct stock on successful submission | Verify successful order, transaction rollback, add-items and retry; existing order fixtures disable stock tracking. |
| A13 | Insufficient stock validation | Two waiters compete for last portions; no negative balance, clear latest quantity and editable cart. |
| A14 | Cancellation and reversal policy | New/Accepted restore; Preparing/Ready/Served wastage; partial/full cancellation, approval/rejection and concurrent prep. |
| A15 | Sold-out dishes disabled on mobile | Test mobile stock screen and both TV availability paths while menu and review sheet are open. |
| A16 | Branch access restrictions | Test another branch/company ID, unassigned station, private-channel authorization and alternate web mutation paths. |
| A17 | Display reconnect after network interruption | Disable network, submit work elsewhere, restore network; warning and automatic catch-up with no duplicates. |
| A18 | Billing authentication/synchronization | Excluded. |
| A19 | API retries do not duplicate orders/deductions | Main composer recovery exists; force-close after uncertain quantity increase exposes G05. Verify both paths after correction. |
| A20 | Reports match order/stock transactions | Compare dashboard, orders, KOTs, cancellations and ledger; complete missing reports first. |

Also verify release signing, HTTPS/WSS enforcement, expiry/revocation, Android notification permission denied/granted, logout push cleanup, multi-station staff and app resume after a long interruption.

## Validation performed and limits

- Read the complete DOCX body and tables, all Flutter application source areas, the mobile API routes/controllers, shared order/stock/cancellation services, events/push code, relevant migrations and browser KDS paths.
- Ran PHP syntax validation again after the external merge on **107** application, route, migration and seeder files: **zero syntax failures**.
- Inspected **27** existing PHP test methods: 4 web authentication, 13 web RBAC, 8 legacy order/KOT and 2 scaffold examples. They do not cover the new mobile-token APIs, prepared stock, cancellation approval/reversal, push, reconnect or idempotent recovery. The order fixtures explicitly disable stock tracking at `OrderManagementTest.php:122,136`.
- No Flutter unit/widget/integration test files were found. Existing CI runs PHP tests only.
- No automated tests were executed, consistent with the repository's manual-testing guidance in `master.md:15`. This review did not submit live orders, change stock, change users or alter production data.
- Waiter Dart static analysis passed with exit code 0 and no diagnostics using the installed Flutter SDK's cached Dart analyzer.
- Kitchen Dart analysis reported **No issues found**, then exited with code 1 because access to Dart's telemetry cleanup file outside the workspace was denied. The requested escalated retry was aborted by the user. No clean successful kitchen analyzer exit is claimed. All analysis sessions were stopped or completed; no process is left running by this audit.
- Current local environment selects Reverb and a database queue. Older documentation saying the active local broadcaster is `log` is stale. This check does not establish that Reverb or a queue worker is running or reachable from either device.
- Both apps have Firebase Android configuration, signing properties and an existing keystore at the configured location. File presence does not establish correct credentials, production ownership, successful FCM delivery or a signed release build. No signing secrets were printed.
- Standard release AAB/APK output locations were not present in either app at review time; an artifact may exist elsewhere. No fresh release build was attempted.
- FCM server configuration depends on `services.fcm.project_id` and `service_account_path`. No `FCM_*` entries were found in the local `.env`; external environment configuration and actual delivery remain unverified.
- Source-level concurrency risks G04/G07/G13 are reasoned from lock and update boundaries. They were not presented as runtime reproductions.

## Documentation corrections needed

| Location | Discrepancy |
|---|---|
| `docs/master.md:41` | Still says user management and orders/KOT are pending, though implemented. |
| `docs/WEB_AUTHENTICATION_AND_AUTHORIZATION.md:23-24` | Says mobile/token authentication does not exist; current Sanctum APIs contradict it. |
| Same file, 100-101 | Says waiter and kitchen roles have no permissions; current role seeder assigns them. |
| `docs/mobile-app-plan.md:64` | Older background-push-pending claim conflicts with later FCM implementation. |
| `docs/kitchen-mobile-app-plan.md:35,55` | Background notifications and local broadcaster claims are stale. |
| `docs/mobile-app-plan.md:71` | Network-only Android permission description predates notification permission support. |
| Mobile signing/setup wording | Claims that owner signing files are unsupplied are inconsistent with their current presence; validity still requires verification. |
| Kitchen README stock/history access | Describes read-only staff access, but navigation is manager-only. G10. |
| Root README and CHANGELOG | Framework scaffold content does not provide the requested Nagarjuna user/technical documentation. |

## Suggested completion order

1. Fix G01/G02 kitchen routing and explicitly define multi-station quantity/status/cancellation semantics.
2. Fix G03/G04 terminal cancellation state and transaction-level transition guards across every mobile and TV entry point.
3. Fix G12 alternate KDS authorization and unify availability events/ledger handling; fix G11 cancellation visibility on both browser paths.
4. Fix G05 recovery identity and G06 quantity wording, then address G07/G13 atomic order and KOT numbering.
5. Complete kitchen-mobile received-time/sound parity and resolve open-review freshness and history access expectations.
6. Complete the web/system report/settings/audit/documentation obligations that remain in scope.
7. Run all applicable section 15 scenarios in staging with real waiter/kitchen accounts, the intended TV path, MySQL, Reverb, queue worker and FCM; then produce and sign both release artifacts.

The appropriate present status is **substantial implementation coverage with identified gaps and acceptance still pending**, rather than a claim that every requirement is satisfied.
