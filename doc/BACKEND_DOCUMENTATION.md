# TRETECH Backend Documentation

Last updated: 2026-04-10

## 1. Overview

TRETECH backend is a Laravel 13 REST API for healthcare inventory lifecycle management.

Main business modules:
- Authentication and RBAC
- Master data (users, products, suppliers, clients, instrument sets)
- Stock-in and inventory tracking
- QR label generation and print jobs
- Consignment, return sessions, reconciliation
- Disposal and supplier returns
- Holding area handling for missing lot numbers
- Reporting and export
- Usage summary generation and ERP push
- Audit and error logs

## 2. Technology Stack

- PHP: `^8.3`
- Framework: `laravel/framework ^13.0`
- Auth: `laravel/sanctum ^4.3`
- Export: `maatwebsite/excel ^3.1`
- PDF: `barryvdh/laravel-dompdf ^3.1`
- Queue: Laravel queue (`database` driver by default)
- Frontend tooling (optional for local dev): Vite + Tailwind

## 3. Project Structure

Core backend directories:
- `app/Http/Controllers/Api/V1` - API controllers per module
- `app/Services` - business logic and transactional workflows
- `app/Http/Requests/Api/V1` - request validation
- `app/Http/Resources/Api/V1` - response resource transformers
- `app/Models` - Eloquent models and relationships
- `app/Enums` - lifecycle/status constants
- `app/Jobs` - async jobs (`PushUsageSummaryJob`)
- `app/Console/Commands` - custom CLI commands
- `database/migrations` - schema
- `database/seeders` - roles, permissions, users, sample/UAT data
- `routes/api.php` - all API routes under `/api/v1`

## 4. Local Setup

### 4.1 Prerequisites

- PHP 8.3+
- Composer
- Node.js + npm
- MySQL or SQLite

### 4.2 Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
```

Optional frontend assets:

```bash
npm install
npm run build
```

### 4.3 Run in Development

Single command (Composer script):

```bash
composer run dev
```

This starts:
- API server (`php artisan serve`)
- queue listener (`php artisan queue:listen --tries=1 --timeout=0`)
- log tail (`php artisan pail --timeout=0`)
- vite dev server (`npm run dev`)

Alternative minimal backend run:

```bash
php artisan serve
php artisan queue:work
```

## 5. Configuration

Core environment keys used by the backend:

### 5.1 Application and bootstrap
- `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL`
- `APP_LOCALE`, `APP_FALLBACK_LOCALE`, `APP_FAKER_LOCALE`

### 5.2 Admin seeding
- `ADMIN_EMAIL`
- `ADMIN_NAME`
- `ADMIN_PASSWORD`

### 5.3 Database and cache
- `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `CACHE_STORE`

### 5.4 Session, Sanctum, auth
- `SESSION_DRIVER`, `SESSION_LIFETIME`, `SESSION_PATH`, `SESSION_DOMAIN`
- `SANCTUM_STATEFUL_DOMAINS`, `SANCTUM_TOKEN_PREFIX`

### 5.5 Queue and scheduler
- `QUEUE_CONNECTION`
- `DB_QUEUE_TABLE`, `DB_QUEUE_RETRY_AFTER`

### 5.6 CORS
- `CORS_ALLOWED_ORIGINS` (comma-separated)

### 5.7 UAT
- `UAT_STAFF_PASSWORD`

## 6. API Conventions

### 6.1 Base URL and versioning

- Base path: `/api/v1`
- Every response includes headers from `ApiBaseMiddleware`:
  - `X-API-Version: 1.0`
  - `X-Request-ID: <generated-or-forwarded>`

### 6.2 Standard response envelope

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

Paginated:

```json
{
  "success": true,
  "message": "Success",
  "status_code": 200,
  "data": [],
  "pagination": {
    "total": 0,
    "per_page": 15,
    "current_page": 1,
    "last_page": 1,
    "from": 1,
    "to": 0
  },
  "timestamp": "2026-04-10T00:00:00Z"
}
```

### 6.3 Common status codes

