# Nagarjuna mobile and kitchen API

This contract covers restaurant operations used by the waiter app, kitchen app and browser KDS. Billing software integration is excluded. The route definitions in [api.php](../routes/api.php) and [web.php](../routes/web.php) are the executable route inventory.

## Common rules

- Base URL: `https://<deployment-host>/api/v1`. Development Android emulators can use `http://10.0.2.2:8000/api/v1`. Release clients require HTTPS and a non-placeholder host.
- Send `Accept: application/json` and `Content-Type: application/json`. Protected mobile endpoints require `Authorization: Bearer <token>`.
- Login returns a seven-day Sanctum token with either `waiter:mobile` or `kitchen:mobile`. These abilities cannot be interchanged. Logout revokes that token; its push registrations are removed by the token foreign key.
- Company and branch come from the signed-in mobile account. Supplying another tenant ID never changes that scope. Kitchen accounts must also have an explicit active kitchen assignment.
- IDs are integers. UUIDs are used for submission keys and cancellation batches. Currency reference values are decimal strings. API timestamps are ISO 8601, including an offset; clients convert them to local display time.
- Successful mobile responses generally use `{ "data": { ... }, "message": "..." }`. Action-only responses can contain only `message`. Nullable fields may be `null`; array fields are arrays even when empty.
- Stock is prepared portions for a branch dish, shared across all kitchens preparing that dish. It is not ingredient inventory.

| Status | Meaning and client action |
|---|---|
| 200 | Read, update, action or replay succeeded. |
| 201 | A new mobile order was committed. |
| 202 | Cancellation awaits approval; quantities and stock are unchanged until approved. |
| 401 | Missing, expired or revoked token. Sign in again. |
| 403 | Inactive account, wrong ability or missing permission. Do not retry with another tenant ID. |
| 404 | Record missing or outside the account's accessible scope. |
| 409 | Current state conflicts, pending request, closed order or key reused for a different payload. Refresh/reconcile; retain uncertain submission keys. |
| 422 | Validation failed. Show `errors` and permit correction after a definitive rejection. |
| 429 | Login or endpoint throttled. Wait and retry without changing an uncertain request key. |
| 5xx or transport timeout | Outcome may be unknown. Retry the identical submission and original key; never silently create a new request. |

Validation errors use `{ "message": "...", "errors": { "field": ["..."] } }`; authorization/conflict errors normally contain `message`.

## Authentication and device notifications

| Method and path | Body and response | Access |
|---|---|---|
| `POST /auth/login` | `{email,password,device_name}`; `data.token`, `token_type`, `expires_at`, `user`. | Registered waiter/captain with order-entry permissions, or an eligible cancellation approver; six attempts/minute. |
| `GET /auth/me` | `data.user` with id, name, employee_code, designation, email, phone, roles, permissions, company and branch. | Waiter token. |
| `POST /auth/logout` | No body; message. | Waiter token. |
| `POST /kitchen-mobile/auth/login` | Same login body/response shape. User includes `can_view_stock`, `can_manage_stock`, `sound_allowed`. | Active kitchen role with `kot.view` and `kot.edit`; six attempts/minute. |
| `GET /kitchen-mobile/auth/me` | `data.user`. | Kitchen token. |
| `POST /kitchen-mobile/auth/logout` | No body; message. | Kitchen token. |
| `POST /mobile/push-token` | `{token,platform}` where platform is `android` or `ios`; message. | Waiter-app token and order view permission, including eligible management accounts. |
| `DELETE /mobile/push-token` | `{token}`; message. | Same session that registered it. |
| `POST /kitchen-mobile/push-token` | Same registration body. | Kitchen role/token. |
| `DELETE /kitchen-mobile/push-token` | `{token}`. | Same kitchen session. |

Device tokens are encrypted in storage and indexed by a hash. They are not returned to clients. FCM delivery requires server project/service-account configuration and a running queue worker; Android registration alone does not establish delivery.

## Waiter floor and menu reads

| Method and path | Response |
|---|---|
| `GET /floors` | `data.branch`, `data.floors[]` with id/name/code and `tables[]`. Requires floor/table view permissions. |
| `GET /tables?floor_id=<id>` | `data.branch`, nullable `floor`, `tables[]`; optional floor must be active in the assigned branch. |
| `GET /menu` | `data.branch`, `categories[]` with id/name and `items[]`. Requires category/menu-type/menu-item view permissions. |

Table fields: `id`, `floor_id`, `name`, `seating_capacity`, lowercase `status`, nullable `active_order_id` and `active_order_number`. Available tables start an order; occupied tables with active IDs resume that order. Reserved/Cleaning cannot start orders. The waiter screen refreshes every 12 seconds while visible and on resume.

Menu item fields: `id`, `category_id`, `name`, `code`, `description`, nullable `image_path`, `selling_price`, `menu_type:{id,name}`, `availability`, `stock_tracking_enabled`, nullable `prepared_stock_quantity`, `stock_configured`, `kitchen_configured`, `is_orderable`. Availability values are `available`, `temporarily_unavailable`, `sold_out`. Inactive category/type/dish records are omitted. An unmapped dish is non-orderable; submitting it is also rejected on the server. Images resolve against the API origin. Menu events and a 15-second refresh keep the menu and open review sheet current.

