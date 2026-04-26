# TRETECH Backend — Frontend Reference (Laravel API v1)

This document is a **frontend-facing contract** for the TRETECH backend:

- **Base URL**: `/api/v1`
- **Auth**: Bearer token (Laravel Sanctum)
- **Response envelope**: Always wrapped in `{ success, message, status_code, data, timestamp }`
- **Authorization**: Route-level permission middleware (RBAC)

It is derived from:

- `routes/api.php` (actual registered endpoints)
- `app/Http/Requests/Api/V1/**` (validation rules = request contract)
- `app/Http/Resources/Api/V1/**` (response fields = response contract)
- `app/Services/**` (workflow side-effects and business rules)

> If something in this document ever disagrees with backend behavior, treat the code as the source of truth.

---

## API fundamentals (applies to all endpoints)

### Base headers

From `ApiBaseMiddleware` (`app/Http/Middleware/ApiBaseMiddleware.php`):

- `X-API-Version: 1.0`
- `X-Request-ID: <forwarded from request OR generated>`
- For JSON responses: `Content-Type: application/json`

Recommended request headers:

- `Authorization: Bearer <token>` (required on all protected endpoints)
- `X-Device-Id: <string>` (optional, used by auth/audit context; important for mobile)
- `X-Request-ID: <string>` (optional; if you provide it, backend will echo it)

### Standard response envelope

Success:

```json
{
  "success": true,
  "message": "Success",
  "status_code": 200,
  "data": {},
  "timestamp": "2026-04-10T00:00:00Z"
}
```

Error:

```json
{
  "success": false,
  "message": "Validation failed",
  "status_code": 422,
  "errors": {
    "field": ["error message"]
  },
  "timestamp": "2026-04-10T00:00:00Z"
}
```

Pagination:

- **Length-aware pagination** (`page`): includes `pagination.total`, `per_page`, `current_page`, `last_page`, `from`, `to`
- **Cursor pagination** (`cursor`): includes `pagination.per_page`, `next_cursor`, `prev_cursor`, `has_more`

### Common HTTP status codes

- `200` OK
- `201` Created
- `401` Unauthenticated (Sanctum)
- `403` Forbidden (RBAC / policy)
- `404` Not found (route/model binding)
- `409` Conflict (unique constraint / FK constraint / domain conflict)
- `422` Validation failed
- `500` Server error (sanitized outside local env)

### Error behavior and exception mapping

Centralized in `app/Exceptions/Handler.php`:

- **Validation**: `422` with `errors: { field: [...] }`
- **Unauthenticated**: `401` with message `"Unauthenticated. Please login to continue"`
- **Unauthorized**: `403` with message `"You are not authorized to perform this action"`
- **Model not found**: `404` with message like `"<Model> not found"`
- **Unknown route**: `404` `"Endpoint not found"`
- **DB constraints**:
  - unique violation → `409` `"This record already exists"`
  - FK constraint → `409` `"Cannot delete this record as it is referenced by other records"`

### RBAC (roles + permissions)

Tables: `roles`, `permissions`, `role_permissions`.

Permission middleware used in routes:

- `permission:<code1>,<code2>`: **any** of the codes
- `all-permissions:<code1>,<code2>`: **all** required (alias exists)

The authenticated user’s permission list is returned by:

- `GET /api/v1/auth/permissions`

### Health check (public)

- `GET /api/health`
- `GET /api/v1/health`

Response:

```json
{
  "success": true,
  "message": "API is healthy",
  "status_code": 200,
  "data": { "status": "ok" },
  "timestamp": "..."
}
```

### RBAC sanity-check endpoint

This endpoint is mainly for debugging role/permission configuration.

- `GET /api/v1/rbac/check`
- **Auth**: required
- **Permission**: `system.manage_roles`

Response `data`: `null`

### Request/response logging (optional, ops)

`app/Http/Middleware/LogApiRequests.php` logs:

- request method/path/ip/user_id/query_params
- response status and duration

It writes to `Log::channel('api')` (ensure the `api` channel exists in `config/logging.php` in production).

---

## Authentication module

### POST `/auth/login` (public)

- **Throttle**: `throttle:login` (5 attempts / 15 min per IP)
- **Request headers**:
  - `X-Device-Id` (optional)

Request body (from `LoginRequest`):

- `email`: **required**, `email:rfc,dns`
- `password`: **required**, string, min 8
- `device_name`: optional, string, max 100

Success `200` response `data` (from `AuthService::login()`):

- `token`: string
- `token_type`: `"Bearer"`
- `user`:
  - `id`, `full_name`, `email`, `role` (role_code), `is_active`, `last_login_at` (ISO8601|null)
- `permissions`: string[] (permission codes)

Error cases:

- `401`: invalid email/password
- `403`: user inactive

### POST `/auth/logout`

- **Auth**: required (`auth:sanctum`)

Response `data`: `null`

### GET `/auth/me`

- **Auth**: required

Response `data`:

- `user` (same shape as login)
- `permissions` (string[])

### GET `/auth/permissions`

- **Auth**: required

Response `data`:

- `role`: string|null (role_code)
- `permissions`: string[]

---

## Master Data module

All master-data endpoints are under `/master-data/*` and require:

- **Auth**: `auth:sanctum`
- **Permission**: per route (see each endpoint)

### Users

#### GET `/master-data/users`

- **Permission**: `system.manage_users`
- Query:
  - `search` (name/email substring)
  - `role_id` (int)
  - `is_active` (bool-ish)
  - `per_page` (default 15, max 100)

Response item fields (from `UserResource`):