- `200` OK
- `201` Created
- `401` Unauthenticated
- `403` Forbidden
- `404` Not found
- `409` Conflict
- `422` Validation failed
- `429` Throttled (login limiter)
- `500` Server error

## 7. Authentication and Authorization

### 7.1 Authentication

- Token auth uses Laravel Sanctum bearer tokens.
- Login endpoint: `POST /api/v1/auth/login`
- Login is rate-limited by `throttle:login` (5 attempts / 15 minutes per IP).
- Optional request header used by auth and audit context: `X-Device-Id`.

### 7.2 Role and permission model

RBAC tables:
- `roles`
- `permissions`
- `role_permissions`

Seeded roles:
- `admin`
- `logistic_staff`

Route-level authorization uses middleware:
- `permission:<code>` (any listed permission in comma-separated list)
- `all-permissions:<code1>,<code2>` (all required, alias exists)

## 8. Business Workflows and Status Lifecycles

### 8.1 Stock-In

- Session starts in `draft`.
- Finalization requires at least one item.
- Finalization creates lots and `lot_movements`.
- Missing lot input generates a `HOLD-...` lot and creates `lot_holdings` entry.
- Finalization also creates initial print jobs.

`stock_ins.status`: `draft -> finalized` (or `cancelled`)

### 8.2 Lot lifecycle

`lots.status` values:
- `available`
- `supplied`
- `used`
- `disposed`
- `holding`
- `returned_to_supplier`

### 8.3 Consignment

- Draft consignment can be edited.
- Confirm requires all linked lots to be `available`.
- On confirm, lots become `supplied` and location updates to client.

`consignments.status`: `draft -> confirmed` (or `cancelled`)

### 8.4 Return Session and Reconciliation

- Return session is linked to a confirmed consignment.
- Completion marks return session `completed`.
- Reconciliation finalization computes:
  - `used = consigned - returned`
- Finalize transitions lots:
  - returned lots -> `available`
  - unreturned lots -> `used`
- Finalize auto-generates usage summary.

`return_sessions.status`: `in_progress -> completed`

`reconciliations.status`: `pending -> finalized -> reopened -> finalized`

### 8.5 Disposal and Supplier Return

- Both modules use draft header + items, then atomic completion.
- Disposal completion sets lots to `disposed`.
- Supplier return completion sets lots to `returned_to_supplier`.

`disposals.status`: `draft -> completed`

`supplier_returns.status`: `draft -> completed`

### 8.6 Holding Area

- Holds lots with missing/invalid lot data.
- Assigning a real lot number releases the hold and returns lot to available flow.

### 8.7 Usage Summary and ERP Push

- Usage summary generated from finalized reconciliation.
- Push endpoint dispatches queue job.
- Push statuses:
  - `generated`
  - `push_pending`
  - `pushed`
  - `push_failed`
- Retry strategy uses push logs and scheduled retry command.

## 9. Request Payload Reference (Key Endpoints)

### 9.1 Auth
- `POST /auth/login`
  - Required: `email`, `password`
  - Optional: `device_name`

### 9.2 Master data
- Product create/update: `ref_num`, `product_name`, `product_type`, `category`, `uom`, flags
- Supplier create/update: `supplier_name`, `phone`, `email`, `address`, `is_active`
- Client create/update: `client_name`, `client_type`, contact fields, `is_active`
- Instrument set create/update: `set_code`, `set_name`, `description`, `is_active`
- User create/update: `role_id`, `full_name`, `email`, `password`, `is_active`

### 9.3 Stock-in
- Session create: `supplier_id`, `do_number`, `stock_in_at`, `pic_user_id`, `remarks`
- Item create: `product_id`, `manufacturing_date`, with lot/expiry capture fields
- Admin correction: at least one corrected field + mandatory `admin_reason`

### 9.4 Consignment and return
- Consignment create: `client_id`, `consignment_at`, `pic_user_id`
- Consignment item add: `lot_id`
- Post-confirm edit: mandatory `reason`
- Return session create: `consignment_id`, `pic_user_id`
- Return scan: `lot_id` or `lot_number` (at least one required)

### 9.5 Reconciliation
- Create: `return_session_id`, `pic_user_id`
- Reopen: mandatory `reopen_reason`