## Create orders and add portions

`POST /mobile/orders` and `POST /mobile/orders/{order}/items` require `order.add` and a UUID **Idempotency-Key** header. Persist the exact payload and key before sending.

Example create payload:

```json
{
  "table_id": 12,
  "guest_count": 2,
  "special_instructions": "Serve together",
  "items": [
    {"menu_item_id": 31, "quantity": 2, "special_instructions": "Less spicy"}
  ]
}
```

Create requires `guest_count` 1–100 and no more than table capacity. Both requests require 1–100 lines, each quantity 1–99, item notes up to 255 characters and order instructions up to 1000. Add-items also requires `table_id` matching the order; it does not require `guest_count`. New order instructions are appended subject to the combined limit.

Both return `data.order` and `data.replayed`. Create returns 201; replay and additions return 200. The response contains the current order, not a frozen snapshot of the original request. Same account/key/operation/payload returns that order without inserting another line or deducting stock again. Reusing the key for different content returns 409. A rolled-back request can be retried with its original key.

The server locks/revalidates the table, dish state and stock; commits the order, item rows, stock ledger and kitchen tickets transactionally; and dispatches events after commit. Each active assigned kitchen receives the full preparation quantity for its station. Stock decreases **once per customer line**, regardless of station count. A dish without an active mapping fails the transaction. Additional portions create new lines and new KOTs; earlier tickets remain intact.

The waiter **Add extra** flow saves a separate immutable addition with its original key per signed-in staff ID. After navigation/restart it reopens the affected order and safely reconciles that same request. Other mutations are blocked until it is resolved. A screen refresh alone must not be interpreted as proof that a timed-out write failed.

## Order reads and actions

| Method and path | Body | Permission and behavior |
|---|---|---|
| `GET /mobile/orders/{order}` | None | `order.view`; `data.order` in assigned tenant. |
| `PATCH /mobile/orders/{order}/items/{item}/instructions` | `{special_instructions}` nullable string, max 255 | `order.edit`; allowed only while every linked station is New/Accepted and no cancellation is pending. |
| `POST /mobile/orders/{order}/items/{item}/served` | None | `order.edit`; every active station must be Ready/Served. Already served is safe to repeat. |
| `POST /mobile/orders/{order}/complete` | None | `order.edit`; active tickets Ready/Served, cancelled tickets terminal, no pending cancellation. Releases the table transactionally. |

Order fields: `id`, `order_number`, `status`, `updated_at`, `guest_count`, `table:{id,name}`, reference `total_amount`, `special_instructions`, `items[]`, `kot_tickets[]`.

Order item fields: `id`, `menu_item_id`, `name`, original `quantity`, `cancelled_quantity`, `pending_cancellation_quantity`, `cancellable_quantity`, `can_modify`, `can_mark_served`, decimal `unit_price`/`total_price`, `special_instructions`, `status`, `cancellations[]`.

Each KOT summary has id/number/kitchen_name/status and items with menu_item_id/name/original quantity/cancelled_quantity/active_quantity/status. A customer item's status waits for its slowest active preparation station; it is Ready only after all stations are ready, and Served only after all stations are served. Fully cancelled portions stay Cancelled. Order headers aggregate ticket state; completion is explicit, rather than a kitchen Ready action releasing the table.

## Cancellation and approval

`POST /mobile/orders/{order}/cancellations` requires `order.cancel` and a trimmed reason of 3–500 characters. Send either:

```json
{"reason":"Guest changed their order","cancel_all":true}
```

or:

```json
{"reason":"Guest changed their order","items":[{"order_item_id":81,"quantity":1}]}
```

It returns message and `data.request_batch`, cancellation summaries and updated order. Existing pending/approved quantities cannot be requested again beyond the original line quantity.

| Method and path | Behavior |
|---|---|
| `GET /mobile/cancellation-requests` | `order.approve`; `data.requests[]`, grouped by batch with order/table/requester/time, items and `can_approve`. |
| `POST /mobile/cancellation-requests/{batch}/approve` | `order.approve`; no body, message. Under separate-approver policy the requester cannot approve. |
| `POST /mobile/cancellation-requests/{batch}/reject` | `order.approve`; `{decision_note}` of 3–500 characters, message. |

When approval is disabled, cancellation applies immediately. When enabled, stock and KOT quantities change only on approval. Every assigned station cancels the same customer quantity. Stock returns once only when **all** stations remain New/Accepted; if any station started preparing, the cancelled portions are recorded as wastage with no stock return. Audit records preserve requester, decider, timestamps, reasons, decision note, restored quantity and wastage quantity. These policies are server-enforced for mobile, TV and web entry points.

## Kitchen queue and stock