- `id`, `role_id`, `role_code`, `role_name`, `full_name`, `email`,
  `is_active`, `last_login_at`, `created_at`, `updated_at`

#### POST `/master-data/users`

- **Permission**: `system.manage_users`

Body (from `StoreUserRequest`):

- `role_id`: **required**, integer, exists roles.id
- `full_name`: **required**, string max 255
- `email`: **required**, email max 255, unique users.email
- `password`: **required**, string min 8 max 255
- `is_active`: optional boolean

Response: `UserResource` (above)

#### GET `/master-data/users/{user}`

- **Permission**: `system.manage_users`

Response: `UserResource`

#### PUT/PATCH `/master-data/users/{user}`

- **Permission**: `system.manage_users`

Body (from `UpdateUserRequest`):

- `role_id`: sometimes required, integer exists roles.id
- `full_name`: sometimes required, string max 255
- `email`: sometimes required, email max 255, unique ignore current user
- `password`: sometimes nullable string min 8 max 255
- `is_active`: sometimes boolean

Response: `UserResource`

#### DELETE `/master-data/users/{user}`

- **Permission**: `system.manage_users`
- Response `data`: `null`

### Products

#### GET `/master-data/products`

- **Permission**: `products.view`
- Query:
  - `search` (matches `ref_num`, `product_name`, `product_type`, `category`)
  - `is_active` (bool-ish)
  - `per_page` (default 15, max 100)

Response item fields (from `ProductResource`):

- `id`, `ref_num`, `product_name`, `product_type`, `category`, `uom`,
  `requires_expiry`, `requires_lot`, `is_active`, `created_at`, `updated_at`

#### POST `/master-data/products`

- **Permission**: `products.create`

Body (from `StoreProductRequest`):

- `ref_num`: **required**, string max 255, unique products.ref_num
- `product_name`: **required**, string max 255
- `product_type`: nullable string max 255
- `category`: nullable string max 255
- `uom`: nullable string max 100
- `requires_expiry`: sometimes boolean
- `requires_lot`: sometimes boolean
- `is_active`: sometimes boolean

Response: `ProductResource`

#### GET `/master-data/products/{product}`

- **Permission**: `products.view`
- Response: `ProductResource`

#### PUT/PATCH `/master-data/products/{product}`

- **Permission**: `products.edit`

Body (from `UpdateProductRequest`):

- `ref_num`: sometimes required, string max 255, unique ignore current
- `product_name`: sometimes required, string max 255
- `product_type`: nullable string max 255
- `category`: nullable string max 255
- `uom`: nullable string max 100
- `requires_expiry`: sometimes boolean
- `requires_lot`: sometimes boolean
- `is_active`: sometimes boolean

Response: `ProductResource`

#### DELETE `/master-data/products/{product}`

- **Permission**: `products.delete`
- Response `data`: `null`

### Suppliers

#### GET `/master-data/suppliers`

- **Permission**: `suppliers.view` OR `suppliers.manage`
- Query:
  - `search` (supplier_name/phone/email substring)
  - `is_active` (bool-ish)
  - `per_page` (default 15, max 100)

Response item fields (from `SupplierResource`):

- `id`, `supplier_name`, `phone`, `email`, `address`, `is_active`, `created_at`, `updated_at`

#### POST `/master-data/suppliers`

- **Permission**: `suppliers.manage`

Body (from `StoreSupplierRequest`):

- `supplier_name`: **required** string max 255
- `phone`: nullable string max 50
- `email`: nullable email max 255
- `address`: nullable string
- `is_active`: sometimes boolean

Response: `SupplierResource`

#### GET `/master-data/suppliers/{supplier}`

- **Permission**: `suppliers.view` OR `suppliers.manage`
- Response: `SupplierResource`

#### PUT/PATCH `/master-data/suppliers/{supplier}`

- **Permission**: `suppliers.manage`

Body (from `UpdateSupplierRequest`):

- `supplier_name`: sometimes required string max 255
- `phone`: nullable string max 50
- `email`: nullable email max 255
- `address`: nullable string
- `is_active`: sometimes boolean

Response: `SupplierResource`

#### DELETE `/master-data/suppliers/{supplier}`

- **Permission**: `suppliers.manage`
- Response `data`: `null`

### Clients

#### GET `/master-data/clients`

- **Permission**: `clients.view` OR `clients.manage`
- Query:
  - `search` (client_name/type/phone/email substring)
  - `client_type` (exact match)
  - `is_active` (bool-ish)
  - `per_page` (default 15, max 100)

Response fields (from `ClientResource`):

- `id`, `client_name`, `client_type`, `phone`, `email`, `address`, `is_active`, `created_at`, `updated_at`

#### POST `/master-data/clients`

- **Permission**: `clients.manage`

Body (from `StoreClientRequest`):

- `client_name`: required string max 255
- `client_type`: required string max 100
- `phone`: nullable string max 50
- `email`: nullable email max 255
- `address`: nullable string
- `is_active`: sometimes boolean

Response: `ClientResource`

#### GET `/master-data/clients/{client}`

- **Permission**: `clients.view` OR `clients.manage`
- Response: `ClientResource`

#### PUT/PATCH `/master-data/clients/{client}`

- **Permission**: `clients.manage`

Body (from `UpdateClientRequest`):

- `client_name`: sometimes required string max 255
- `client_type`: sometimes required string max 100
- `phone`: nullable string max 50
- `email`: nullable email max 255
- `address`: nullable string
- `is_active`: sometimes boolean

Response: `ClientResource`