### 9.6 Disposal and supplier return
- Disposal create: `disposed_at`, `pic_user_id`
- Disposal item: `lot_id`, `disposal_category`, `reason_text`
- Supplier return create: `supplier_id`, `returned_at`, `pic_user_id`
- Supplier return item: `lot_id`, `return_reason`

### 9.7 Holding area
- Assign lot: `lot_number`, `resolution_reason`, `remarks`

### 9.8 Usage summary
- Generate: `reconciliation_id` (must be finalized)

### 9.9 Print jobs
- Create: `lot_id`, optional `printer_name`, `device_id`
- Reprint: `lot_id`, mandatory `reason`, optional printer/device metadata
- Mark failed: mandatory `error_message`

## 10. Scheduled Tasks and Asynchronous Processing

Defined in `routes/console.php`:

- `tretech:check-expiry`
  - Schedule: daily `08:00`
  - Purpose: report lots expiring within 30/60/90 day windows

- `tretech:retry-failed-pushes`
  - Schedule: every 15 minutes
  - Purpose: retries ERP push attempts with `failed_retryable` status

Queue job:
- `PushUsageSummaryJob`
  - tries: `3`
  - backoff: `30`
  - middleware: `ThrottlesExceptions(1,5)->backoff(15)`

## 11. Data Model Summary

Primary entity groups:

- Security and sessions:
  - `users`, `roles`, `permissions`, `role_permissions`, `sessions`, `login_attempts`, `personal_access_tokens`

- Master data:
  - `products`, `suppliers`, `clients`, `instrument_sets`

- Inventory and inbound:
  - `stock_ins`, `stock_in_items`, `lots`, `lot_holdings`, `lot_movements`

- Labels and printing:
  - `qr_labels`, `qr_print_jobs`

- Outbound and return lifecycle:
  - `consignments`, `consignment_items`, `return_sessions`, `return_session_items`, `reconciliations`, `reconciliation_items`

- Disposal and supplier return:
  - `disposals`, `disposal_items`, `supplier_returns`, `supplier_return_items`

- Usage and integration:
  - `usage_summaries`, `usage_summary_items`, `usage_summary_push_logs`

- Governance:
  - `audit_logs`, `error_logs`

## 12. Complete API Endpoint Registry

Generated from `php artisan route:list --path=api --except-vendor --json`.
Total API routes: 123.

### Audit Logs (2)

| Method | Path | Auth | Permission | Handler |
|---|---|---|---|---|
| GET | `/api/v1/audit-logs` | Yes | `system.manage_roles` | `Api\V1\Audit\AuditLogController@index` |
| GET | `/api/v1/audit-logs/{id}` | Yes | `system.manage_roles` | `Api\V1\Audit\AuditLogController@show` |

### Authentication (4)

| Method | Path | Auth | Permission | Handler |
|---|---|---|---|---|
| POST | `/api/v1/auth/login` | No | `-` | `Api\V1\AuthController@login` |
| POST | `/api/v1/auth/logout` | Yes | `-` | `Api\V1\AuthController@logout` |
| GET | `/api/v1/auth/me` | Yes | `-` | `Api\V1\AuthController@me` |
| GET | `/api/v1/auth/permissions` | Yes | `-` | `Api\V1\AuthController@permissions` |

### Consignments (11)