| Method and path | Body/query | Response and access |
|---|---|---|
| `GET /kitchen-mobile/kitchens` | None | `data.branch`, assigned active `kitchens[]`; `kot.view`. |
| `GET /kitchen-mobile/kot` | `view=active|completed|cancelled`, optional page/per_page | All assigned kitchens; `data.kitchens`, `tickets[]`, nullable `pagination`, `view`, `refreshed_at`, `sound_allowed`. |
| `GET /kitchen-mobile/kitchens/{kitchen}/kot` | Same query | One assigned station; `data.kitchen`, otherwise same shape. |
| `PATCH /kitchen-mobile/kot-tickets/{ticket}/status` | `{status}`: accepted/preparing/ready | `kot.edit`; message and `data.ticket`. |
| `GET /kitchen-mobile/stock` | page/per_page | `menu-item.view`; `data.items`, `pagination`, `can_manage_stock`. Read-only staff may access it. |
| `GET /kitchen-mobile/stock/{menuItem}/transactions` | page/per_page | `menu-item.view`; `data.transactions`, `pagination`. |
| `POST /kitchen-mobile/stock/{menuItem}/adjustments` | type, quantity, reason, optional direction | `menu-item.stock-adjustment`; message and `data.item`. |
| `PATCH /kitchen-mobile/stock/{menuItem}/availability` | availability, reason | Same adjustment permission; message and `data.item`. |

All reads/mutations require the kitchen token, tenant scope and active dish-to-assigned-station mapping. The active queue is unpaginated so pending work is not hidden. Completed/cancelled queues, stock dishes and history use positive `page` and `per_page` 1–100, default 50. Pagination fields are `current_page`, `last_page`, `per_page`, `total`, `has_more`; empty pages remain arrays. Completed includes served tickets or completed parent orders; cancelled includes tickets with any cancelled quantity.

Kitchen ticket records include id/kot_number/order_id/kitchen_id/ticket_status, received/accepted/preparing/ready timestamps, order/table context and item rows with original and cancelled quantities, notes and statuses. The app displays the received clock time as well as waiting age.

Stock item fields are id/name/code/stock_tracking_enabled/nullable prepared_stock_quantity/availability/kitchens. Transaction fields are id/type/quantity/quantity_delta/stock_before/stock_after/reason/user/created_at.

Adjustment types: `stock_in`, `wastage`, `damaged`, `manual_adjustment`. Quantity is 1–999999; reason is 3–500 characters. Manual adjustment also requires `direction=increase|decrease`. The first positive initialization is recorded as `opening_stock`. Tracking must be enabled in menu management first. Negative balances fail. Availability accepts `available|temporarily_unavailable|sold_out`, with a reason. Every successful availability/stock mutation writes the ledger and broadcasts the menu change.

## Live events and Android push

Reverb uses the Pusher WebSocket protocol. Authorize subscriptions with `POST /api/broadcasting/auth` (outside the `/v1` prefix), bearer token, and `{socket_id,channel_name}`. Subscribe to private channels:

- `private-kitchens.{id}`: authorized assigned kitchen staff or scoped management with KOT permission.
- `private-orders.{id}`: waiter-app token, order view permission and assigned tenant.
- `private-branches.{id}.menu`: waiter-app token, menu view permission and assigned tenant.

KOT event names: `kot.ticket.created`, `kot.ticket.updated`, `kot.ticket.cancelled`. Data: ticket_id/kitchen_id/order_id/change_type/status. `menu.item.updated` contains menu_item_id/availability/prepared_stock_quantity. Treat events as invalidations and fetch authoritative records. On reconnect fetch pending work before assuming synchronization. Both mobile apps retain polling; order/floor/kitchen intervals are 12 seconds, menu 15 seconds.

Queued FCM messages use `type=kot_created|kot_cancelled` for kitchen staff and `type=order_ready` for the original waiter. Data includes ticket/kitchen/order identifiers and order number. Foreground kitchen new-ticket sound requires the operator's enabled preference and branch permission; background alerts also depend on Android channel settings.

## Browser KDS and management endpoints

The session API under `/api/v1/orders`, `/kitchens/{id}/kot`, `/kot-tickets/{id}/status` and `/kot-items/{id}/status` remains for existing web operations. It requires authenticated active sessions and module permissions, with tenant/station guards. It is not the mobile bearer API or a billing integration contract; legacy session submissions do not provide the mobile submission-key recovery contract.

Web KDS paths: `/{branchCode}/{kitchenCode}`, `/kds`, `/kds/all`, `/kds/{kitchen}/feed`. Feeds include stage arrays, remaining/cancelled quantities, paginated `history=cancelled|completed` and `history_page`, and recent cancellation updates. Friendly screens authenticate private channels with the web session.

Registered display URLs `/kds/{64-character-token}` are scoped bearer credentials for one active kitchen. Register/rotate/revoke from the kitchen editor. Their broadcasting auth and ticket status endpoints use the same token and station scope. Never put these URLs in public dashboards or logs. Both TV paths retain automatic refresh, stage sections, history, sound controls and reconnection.

Management routes `/reports` and `/settings` use web sessions. Reports require `report.view`, exports `report.export`, and user activity additionally `audit-log.view/export`. Settings require `settings.view/edit` and authorized branch scope. Their detailed filters and operator workflows are in [OPERATIONS.md](OPERATIONS.md).