#### DELETE `/master-data/clients/{client}`

- **Permission**: `clients.manage`
- Response `data`: `null`

### Instrument Sets

#### GET `/master-data/instrument-sets`

- **Permission**: `instrument_sets.view` OR `instrument_sets.manage`
- Query:
  - `search` (set_code/set_name substring)
  - `is_active` (bool-ish)
  - `per_page` (default 15, max 100)

Response fields (from `InstrumentSetResource`):

- `id`, `set_code`, `set_name`, `description`, `is_active`, `created_at`, `updated_at`

#### POST `/master-data/instrument-sets`

- **Permission**: `instrument_sets.manage`

Body (from `StoreInstrumentSetRequest`):

- `set_code`: nullable string max 255, unique instrument_sets.set_code
- `set_name`: required string max 255
- `description`: nullable string
- `is_active`: sometimes boolean

Response: `InstrumentSetResource`

#### GET `/master-data/instrument-sets/{instrumentSet}`

- **Permission**: `instrument_sets.view` OR `instrument_sets.manage`
- Response: `InstrumentSetResource`

#### PUT/PATCH `/master-data/instrument-sets/{instrumentSet}`

- **Permission**: `instrument_sets.manage`

Body (from `UpdateInstrumentSetRequest`):

- `set_code`: nullable string max 255, unique ignore current
- `set_name`: sometimes required string max 255
- `description`: nullable string
- `is_active`: sometimes boolean

Response: `InstrumentSetResource`

#### DELETE `/master-data/instrument-sets/{instrumentSet}`

- **Permission**: `instrument_sets.manage`
- Response `data`: `null`

---

## Stock-In module

### Stock-in session lifecycle

- Sessions start as `status = "draft"`.
- Only **draft** sessions can be updated / have items edited.
- Finalization is **atomic**:
  - every item produces exactly one `lots` row
  - lots become:
    - `available` if lot number present
    - `holding` if `missing_lot_flag=true` or lot number empty (system generates `HOLD-...`)
  - a `lot_movements` row is created for each lot (`movement_type = "stock_in"`)
  - QR label is generated (if missing) and a print job is queued for every lot
  - session becomes `finalized`, sets `confirmed_at`, `confirmed_by_user_id`

### GET `/stock-in-sessions`

- **Permission**: `stock_in.view`
- Query:
  - `search` (session_no/do_number)
  - `status` (draft|finalized|...)
  - `supplier_id` (int)
  - `from_date` (date)
  - `to_date` (date)
  - `per_page` (default 15, max 100)

Response item fields (from `StockInSessionResource`):

- `id`, `supplier_id`, `supplier?{id,supplier_name}`, `session_no`, `do_number`, `stock_in_at`,
  `pic_user_id`, `pic_user?{id,full_name}`, `status`, `remarks`,
  `confirmed_at`, `confirmed_by_user_id`, `confirmed_by_user?{id,full_name}`,
  `items_count`, `items` (optional), `created_at`, `updated_at`

### POST `/stock-in-sessions`

- **Permission**: `stock_in.create`

Body (from `StoreStockInSessionRequest`):

- `supplier_id`: required int exists suppliers.id
- `do_number`: required string max 255
- `stock_in_at`: required date
- `pic_user_id`: required int exists users.id
- `remarks`: nullable string

Response: `StockInSessionResource`

### GET `/stock-in-sessions/{stockIn}`

- **Permission**: `stock_in.view`
- Response: `StockInSessionResource` with `items` when loaded

### PUT/PATCH `/stock-in-sessions/{stockIn}`

- **Permission**: `stock_in.edit_draft`
- Only draft sessions can be modified (otherwise `400` business logic error).

Body (from `UpdateStockInSessionRequest`):

- `supplier_id`: sometimes int exists
- `do_number`: sometimes string max 255
- `stock_in_at`: sometimes date
- `pic_user_id`: sometimes int exists
- `remarks`: nullable string

Response: `StockInSessionResource`

### GET `/stock-in-sessions/{stockIn}/review`

- **Permission**: `stock_in.view`
- Response: `StockInSessionResource` (review payload)

### POST `/stock-in-sessions/{stockIn}/finalize`

- **Permission**: `stock_in.confirm`

Response `data`:

- `session`: `StockInSessionResource` (finalized)
- `created_lots_count`: number
- `created_lots`: `LotResource[]` (see `app/Http/Resources/Api/V1/StockIn/LotResource.php`)

Error cases:

- `400`: session not draft, or session has no items, or duplicate lot number exists

### Stock-in items

#### GET `/stock-in-sessions/{stockIn}/items`

- **Permission**: `stock_in.view`

Response item fields (from `StockInItemResource`):

- `id`, `stock_in_id`, `product_id`, `product?{id,ref_num,product_name}`,
  `lot_id`, `lot?{id,lot_number,status}`,
  `scanned_lot_number`, `supplier_batch_code`, `expiry_date`,
  `lot_entry_mode` (`scan|manual`), `expiry_entry_mode` (`scan|manual`),
  `missing_lot_flag` (bool), `source_barcode`, `entry_override_reason`, `remarks`,
  `created_at`, `updated_at`

#### POST `/stock-in-sessions/{stockIn}/items`

- **Permission**: `stock_in.edit_draft`

Body (from `StoreStockInItemRequest`):