| Method | Path | Auth | Permission | Handler |
|---|---|---|---|---|
| GET | `/api/v1/consignments` | Yes | `consignments.view` | `Api\V1\Consignment\ConsignmentController@index` |
| POST | `/api/v1/consignments` | Yes | `consignments.create` | `Api\V1\Consignment\ConsignmentController@store` |
| GET | `/api/v1/consignments/{consignment}` | Yes | `consignments.view` | `Api\V1\Consignment\ConsignmentController@show` |
| PATCH | `/api/v1/consignments/{consignment}` | Yes | `consignments.edit_draft` | `Api\V1\Consignment\ConsignmentController@update` |
| PUT | `/api/v1/consignments/{consignment}` | Yes | `consignments.edit_draft` | `Api\V1\Consignment\ConsignmentController@update` |
| POST | `/api/v1/consignments/{consignment}/confirm` | Yes | `consignments.confirm` | `Api\V1\Consignment\ConsignmentController@confirm` |
| GET | `/api/v1/consignments/{consignment}/items` | Yes | `consignments.view` | `Api\V1\Consignment\ConsignmentItemController@index` |
| POST | `/api/v1/consignments/{consignment}/items` | Yes | `consignments.edit_draft` | `Api\V1\Consignment\ConsignmentItemController@store` |
| DELETE | `/api/v1/consignments/{consignment}/items/{consignmentItem}` | Yes | `consignments.edit_draft` | `Api\V1\Consignment\ConsignmentItemController@destroy` |
| PUT | `/api/v1/consignments/{consignment}/post-confirm-edit` | Yes | `consignments.edit_confirmed` | `Api\V1\Consignment\ConsignmentController@postConfirmEdit` |
| GET | `/api/v1/consignments/{consignment}/review` | Yes | `consignments.view` | `Api\V1\Consignment\ConsignmentController@review` |

### Disposals (9)

| Method | Path | Auth | Permission | Handler |
|---|---|---|---|---|
| GET | `/api/v1/disposals` | Yes | `disposals.view` | `Api\V1\Disposal\DisposalController@index` |
| POST | `/api/v1/disposals` | Yes | `disposals.create` | `Api\V1\Disposal\DisposalController@store` |
| GET | `/api/v1/disposals/{disposal}` | Yes | `disposals.view` | `Api\V1\Disposal\DisposalController@show` |
| PATCH | `/api/v1/disposals/{disposal}` | Yes | `disposals.create` | `Api\V1\Disposal\DisposalController@update` |
| PUT | `/api/v1/disposals/{disposal}` | Yes | `disposals.create` | `Api\V1\Disposal\DisposalController@update` |
| POST | `/api/v1/disposals/{disposal}/complete` | Yes | `disposals.create` | `Api\V1\Disposal\DisposalController@complete` |
| GET | `/api/v1/disposals/{disposal}/items` | Yes | `disposals.view` | `Api\V1\Disposal\DisposalController@indexItems` |
| POST | `/api/v1/disposals/{disposal}/items` | Yes | `disposals.create` | `Api\V1\Disposal\DisposalController@storeItem` |
| DELETE | `/api/v1/disposals/{disposal}/items/{disposalItem}` | Yes | `disposals.create` | `Api\V1\Disposal\DisposalController@destroyItem` |

### Error Logs (2)

| Method | Path | Auth | Permission | Handler |
|---|---|---|---|---|
| GET | `/api/v1/error-logs` | Yes | `system.manage_roles` | `Api\V1\Audit\ErrorLogController@index` |
| GET | `/api/v1/error-logs/{id}` | Yes | `system.manage_roles` | `Api\V1\Audit\ErrorLogController@show` |

### Holding Area (3)

| Method | Path | Auth | Permission | Handler |
|---|---|---|---|---|
| GET | `/api/v1/holding-area` | Yes | `holding_area.view` | `Api\V1\HoldingArea\HoldingAreaController@index` |
| GET | `/api/v1/holding-area/{lot}` | Yes | `holding_area.view` | `Api\V1\HoldingArea\HoldingAreaController@show` |
| POST | `/api/v1/holding-area/{lot}/assign-lot` | Yes | `holding_area.assign_lot` | `Api\V1\HoldingArea\HoldingAreaController@assignLot` |

### Inventory (8)

| Method | Path | Auth | Permission | Handler |
|---|---|---|---|---|
| GET | `/api/v1/inventory-ledger` | Yes | `stock_in.view` | `Api\V1\Inventory\InventoryController@ledger` |
| GET | `/api/v1/inventory-units` | Yes | `stock_in.view` | `Api\V1\Inventory\InventoryController@index` |
| GET | `/api/v1/inventory-units/{lot}` | Yes | `stock_in.view` | `Api\V1\Inventory\InventoryController@show` |
| GET | `/api/v1/inventory-units/{lot}/movements` | Yes | `stock_in.view` | `Api\V1\Inventory\InventoryController@movements` |
| GET | `/api/v1/inventory-units/expiring-soon` | Yes | `stock_in.view` | `Api\V1\Inventory\InventoryController@expiringSoon` |
| GET | `/api/v1/inventory-units/lookup/by-lot/{lotNumber}` | Yes | `stock_in.view` | `Api\V1\Inventory\InventoryController@lookupByLot` |
| GET | `/api/v1/inventory-units/lookup/by-ref/{refNum}` | Yes | `stock_in.view` | `Api\V1\Inventory\InventoryController@lookupByRef` |
| GET | `/api/v1/inventory-units/summary` | Yes | `stock_in.view` | `Api\V1\Inventory\InventoryController@summary` |

### Master Data (30)

| Method | Path | Auth | Permission | Handler |
|---|---|---|---|---|
| GET | `/api/v1/master-data/clients` | Yes | `clients.view,clients.manage` | `Api\V1\MasterData\ClientController@index` |
| POST | `/api/v1/master-data/clients` | Yes | `clients.manage` | `Api\V1\MasterData\ClientController@store` |
| DELETE | `/api/v1/master-data/clients/{client}` | Yes | `clients.manage` | `Api\V1\MasterData\ClientController@destroy` |
| GET | `/api/v1/master-data/clients/{client}` | Yes | `clients.view,clients.manage` | `Api\V1\MasterData\ClientController@show` |
| PATCH | `/api/v1/master-data/clients/{client}` | Yes | `clients.manage` | `Api\V1\MasterData\ClientController@update` |
| PUT | `/api/v1/master-data/clients/{client}` | Yes | `clients.manage` | `Api\V1\MasterData\ClientController@update` |
| GET | `/api/v1/master-data/instrument-sets` | Yes | `instrument_sets.view,instrument_sets.manage` | `Api\V1\MasterData\InstrumentSetController@index` |
| POST | `/api/v1/master-data/instrument-sets` | Yes | `instrument_sets.manage` | `Api\V1\MasterData\InstrumentSetController@store` |
| DELETE | `/api/v1/master-data/instrument-sets/{instrumentSet}` | Yes | `instrument_sets.manage` | `Api\V1\MasterData\InstrumentSetController@destroy` |
| GET | `/api/v1/master-data/instrument-sets/{instrumentSet}` | Yes | `instrument_sets.view,instrument_sets.manage` | `Api\V1\MasterData\InstrumentSetController@show` |
| PATCH | `/api/v1/master-data/instrument-sets/{instrumentSet}` | Yes | `instrument_sets.manage` | `Api\V1\MasterData\InstrumentSetController@update` |
| PUT | `/api/v1/master-data/instrument-sets/{instrumentSet}` | Yes | `instrument_sets.manage` | `Api\V1\MasterData\InstrumentSetController@update` |
| GET | `/api/v1/master-data/products` | Yes | `products.view` | `Api\V1\MasterData\ProductController@index` |
| POST | `/api/v1/master-data/products` | Yes | `products.create` | `Api\V1\MasterData\ProductController@store` |
| DELETE | `/api/v1/master-data/products/{product}` | Yes | `products.delete` | `Api\V1\MasterData\ProductController@destroy` |
| GET | `/api/v1/master-data/products/{product}` | Yes | `products.view` | `Api\V1\MasterData\ProductController@show` |
| PATCH | `/api/v1/master-data/products/{product}` | Yes | `products.edit` | `Api\V1\MasterData\ProductController@update` |
| PUT | `/api/v1/master-data/products/{product}` | Yes | `products.edit` | `Api\V1\MasterData\ProductController@update` |
| GET | `/api/v1/master-data/suppliers` | Yes | `suppliers.view,suppliers.manage` | `Api\V1\MasterData\SupplierController@index` |
| POST | `/api/v1/master-data/suppliers` | Yes | `suppliers.manage` | `Api\V1\MasterData\SupplierController@store` |
| DELETE | `/api/v1/master-data/suppliers/{supplier}` | Yes | `suppliers.manage` | `Api\V1\MasterData\SupplierController@destroy` |
| GET | `/api/v1/master-data/suppliers/{supplier}` | Yes | `suppliers.view,suppliers.manage` | `Api\V1\MasterData\SupplierController@show` |
| PATCH | `/api/v1/master-data/suppliers/{supplier}` | Yes | `suppliers.manage` | `Api\V1\MasterData\SupplierController@update` |
| PUT | `/api/v1/master-data/suppliers/{supplier}` | Yes | `suppliers.manage` | `Api\V1\MasterData\SupplierController@update` |
| GET | `/api/v1/master-data/users` | Yes | `system.manage_users` | `Api\V1\MasterData\UserController@index` |
| POST | `/api/v1/master-data/users` | Yes | `system.manage_users` | `Api\V1\MasterData\UserController@store` |
| DELETE | `/api/v1/master-data/users/{user}` | Yes | `system.manage_users` | `Api\V1\MasterData\UserController@destroy` |
| GET | `/api/v1/master-data/users/{user}` | Yes | `system.manage_users` | `Api\V1\MasterData\UserController@show` |
| PATCH | `/api/v1/master-data/users/{user}` | Yes | `system.manage_users` | `Api\V1\MasterData\UserController@update` |
| PUT | `/api/v1/master-data/users/{user}` | Yes | `system.manage_users` | `Api\V1\MasterData\UserController@update` |