- `product_id`: required int exists products.id
- `scanned_lot_number`: nullable string max 255, **required_without** `missing_lot_flag`
- `supplier_batch_code`: required string max 255
- `expiry_date`: nullable date
- `lot_entry_mode`: sometimes in `scan|manual`
- `expiry_entry_mode`: sometimes in `scan|manual`
- `missing_lot_flag`: sometimes boolean (defaults to false if missing)
- `source_barcode`: nullable string
- `entry_override_reason`: nullable string, **required if**:
  - `missing_lot_flag=true` OR `lot_entry_mode=manual` OR `expiry_entry_mode=manual`
- `remarks`: nullable string

Response: `StockInItemResource`

#### PUT/PATCH `/stock-in-sessions/{stockIn}/items/{stockInItem}`

- **Permission**: `stock_in.edit_draft`
- Item must belong to session (otherwise `404`).

Body (from `UpdateStockInItemRequest`): same fields as store but mostly `sometimes`.

Response: `StockInItemResource`

#### DELETE `/stock-in-sessions/{stockIn}/items/{stockInItem}`

- **Permission**: `stock_in.edit_draft`
- Response `data`: `null`

#### PATCH `/stock-in-sessions/{stockIn}/items/{stockInItem}/correct` (admin correction)

- **Permission**: `stock_in.correct_confirmed`
- Corrects immutable fields after finalization. Service enforces “at least one field present”.

Body (from `CorrectStockInItemRequest`):

- `lot_number`: sometimes string max 255
- `supplier_batch_code`: sometimes string max 255
- `expiry_date`: sometimes nullable date
- `admin_reason`: **required** string min 5 max 1000

Response: `StockInItemResource`

---

## Inventory module

All endpoints:

- **Auth**: required
- **Permission**: `stock_in.view`

### GET `/inventory-units`

Query params (documented in controller):

- `status`: available | supplied | used | disposed | holding | returned_to_supplier
- `supplier_id`: int
- `product_id`: int
- `instrument_set_id`: int
- `expiry_from`: `YYYY-MM-DD` (inclusive)
- `expiry_to`: `YYYY-MM-DD` (inclusive)
- `search`: matches lot_number, supplier_batch_code, product ref_num or name
- `per_page`: default 15, max 100
- `cursor`: if provided, backend returns **cursor pagination** instead of page pagination

Response item fields (from `InventoryUnitResource`):

- `id`, `lot_number`, `original_lot_number`, `is_system_generated_lot`, `supplier_batch_code`,
  `expiry_date` (Y-m-d|null), `status`, `current_location_type`, `current_location_id`,
  `remarks`, `received_at`, `created_at`, `updated_at`
- `product?{id,ref_num,product_name,product_type,uom}`
- `supplier?{id,supplier_name}`
- `qr_label?{id,qr_payload,generated_at}`
- `lot_movements_count` (count only on list)

### GET `/inventory-units/summary`

Response `data` (from `InventoryService::summary()`):

- `total`: int
- `available`, `supplied`, `used`, `disposed`, `holding`: int

### GET `/inventory-units/expiring-soon`

Query:

- `days`: int (default 30, max 365)
- `status`: optional
- `supplier_id`: optional int
- `product_id`: optional int
- `per_page`: default 15 max 100

Response: paginated `InventoryUnitResource[]`

### GET `/inventory-units/lookup/by-lot/{lotNumber}`

- Exact match by `lots.lot_number`
- `404` if not found

Response: `InventoryUnitResource` (includes `qr_label` and `lot_holding` when loaded by service)

### GET `/inventory-units/lookup/by-ref/{refNum}`

Response: `InventoryUnitResource[]`

### GET `/inventory-units/{lot}`

Response: `InventoryUnitResource` with more relations:

- `instrument_set` (if loaded)
- `qr_label`
- `lot_holding`
- `lot_movements_count`

### GET `/inventory-units/{lot}/movements`

Query:

- `movement_type`: string (e.g. stock_in, consigned, returned, used, disposed, returned_to_supplier, holding_released)
- `from_date`: `YYYY-MM-DD`
- `to_date`: `YYYY-MM-DD`
- `per_page`: default 15 max 100

Response item fields (from `LotMovementResource`):

- `id`, `lot_id`, `movement_type`, `reference_type`, `reference_id`,
  `from_status`, `to_status`, `from_location_type`, `from_location_id`, `to_location_type`, `to_location_id`,
  `performed_at`, `remarks`, `created_at`
- `performed_by_user?{id,full_name,email}`
- `lot?{id,lot_number,status,product:{id,ref_num,product_name}}`

### GET `/inventory-ledger`

Query:

- `lot_id`: int
- `lot_number`: exact match
- `movement_type`: string
- `from_date`: `YYYY-MM-DD`
- `to_date`: `YYYY-MM-DD`
- `per_page`: default 15 max 100

Response: paginated `LotMovementResource[]`

---

## QR Labels module

All endpoints:

- **Auth**: required
- **Permission**: `stock_in.view`

### GET `/qr-labels/{lot}`

Idempotent: creates persisted `qr_labels` row if missing.

Response fields (from `QrLabelResource`):

- `id`, `lot_id`, `qr_payload`, `generated_at`, `generated_by_user_id`, `created_at`
- `lot?{id,lot_number,supplier_batch_code,expiry_date,status}` when relation loaded

### GET `/qr-labels/{lot}/preview`

No persistence. Response `data`:

- `lot_id`, `lot_number`, `qr_payload`, `tspl_payload`

Canonical payload format (from `QrPayloadService`):

- `V=1;REF={RefNum};LOT={LotNumber};BATCH={SupplierBatchCode|-};EXP={YYYY-MM-DD|-}`

---

## Print Jobs module (mobile printing)

All endpoints:

- **Auth**: required
- **Permission**: `stock_in.view`