### Print Jobs (6)

| Method | Path | Auth | Permission | Handler |
|---|---|---|---|---|
| GET | `/api/v1/print-jobs` | Yes | `stock_in.view` | `Api\V1\QrLabel\PrintJobController@index` |
| POST | `/api/v1/print-jobs` | Yes | `stock_in.view` | `Api\V1\QrLabel\PrintJobController@store` |
| GET | `/api/v1/print-jobs/{printJob}` | Yes | `stock_in.view` | `Api\V1\QrLabel\PrintJobController@show` |
| PATCH | `/api/v1/print-jobs/{printJob}/mark-failed` | Yes | `stock_in.view` | `Api\V1\QrLabel\PrintJobController@markFailed` |
| PATCH | `/api/v1/print-jobs/{printJob}/mark-printed` | Yes | `stock_in.view` | `Api\V1\QrLabel\PrintJobController@markPrinted` |
| POST | `/api/v1/print-jobs/reprint` | Yes | `stock_in.view` | `Api\V1\QrLabel\PrintJobController@reprint` |

### QR Labels (2)

| Method | Path | Auth | Permission | Handler |
|---|---|---|---|---|
| GET | `/api/v1/qr-labels/{lot}` | Yes | `stock_in.view` | `Api\V1\QrLabel\QrLabelController@show` |
| GET | `/api/v1/qr-labels/{lot}/preview` | Yes | `stock_in.view` | `Api\V1\QrLabel\QrLabelController@preview` |

### RBAC (1)

| Method | Path | Auth | Permission | Handler |
|---|---|---|---|---|
| GET | `/api/v1/rbac/check` | Yes | `system.manage_roles` | `Closure` |

### Reconciliations (5)

| Method | Path | Auth | Permission | Handler |
|---|---|---|---|---|
| GET | `/api/v1/reconciliations` | Yes | `returns.view` | `Api\V1\Reconciliation\ReconciliationController@index` |
| POST | `/api/v1/reconciliations` | Yes | `returns.finalize` | `Api\V1\Reconciliation\ReconciliationController@store` |
| GET | `/api/v1/reconciliations/{reconciliation}` | Yes | `returns.view` | `Api\V1\Reconciliation\ReconciliationController@show` |
| POST | `/api/v1/reconciliations/{reconciliation}/finalize` | Yes | `returns.finalize` | `Api\V1\Reconciliation\ReconciliationController@finalize` |
| POST | `/api/v1/reconciliations/{reconciliation}/reopen` | Yes | `returns.reopen_reconciliation` | `Api\V1\Reconciliation\ReconciliationController@reopen` |

### Reporting (6)