### GET `/print-jobs`

Query (from controller):

- `status`: queued | printed | failed
- `action_type`: print | reprint
- `lot_id`: int
- `device_id`: string (mobile BLE device identifier)
- `from_date`: `YYYY-MM-DD`
- `to_date`: `YYYY-MM-DD`
- `per_page`: default 15 max 100

Response fields (from `PrintJobResource`):

- `id`, `lot_id`, `qr_label_id`, `action_type`, `reprint_reason`, `status`,
  `printer_name`, `device_id`, `tspl_payload`, `error_message`,
  `requested_by_user_id`, `requested_at`, `printed_at`, `failed_at`, `created_at`
- `lot?{id,lot_number,product?{id,ref_num,product_name}}`
- `requested_by?{id,full_name}`

### GET `/print-jobs/{printJob}`

Response: `PrintJobResource` (includes `tspl_payload`)

### POST `/print-jobs`

Body (from `CreatePrintJobRequest`):

- `lot_id`: required int exists lots.id
- `printer_name`: nullable string max 255
- `device_id`: nullable string max 255

Response: `PrintJobResource`

### POST `/print-jobs/reprint`

Body (from `ReprintRequest`):

- `lot_id`: required int exists lots.id
- `reason`: required string min 5 max 1000
- `printer_name`: nullable string max 255
- `device_id`: nullable string max 255

Response: `PrintJobResource` (action_type = `reprint`)

### PATCH `/print-jobs/{printJob}/mark-printed`

Body (from `MarkPrintedRequest`):

- `printer_name`: nullable string max 255

Response: `PrintJobResource` (status = `printed`)

### PATCH `/print-jobs/{printJob}/mark-failed`

Body (from `MarkFailedRequest`):

- `error_message`: required string max 2000

Response: `PrintJobResource` (status = `failed`)

---

## Consignments module (stock-out)

### Consignment lifecycle

- `draft` consignments can be edited and have items added/removed.
- Confirm is atomic:
  - all linked lots must be in a valid state (service enforces)
  - lots become `supplied` and location updates to client
  - movements recorded
- Post-confirm edit exists and requires a mandatory `reason`.

### GET `/consignments`

- **Permission**: `consignments.view`
- Query:
  - `search` (consignment_no)
  - `status`
  - `client_id`
  - `from_date`
  - `to_date`
  - `per_page` (default 15, max 100)

Response fields (from `ConsignmentResource`):

- `id`, `consignment_no`, `status`, `client_id`, `client?{id,client_name}`,
  `consignment_at`, `pic_user_id`, `pic_user?{id,full_name}`, `remarks`,
  `confirmed_at`, `confirmed_by_user_id`, `confirmed_by_user?{id,full_name}`,
  `edited_after_confirmation`, `last_post_confirm_edit_at`, `last_post_confirm_edit_by_user_id`,
  `last_post_confirm_edit_by_user?{id,full_name}`, `last_post_confirm_edit_reason`,
  `items_count`, `items` (optional), `created_at`, `updated_at`

### POST `/consignments`

- **Permission**: `consignments.create`

Body (from `StoreConsignmentRequest`):

- `client_id`: required int exists clients.id
- `consignment_at`: required date
- `pic_user_id`: required int exists users.id
- `remarks`: nullable string

Response: `ConsignmentResource`

### GET `/consignments/{consignment}`

- **Permission**: `consignments.view`
- Response: `ConsignmentResource` (with items when loaded)

### PUT/PATCH `/consignments/{consignment}`

- **Permission**: `consignments.edit_draft`
- Body (from `UpdateConsignmentRequest`):
  - `client_id`: sometimes int exists
  - `consignment_at`: sometimes date
  - `pic_user_id`: sometimes int exists
  - `remarks`: nullable string

Response: `ConsignmentResource`

### GET `/consignments/{consignment}/review`

- **Permission**: `consignments.view`
- Response: `ConsignmentResource`

### POST `/consignments/{consignment}/confirm`

- **Permission**: `consignments.confirm`
- Response: `ConsignmentResource` (confirmed)

### PUT `/consignments/{consignment}/post-confirm-edit`

- **Permission**: `consignments.edit_confirmed`

Body (from `PostConfirmEditRequest`):

- `reason`: required string min 5 max 1000
- `remarks`: nullable string

Response: `ConsignmentResource`

### Consignment items

#### GET `/consignments/{consignment}/items`

- **Permission**: `consignments.view`

Response fields (from `ConsignmentItemResource`):

- `id`, `consignment_id`, `lot_id`,
  `lot?{id,lot_number,status,expiry_date,product?{id,ref_num,product_name}}`,
  `issued_at`, `issued_by_user_id`, `remarks`, `created_at`

#### POST `/consignments/{consignment}/items`

- **Permission**: `consignments.edit_draft`

Body (from `StoreConsignmentItemRequest`):

- `lot_id`: required int exists lots.id
- `remarks`: nullable string

Response: `ConsignmentItemResource`

#### DELETE `/consignments/{consignment}/items/{consignmentItem}`

- **Permission**: `consignments.edit_draft`
- Response `data`: `null`

---

## Return Sessions module

### GET `/return-sessions`

- **Permission**: `returns.view`
- Query:
  - `status`
  - `consignment_id`
  - `from_date`
  - `to_date`
  - `per_page` (default 15, max 100)

Response fields (from `ReturnSessionResource`):

- `id`, `return_session_no`, `status`,
  `consignment_id`, `consignment?{id,consignment_no}`,
  `pic_user_id`, `pic_user?{id,full_name}`,
  `remarks`, `started_at`, `completed_at`,
  `completed_by_user_id`, `completed_by_user?{id,full_name}`,
  `items_count`, `items` (optional),
  `created_at`, `updated_at`

### POST `/return-sessions`

- **Permission**: `returns.create`

Body (from `StoreReturnSessionRequest`):

- `consignment_id`: required int exists consignments.id
- `pic_user_id`: required int exists users.id
- `remarks`: nullable string

Response: `ReturnSessionResource`

### GET `/return-sessions/{returnSession}`

- **Permission**: `returns.view`
- Response: `ReturnSessionResource` (with items when loaded)

### POST `/return-sessions/{returnSession}/scan`

- **Permission**: `returns.create`

Body (from `ScanReturnItemRequest`):

- `lot_id`: nullable int exists lots.id
- `lot_number`: nullable string max 255
- `source_qr_payload`: nullable string
- `remarks`: nullable string

Validation rule: **either `lot_id` or `lot_number` must be provided**.

Response: `ReturnSessionItemResource`

Response fields (from `ReturnSessionItemResource`):

- `id`, `return_session_id`, `lot_id`,
  `lot?{id,lot_number,status,expiry_date,product?{id,ref_num,product_name}}`,
  `returned_at`, `returned_by_user_id`,
  `source_qr_payload`, `remarks`, `created_at`

### DELETE `/return-sessions/{returnSession}/items/{returnSessionItem}`

- **Permission**: `returns.create`
- Response `data`: `null`

### POST `/return-sessions/{returnSession}/complete`

- **Permission**: `returns.finalize`
- Response: `ReturnSessionResource`

---

## Reconciliations module

### What “finalize” does (high-level)

From `ReconciliationFinalizeService`:

- Allowed statuses: `pending` or `reopened`
- Computes: `used = consigned - returned`
- For each **returned** lot:
  - movement `returned`
  - lot status → `available`
  - location → `warehouse`
- For each **used** lot:
  - movement `used`
  - lot status → `used` (terminal)
- Creates `reconciliation_items` with `result = returned|used`
- Marks reconciliation finalized and **auto-generates usage summary**

### GET `/reconciliations`

- **Permission**: `returns.view`
- Query:
  - `search`
  - `status`
  - `consignment_id`
  - `from_date`
  - `to_date`
  - `per_page` (default 15, max 100)

Response fields (from `ReconciliationResource`):

- `id`, `reconciliation_no`, `status`,
  `consignment_id`, `consignment?{id,consignment_no}`,
  `return_session_id`, `return_session?{id,return_session_no}`,
  `pic_user_id`, `pic_user?{id,full_name}`,
  `remarks`,
  `completed_at`, `completed_by_user_id`, `completed_by_user?{id,full_name}`,
  `reopened_at`, `reopened_by_user_id`, `reopened_by_user?{id,full_name}`,
  `reopen_reason`,
  `summary?{total_consigned,total_returned,total_used}` (only computed when items loaded),
  `items_count`, `items` (optional),
  `created_at`, `updated_at`

### POST `/reconciliations`

- **Permission**: `returns.finalize`

Body (from `StoreReconciliationRequest`):

- `return_session_id`: required int exists return_sessions.id
- `pic_user_id`: required int exists users.id
- `remarks`: nullable string max 1000

Response: `ReconciliationResource`

### GET `/reconciliations/{reconciliation}`

- **Permission**: `returns.view`
- Response: `ReconciliationResource` (with items when loaded)

### POST `/reconciliations/{reconciliation}/finalize`

- **Permission**: `returns.finalize`

Body (from `FinalizeReconciliationRequest`):

- `remarks`: nullable string max 1000

Response: `ReconciliationResource` (finalized)

### POST `/reconciliations/{reconciliation}/reopen`

- **Permission**: `returns.reopen_reconciliation`

Body (from `ReopenReconciliationRequest`):

- `reopen_reason`: required string min 5 max 1000

Effect (from `ReconciliationReopenService`):

- Only allowed when status is `finalized`
- Reverts “used” lots back to `supplied`
- Deletes reconciliation items
- Marks reconciliation as `reopened`

Response: `ReconciliationResource`

---

## Disposal module

### GET `/disposals`

- **Permission**: `disposals.view`
- Query:
  - `search`, `status`, `from_date`, `to_date`, `per_page`

Response: paginated `DisposalResource[]`

`DisposalResource` fields:

- `id`, `disposal_no`, `status`, `disposed_at`, `remarks`,
  `pic_user_id`, `pic_user?{id,full_name}`,
  `completed_at`, `completed_by_user_id`, `completed_by_user?{id,full_name}`,
  `disposal_items_count`, `disposal_items` (optional),
  `created_at`, `updated_at`

### POST `/disposals`

- **Permission**: `disposals.create`

Body (from `StoreDisposalRequest`):

- `disposed_at`: required date
- `pic_user_id`: required int exists users.id
- `remarks`: nullable string max 1000

Response: `DisposalResource`

### GET `/disposals/{disposal}`

- **Permission**: `disposals.view`
- Response: `DisposalResource` (with items when loaded)

### PUT/PATCH `/disposals/{disposal}`

- **Permission**: `disposals.create`

Body (from `UpdateDisposalRequest`):

- `disposed_at`: sometimes date
- `pic_user_id`: sometimes int exists users.id
- `remarks`: nullable string max 1000

Response: `DisposalResource`

### Items

#### GET `/disposals/{disposal}/items`

- **Permission**: `disposals.view`
- Response: `DisposalItemResource[]`