| Method | Path | Auth | Permission | Handler |
|---|---|---|---|---|
| POST | `/api/v1/reports/{type}/export` | Yes | `reports.export` | `Api\V1\Reporting\ReportController@export` |
| GET | `/api/v1/reports/consignments` | Yes | `reports.view` | `Api\V1\Reporting\ReportController@consignments` |
| GET | `/api/v1/reports/disposals` | Yes | `reports.view` | `Api\V1\Reporting\ReportController@disposals` |
| GET | `/api/v1/reports/expiry` | Yes | `reports.view` | `Api\V1\Reporting\ReportController@expiry` |
| GET | `/api/v1/reports/returns-analysis` | Yes | `reports.view` | `Api\V1\Reporting\ReportController@returnsAnalysis` |
| GET | `/api/v1/reports/stock-in` | Yes | `reports.view` | `Api\V1\Reporting\ReportController@stockIn` |

### Return Sessions (6)

| Method | Path | Auth | Permission | Handler |
|---|---|---|---|---|
| GET | `/api/v1/return-sessions` | Yes | `returns.view` | `Api\V1\ReturnSession\ReturnSessionController@index` |
| POST | `/api/v1/return-sessions` | Yes | `returns.create` | `Api\V1\ReturnSession\ReturnSessionController@store` |
| GET | `/api/v1/return-sessions/{returnSession}` | Yes | `returns.view` | `Api\V1\ReturnSession\ReturnSessionController@show` |
| POST | `/api/v1/return-sessions/{returnSession}/complete` | Yes | `returns.finalize` | `Api\V1\ReturnSession\ReturnSessionController@complete` |
| DELETE | `/api/v1/return-sessions/{returnSession}/items/{returnSessionItem}` | Yes | `returns.create` | `Api\V1\ReturnSession\ReturnSessionController@removeItem` |
| POST | `/api/v1/return-sessions/{returnSession}/scan` | Yes | `returns.create` | `Api\V1\ReturnSession\ReturnSessionController@scan` |

### Stock-In (13)

| Method | Path | Auth | Permission | Handler |
|---|---|---|---|---|
| GET | `/api/v1/stock-in-sessions` | Yes | `stock_in.view` | `Api\V1\StockIn\StockInSessionController@index` |
| POST | `/api/v1/stock-in-sessions` | Yes | `stock_in.create` | `Api\V1\StockIn\StockInSessionController@store` |
| GET | `/api/v1/stock-in-sessions/{stockIn}` | Yes | `stock_in.view` | `Api\V1\StockIn\StockInSessionController@show` |
| PATCH | `/api/v1/stock-in-sessions/{stockIn}` | Yes | `stock_in.edit_draft` | `Api\V1\StockIn\StockInSessionController@update` |
| PUT | `/api/v1/stock-in-sessions/{stockIn}` | Yes | `stock_in.edit_draft` | `Api\V1\StockIn\StockInSessionController@update` |
| POST | `/api/v1/stock-in-sessions/{stockIn}/finalize` | Yes | `stock_in.confirm` | `Api\V1\StockIn\StockInSessionController@finalize` |
| GET | `/api/v1/stock-in-sessions/{stockIn}/items` | Yes | `stock_in.view` | `Api\V1\StockIn\StockInItemController@index` |
| POST | `/api/v1/stock-in-sessions/{stockIn}/items` | Yes | `stock_in.edit_draft` | `Api\V1\StockIn\StockInItemController@store` |
| DELETE | `/api/v1/stock-in-sessions/{stockIn}/items/{stockInItem}` | Yes | `stock_in.edit_draft` | `Api\V1\StockIn\StockInItemController@destroy` |
| PATCH | `/api/v1/stock-in-sessions/{stockIn}/items/{stockInItem}` | Yes | `stock_in.edit_draft` | `Api\V1\StockIn\StockInItemController@update` |
| PUT | `/api/v1/stock-in-sessions/{stockIn}/items/{stockInItem}` | Yes | `stock_in.edit_draft` | `Api\V1\StockIn\StockInItemController@update` |
| PATCH | `/api/v1/stock-in-sessions/{stockIn}/items/{stockInItem}/correct` | Yes | `stock_in.correct_confirmed` | `Api\V1\StockIn\StockInItemController@correct` |
| GET | `/api/v1/stock-in-sessions/{stockIn}/review` | Yes | `stock_in.view` | `Api\V1\StockIn\StockInSessionController@review` |