`DisposalItemResource` fields:

- `id`, `disposal_id`, `lot_id`,
  `lot?{id,lot_number,supplier_batch_code,expiry_date,status,product?,supplier?}`,
  `disposal_category` (`expired|damaged|lost|other`),
  `reason_text`, `remarks`, `created_at`

#### POST `/disposals/{disposal}/items`

- **Permission**: `disposals.create`

Body (from `StoreDisposalItemRequest`):

- `lot_id`: required int exists lots.id
- `disposal_category`: required in `expired|damaged|lost|other`
- `reason_text`: required string max 500
- `remarks`: nullable string max 1000

Response: `DisposalItemResource`

#### DELETE `/disposals/{disposal}/items/{disposalItem}`

- **Permission**: `disposals.create`
- Response `data`: `null`

### POST `/disposals/{disposal}/complete`

- **Permission**: `disposals.create`

Effect (from `DisposalCompleteService`):

- Only draft disposals
- Must have at least one item
- Each lot status becomes `disposed` + movement recorded
- Disposal becomes `completed`

Response: `DisposalResource`

---

## Supplier Returns module

### GET `/supplier-returns`

- **Permission**: `disposals.view` (as wired in routes)  
  (Note: creation endpoints require `supplier_returns.create`.)
- Query: `search`, `status`, `supplier_id`, `from_date`, `to_date`, `per_page`

Response: paginated `SupplierReturnResource[]`

`SupplierReturnResource` fields:

- `id`, `supplier_return_no`, `status`,
  `supplier_id`, `supplier?{id,supplier_name}`,
  `returned_at`, `reference_no`, `remarks`,
  `pic_user_id`, `pic_user?{id,full_name}`,
  `completed_at`, `completed_by_user_id`, `completed_by_user?{id,full_name}`,
  `supplier_return_items_count`, `supplier_return_items` (optional),
  `created_at`, `updated_at`

### POST `/supplier-returns`

- **Permission**: `supplier_returns.create`

Body (from `StoreSupplierReturnRequest`):

- `supplier_id`: required int exists suppliers.id
- `returned_at`: required date
- `pic_user_id`: required int exists users.id
- `reference_no`: nullable string max 100
- `remarks`: nullable string max 1000

Response: `SupplierReturnResource`

### GET `/supplier-returns/{supplierReturn}`

- **Permission**: `disposals.view` (as wired in routes)
- Response: `SupplierReturnResource` (with items when loaded)

### PUT/PATCH `/supplier-returns/{supplierReturn}`

- **Permission**: `supplier_returns.create`

Body (from `UpdateSupplierReturnRequest`):

- `returned_at`: sometimes date
- `pic_user_id`: sometimes int exists users.id
- `reference_no`: nullable string max 100
- `remarks`: nullable string max 1000

Response: `SupplierReturnResource`

### Items

#### GET `/supplier-returns/{supplierReturn}/items`

- **Permission**: `disposals.view` (as wired in routes)
- Response: `SupplierReturnItemResource[]`

`SupplierReturnItemResource` fields:

- `id`, `supplier_return_id`, `lot_id`,
  `lot?{id,lot_number,supplier_batch_code,expiry_date,status,product?,supplier?}`,
  `return_reason`, `remarks`, `created_at`

#### POST `/supplier-returns/{supplierReturn}/items`

- **Permission**: `supplier_returns.create`

Body (from `StoreSupplierReturnItemRequest`):

- `lot_id`: required int exists lots.id
- `return_reason`: required string max 500
- `remarks`: nullable string max 1000

Response: `SupplierReturnItemResource`

#### DELETE `/supplier-returns/{supplierReturn}/items/{supplierReturnItem}`

- **Permission**: `supplier_returns.create`
- Response `data`: `null`

### POST `/supplier-returns/{supplierReturn}/complete`

- **Permission**: `supplier_returns.create`

Effect (from `SupplierReturnCompleteService`):

- Only draft supplier returns
- Must have at least one item
- Each lot status becomes `returned_to_supplier` + movement recorded
- Supplier return becomes `completed`

Response: `SupplierReturnResource`

---

## Holding Area module

Holding area is specifically for lots with `status = "holding"` created by stock-in finalization when the lot number is missing.

### GET `/holding-area`

- **Permission**: `holding_area.view`
- Query:
  - `search` (lot_number or supplier_batch_code substring)
  - `supplier_id`
  - `product_id`
  - `from_date` (received_at >=)
  - `to_date` (received_at <=)
  - `per_page` (default 15, max 100)

Response: paginated `HoldingAreaResource[]`

`HoldingAreaResource` fields:

- `id`, `lot_number`, `original_lot_number`, `is_system_generated_lot`,
  `supplier_batch_code`, `expiry_date`, `status`, `received_at`, `remarks`,
  `product?{id,ref_num,product_name}`, `supplier?{id,supplier_name}`,
  `lot_holding?{ id, holding_reason, assigned_at, assigned_by_user?, released_at, released_by_user_id, corrected_lot_number, resolution_reason, remarks }`,
  `created_at`, `updated_at`

### GET `/holding-area/{lot}`

- **Permission**: `holding_area.view`
- If lot is not holding → `422` `"This unit is not in holding status."`
- Response: `HoldingAreaResource`

### POST `/holding-area/{lot}/assign-lot`

- **Permission**: `holding_area.assign_lot`
- If lot is not holding → `422`

Body (from `AssignLotRequest`):

- `lot_number`: required string max 100, unique lots.lot_number (ignoring current lot)
- `resolution_reason`: required string max 500
- `remarks`: nullable string max 1000