### Supplier Returns (9)

| Method | Path | Auth | Permission | Handler |
|---|---|---|---|---|
| GET | `/api/v1/supplier-returns` | Yes | `disposals.view` | `Api\V1\SupplierReturn\SupplierReturnController@index` |
| POST | `/api/v1/supplier-returns` | Yes | `supplier_returns.create` | `Api\V1\SupplierReturn\SupplierReturnController@store` |
| GET | `/api/v1/supplier-returns/{supplierReturn}` | Yes | `disposals.view` | `Api\V1\SupplierReturn\SupplierReturnController@show` |
| PATCH | `/api/v1/supplier-returns/{supplierReturn}` | Yes | `supplier_returns.create` | `Api\V1\SupplierReturn\SupplierReturnController@update` |
| PUT | `/api/v1/supplier-returns/{supplierReturn}` | Yes | `supplier_returns.create` | `Api\V1\SupplierReturn\SupplierReturnController@update` |
| POST | `/api/v1/supplier-returns/{supplierReturn}/complete` | Yes | `supplier_returns.create` | `Api\V1\SupplierReturn\SupplierReturnController@complete` |
| GET | `/api/v1/supplier-returns/{supplierReturn}/items` | Yes | `disposals.view` | `Api\V1\SupplierReturn\SupplierReturnController@indexItems` |
| POST | `/api/v1/supplier-returns/{supplierReturn}/items` | Yes | `supplier_returns.create` | `Api\V1\SupplierReturn\SupplierReturnController@storeItem` |
| DELETE | `/api/v1/supplier-returns/{supplierReturn}/items/{supplierReturnItem}` | Yes | `supplier_returns.create` | `Api\V1\SupplierReturn\SupplierReturnController@destroyItem` |

### Usage Summary (6)

| Method | Path | Auth | Permission | Handler |
|---|---|---|---|---|
| GET | `/api/v1/usage-summaries` | Yes | `usage_summary.view` | `Api\V1\UsageSummary\UsageSummaryController@index` |
| GET | `/api/v1/usage-summaries/{usageSummary}` | Yes | `usage_summary.view` | `Api\V1\UsageSummary\UsageSummaryController@show` |
| POST | `/api/v1/usage-summaries/{usageSummary}/export` | Yes | `usage_summary.view` | `Api\V1\UsageSummary\UsageSummaryController@export` |
| POST | `/api/v1/usage-summaries/{usageSummary}/push` | Yes | `usage_summary.generate` | `Api\V1\UsageSummary\UsageSummaryController@push` |
| GET | `/api/v1/usage-summaries/{usageSummary}/push-logs` | Yes | `usage_summary.view_logs` | `Api\V1\UsageSummary\UsageSummaryController@pushLogs` |
| POST | `/api/v1/usage-summaries/generate` | Yes | `usage_summary.generate` | `Api\V1\UsageSummary\UsageSummaryController@generate` |


## 13. Testing and Tooling

- Run tests:

```bash
composer test
```

- Useful docs already in repository:
  - `doc/POSTMAN_API_TESTING_GUIDE.md`
  - `doc/API_RESPONSE_STANDARD.md`
  - `doc/EXCEPTION_HANDLING.md`
  - `doc/ROLES_AND_PERMISSIONS.md`
  - `doc/PERMISSIONS_IMPLEMENTATION_GUIDE.md`

## 14. Operational Notes

- API logging middleware writes to `Log::channel('api')`.
  Ensure an `api` channel exists in `config/logging.php` for production readiness.

- ERP push service reads:
  - `config('services.erp.push_url')`
  - `config('services.erp.api_key')`
  Add `erp` keys in `config/services.php` and matching env vars if not already present.

- Keep queue workers and scheduler active in non-local environments:
  - `php artisan queue:work`
  - `php artisan schedule:work` (or server cron running `schedule:run`)