Effects (from `HoldingAreaService::assignLot()`):

- lot_number changed from `HOLD-*` to real value
- status → `available`
- closes lot_holding record, creates movement `holding_released`
- QR label payload regenerated
- a new print job is queued

Response: `HoldingAreaResource`

---

## Reporting module

All endpoints:

- **Auth**: required
- **Permission**: `reports.view` (for GET), `reports.export` (for export)

### GET `/reports/stock-in`

Query filters:

- `from_date`, `to_date` (received_at date)
- `supplier_id`
- `product_id`

Response `data`:

- `summary`: aggregates (counts by status/supplier/product)
- `data`: list of matching `lots` with relations loaded

### GET `/reports/consignments`

Query filters:

- `from_date`, `to_date`
- `client_id`
- `product_id`
- `status`

### GET `/reports/returns-analysis`

Query filters:

- `from_date`, `to_date`
- `client_id`

### GET `/reports/disposals`

Query filters:

- `from_date`, `to_date`
- `supplier_id`
- `product_id`
- `disposal_category`

### GET `/reports/expiry`

Query filters:

- `supplier_id`
- `product_id`
- `window` (optional int; must be one of `30|60|90`)

### POST `/reports/{type}/export`

- **Permission**: `reports.export`
- `type`: `stock-in | consignments | returns-analysis | disposals | expiry`
- `format`: `csv | xlsx | pdf` (default `xlsx`)
- plus any report-specific filters

Response: file download (not JSON).

---

## Usage Summary + ERP Push module

### GET `/usage-summaries`

- **Permission**: `usage_summary.view`
- Query:
  - `status`
  - `from_date` (generated_at >=)
  - `to_date` (generated_at <=)
  - `per_page` (default 15, max 100)

Response: paginated `UsageSummaryResource[]`

`UsageSummaryResource` fields:

- `id`, `summary_no`, `status`, `generated_at`,
  `generated_by?{id,full_name}` (when loaded),
  `reconciliation?{id,reconciliation_no,status,consignment_no,client_name}` (when loaded),
  `items_count`,
  `items` (only when show endpoint loads items),
  `created_at`, `updated_at`

### GET `/usage-summaries/{usageSummary}`

- **Permission**: `usage_summary.view`
- Response: `UsageSummaryResource` with items loaded

### POST `/usage-summaries/generate`

- **Permission**: `usage_summary.generate`

Body (from `GenerateUsageSummaryRequest`):

- `reconciliation_id`: required int, exists reconciliations.id **where status = finalized**

Response: `UsageSummaryResource` (`201`)

### POST `/usage-summaries/{usageSummary}/push`

- **Permission**: `usage_summary.generate`

Effect:

- dispatches a queue job to push to ERP
- returns `{ status: "push_pending" }`

### GET `/usage-summaries/{usageSummary}/push-logs`

- **Permission**: `usage_summary.view_logs`

Response `data`: array of:

- `id`, `status`, `http_status_code`, `pushed_at`, `next_retry_at`, `retry_count`, `error_message`, `pushed_by`

### POST `/usage-summaries/{usageSummary}/export`

- **Permission**: `usage_summary.view`
- Query: `format=csv|xlsx|pdf`
- Response: file download.

---

## Audit & Error Logs module (system management)

All endpoints:

- **Auth**: required
- **Permission**: `system.manage_roles` (as wired in routes)

### GET `/audit-logs`

Supports both `page` pagination and optional `cursor` pagination.

Query (from `ListAuditLogsRequest`):

- `page` (int >=1)
- `per_page` (int 1..100, default 20)
- `user_id` (exists users.id)
- `auditable_type` (string max 200; e.g. `"App\\Models\\Lot"`)
- `auditable_id` (int)
- `action_type` (string max 100)
- `ip_address` (string max 45)
- `device_id` (string max 200)
- `from_date` (Y-m-d)
- `to_date` (Y-m-d, >= from_date)
- `cursor` (string; enables cursor pagination)

Response item fields (from `AuditLogResource`):

- `id`, `action_type`, `description`,
  `auditable_type`, `auditable_id`,
  `user_id`, `role_code_snapshot`,
  `ip_address`, `device_id`,
  `before_json`, `after_json`,
  `server_timestamp`, `created_at`,
  `user?{id,full_name,email}` (when loaded)

### GET `/audit-logs/{id}`

Response: `AuditLogResource`

### GET `/error-logs`

Query (from `ListErrorLogsRequest`):

- `page`, `per_page` (default 20, max 100)
- `source` (string max 200)
- `source_id` (int)
- `from_date` (Y-m-d)
- `to_date` (Y-m-d, >= from_date)

Response item fields (from `ErrorLogResource`):

- `id`, `source`, `source_id`, `message`, `details`, `created_at`

### GET `/error-logs/{id}`

Response: `ErrorLogResource`

---

## Scheduler + background jobs (ops / mobile integrations)

### Scheduled commands

Configured in `routes/console.php`:

- `tretech:check-expiry` — daily at `08:00`
- `tretech:retry-failed-pushes` — every 15 minutes

### Queue work

Queue is used for ERP push (`PushUsageSummaryJob`).

For non-local environments you must run:

- `php artisan queue:work`
- `php artisan schedule:work` (or server cron for `schedule:run`)

---

## Endpoint registry (single source list)

For the definitive list of endpoints + permissions + handlers, see:

- `doc/BACKEND_DOCUMENTATION.md` → section “Complete API Endpoint Registry”
- `routes/api.php` (code)

