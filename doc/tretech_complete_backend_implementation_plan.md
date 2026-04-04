# TRETECH Complete Backend Implementation Plan

**Document:** TRETECH Inventory & Logistics System — Full Backend Implementation Plan  
**Version:** 2.0  
**Date:** 2026-04-04  
**Target Completion:** 2026-05-04 (1 month)  
**Prepared By:** Development Team  
**Based On:** tretech_srs_canvas.md v1.0, tretech_sprint_1_backend_plan.md

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Current State Assessment](#2-current-state-assessment)
3. [Architecture Overview](#3-architecture-overview)
4. [1-Month Execution Roadmap](#4-1-month-execution-roadmap)
5. [WEEK 1 — Foundation & Stock-In](#5-week-1--foundation--stock-in-5-apr--11-apr)
6. [WEEK 2 — Consignment & Returns](#6-week-2--consignment--returns-12-apr--18-apr)
7. [WEEK 3 — Disposal, Holding & Reporting](#7-week-3--disposal-holding--reporting-19-apr--25-apr)
8. [WEEK 4 — Integration, Testing & Hardening](#8-week-4--integration-testing--hardening-26-apr--4-may)
9. [Complete API Endpoint Registry](#9-complete-api-endpoint-registry)
10. [Database Schema Summary](#10-database-schema-summary)
11. [Service Layer Architecture](#11-service-layer-architecture)
12. [Cross-Cutting Concerns](#12-cross-cutting-concerns)
13. [Testing Strategy](#13-testing-strategy)
14. [Deployment & DevOps](#14-deployment--devops)
15. [Risk Register](#15-risk-register)

---

## 1. Executive Summary

TRETECH is a hybrid web and mobile system for unit-level medical supply inventory management. The backend is built with **Laravel 13 + PHP 8.3 + MySQL** and exposes a RESTful JSON API consumed by a React web app and a Flutter mobile app.

This document is the **complete backend implementation plan** covering all SRS features in a **compressed 1-month timeline (4 weeks)**. Each week is a focused phase with day-by-day sequential execution.

### Feature Coverage Map (SRS → Week)

| SRS Section | Feature | Week |
|---|---|---|
| 3.11 | Authentication & Access Control | Week 1 |
| 3.1 | Master Data Governance | Week 1 |
| 3.2 | Stock-In Session | Week 1 |
| 3.3 | Internal Labelling & QR Printing | Week 1 |
| 3.10 | Governance & Audit | Week 1 (baseline) + ongoing |
| 3.4 | Consignment (Stock-Out) | Week 2 |
| 3.5 | Return Session & Reconciliation | Week 2 |
| 3.7 | Disposal & Return-to-Supplier | Week 3 |
| 3.8 | Holding Area | Week 3 |
| 3.9 | Reporting & Analytics | Week 3 |
| 3.6 | Usage Summary & ERP Integration | Week 4 |
| — | Testing, Performance & Hardening | Week 4 |

---

## 2. Current State Assessment

### What Already Exists

| Layer | Status | Details |
|---|---|---|
| **Laravel Project** | ✅ Scaffolded | Laravel 13, Sanctum 4.3, PHP 8.3 |
| **Database Migrations** | ✅ 34 migrations | All core tables created (users, roles, permissions, products, suppliers, clients, lots, stock_ins, consignments, returns, reconciliations, disposals, etc.) |
| **Models** | ✅ 31 models | All domain models with relationships defined |
| **Auth Infrastructure** | ✅ Sanctum configured | Token-based auth ready |
| **Permission System** | ✅ Complete | 45 permissions, 2 roles, seeder, middleware |
| **API Response Standard** | ✅ Established | ApiResponse class, ApiResponseTrait, consistent envelope |
| **Exception Handling** | ✅ Complete | 7 custom exception classes + Handler |
| **Middleware** | ✅ 4 middleware | ApiBase, LogApiRequests, CheckPermission, CheckAllPermissions |
| **Controllers** | ❌ Empty | Only base Controller.php exists |
| **Services** | ❌ Not created | No service layer yet |
| **Form Requests** | ❌ Not created | No validation request classes |
| **Routes** | ❌ Minimal | Only default Sanctum route |
| **Tests** | ❌ Not created | No feature or unit tests |
| **Seeders** | ⚠️ Partial | PermissionSeeder only, no master data seeders |

### What Needs To Be Built

1. **All controllers** — approximately 15-18 resource controllers
2. **Service layer** — approximately 15-20 service classes
3. **Form Request classes** — approximately 30-40 validation classes
4. **Route definitions** — full `/api/v1/` route file
5. **Business logic** — stock-in finalization, consignment flow, reconciliation, disposal
6. **Queue jobs** — print jobs, ERP push, expiry notifications
7. **Export services** — PDF, CSV, XLSX generation
8. **Seeders** — master data, test data
9. **Tests** — feature tests, unit tests
10. **Scheduled tasks** — expiry alerts, backup triggers

---

## 3. Architecture Overview

### Directory Structure (Target)

```
app/
├── Console/
│   └── Commands/              # Artisan commands (expiry check, cleanup)
├── Enums/                     # Status enums, category enums
├── Events/                    # Domain events
├── Exceptions/                # ✅ Already complete
├── Exports/                   # Excel/CSV export classes
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       └── V1/            # All versioned API controllers
│   ├── Middleware/             # ✅ Already complete
│   ├── Requests/              # Form Request validation classes
│   │   └── Api/
│   │       └── V1/            # Versioned request classes
│   ├── Resources/             # API Resource transformers
│   │   └── Api/
│   │       └── V1/            # Versioned resource classes
│   ├── Responses/             # ✅ Already complete
│   └── Traits/                # ✅ Already complete
├── Jobs/                      # Queued jobs
├── Listeners/                 # Event listeners
├── Models/                    # ✅ Already complete
├── Notifications/             # Expiry alerts, system notifications
├── Observers/                 # Model observers for audit logging
├── Policies/                  # Authorization policies
├── Providers/                 # Service providers
├── Rules/                     # Custom validation rules
└── Services/                  # Business logic services
    ├── Auth/
    ├── MasterData/
    ├── StockIn/
    ├── Consignment/
    ├── Return/
    ├── Reconciliation/
    ├── Disposal/
    ├── HoldingArea/
    ├── QrLabel/
    ├── Reporting/
    ├── UsageSummary/
    └── Audit/
```

### Request Flow

```
HTTP Request
  → Middleware (ApiBase, Auth:Sanctum, Permission)
    → Controller (thin, coordinates only)
      → FormRequest (validation)
      → Service (business logic, DB transactions)
        → Model (data access)
        → Events/Jobs (async work)
      → API Resource (response transformation)
    → ApiResponse (standard envelope)
  → HTTP Response
```

---

## 4. 1-Month Execution Roadmap

### Timeline Overview

| Week | Dates | Phase | Focus |
|---|---|---|---|
| **Week 1** | 5 Apr — 11 Apr 2026 | Foundation | Auth, Master Data, Stock-In, QR, Inventory, Audit |
| **Week 2** | 12 Apr — 18 Apr 2026 | Core Workflows | Consignment, Return Session, Reconciliation |
| **Week 3** | 19 Apr — 25 Apr 2026 | Operations | Disposal, Supplier Return, Holding Area, Reporting |
| **Week 4** | 26 Apr — 4 May 2026 | Integration | Usage Summary, ERP Push, Testing, Hardening |

### Dependency Chain

```
Week 1 (Foundation + Stock-In)
    │
    ├── Auth & RBAC
    ├── Master Data (Products, Suppliers, Clients)
    ├── Stock-In → Lot Creation
    ├── QR Labels & Print Jobs
    ├── Inventory Visibility
    └── Audit Logging Baseline
            │
Week 2 (Consignment + Returns) ◄──────┘
    │
    ├── Consignment Note (Stock-Out)
    ├── Return Session
    └── Reconciliation + Used Computation
            │
Week 3 (Disposal + Holding + Reporting) ◄──┘
    │
    ├── Disposal & Return-to-Supplier
    ├── Holding Area Management
    └── Reporting (Stock, Consignment, Returns, Expiry)
            │
Week 4 (Integration + Hardening) ◄─────────┘
    │
    ├── Usage Summary & ERP Push
    ├── Comprehensive Testing
    ├── Performance Optimization
    └── Deployment Readiness
```

---

## 5. WEEK 1 — Foundation & Stock-In (5 Apr — 11 Apr)

> **Goal:** Backend skeleton with auth, master data CRUD, full stock-in flow, QR labeling, and inventory visibility.

### Day-by-Day Execution Sequence

#### Day 1 (Sat 5 Apr) — Enums, Auth & Route Structure

**Build order:**
1. Create all status Enums (`StockInStatus`, `LotStatus`, `PrintJobStatus`, `CaptureMethod`, `AuditAction`)
2. Create `routes/api.php` with `/api/v1/` prefix and route groups
3. Create `AuthController` + `AuthService` + `LoginRequest`
4. Implement login, logout, me, permissions endpoints
5. Create `AdminUserSeeder` with default admin credentials
6. Create `AuditLogService` + `ErrorLogService` (used by all modules)
7. Wire up middleware to route groups (auth:sanctum, permission checks)

**Done when:** Login returns token, `/me` returns user with role, permission middleware blocks unauthorized access.

---

#### Day 2 (Sun 6 Apr) — Master Data CRUD

**Build order:**
1. Create `UserController` + `UserResource` + Form Requests (admin-only CRUD)
2. Create `SupplierController` + `SupplierService` + `SupplierResource` + Form Requests
3. Create `ClientController` + `ClientService` + `ClientResource` + Form Requests
4. Create `ProductController` + `ProductService` + `ProductResource` + Form Requests
5. Create `InstrumentSetController` + `InstrumentSetService` + `InstrumentSetResource` + Form Requests
6. Create `SampleMasterDataSeeder` (suppliers, clients, products for testing)
7. Add audit logging to all create/update/delete operations

**Done when:** All master data CRUD works through API with pagination, filtering, uniqueness validation (product ref_num), and permission enforcement.

---

#### Day 3 (Mon 7 Apr) — Stock-In Session & Item Capture

**Build order:**
1. Create `StockInSessionController` + `StockInSessionService` + `StockInSessionResource`
2. Create `StoreStockInSessionRequest` + `UpdateStockInSessionRequest`
3. Implement session create (draft), update, list, show
4. Create `StockInItemController` + `StockInItemService` + `StockInItemResource`
5. Create `StoreStockInItemRequest` + `UpdateStockInItemRequest`
6. Implement item add, update, remove within draft session
7. Implement duplicate lot detection (within session + database)
8. Implement manual capture audit logging (when `capture_method = manual`)
9. Implement session review endpoint

**Done when:** Logistic staff can create session, add/edit/remove items with full validation, review summary. Duplicate lots blocked.

---

#### Day 4 (Tue 8 Apr) — Stock-In Finalization & Inventory

**Build order:**
1. Create `StockInFinalizeService` with atomic DB transaction
2. Implement finalization logic:
   - Validate session is `draft` with at least 1 item
   - Validate all mandatory fields and lot uniqueness
   - Create/update Lot records with status `available`
   - Create LotMovement records (action: `stock_in`)
   - Set session status to `finalized`
   - Record confirmed_at and confirmed_by
   - Write audit log entries
3. Create `InventoryController` + `InventoryService` + `LotResource`
4. Implement inventory listing with filters (status, supplier, product, expiry range)
5. Implement lot lookup by lot_number and by ref_num
6. Implement inventory ledger (movement history per lot)

**Done when:** Finalization creates lots in `available` status. Inventory search returns confirmed units. Movement ledger shows stock-in entry.

---

#### Day 5 (Wed 9 Apr) — QR Labels & Print Jobs

**Build order:**
1. Create `QrPayloadService` — generate canonical format: `V=1;REF={RefNum};LOT={LotNumber};BATCH={SupplierBatchCode};EXP={YYYY-MM-DD|-}`
2. Create `QrPayloadService::validate()` — check mandatory fields, format
3. Create `QrLabelController` — preview-payload, get-payload
4. Create `PrintJobService` — create, markPrinted, markFailed
5. Create `PrintJobController` + `PrintJobResource`
6. Integrate QR generation + print job creation into finalization flow
7. Implement reprint endpoint with mandatory reason + audit log

**Done when:** Finalization auto-creates QR payloads and print jobs. Print job lifecycle (queued → printed/failed) works. Reprint with reason is tracked.

---

#### Day 6 (Thu 10 Apr) — Audit & Admin Corrections

**Build order:**
1. Create `AuditLogController` + `AuditLogResource` (admin-only)
2. Implement audit log listing with filters (date, user, action, object_type)
3. Create admin correction endpoint for immutable fields (Ref, Lot, Batch, Expiry post-confirmation)
4. Implement old/new value tracking in audit log
5. Create `ErrorLogController` (admin-only) for system error viewing
6. Review and verify all Sprint 1 audit events are being captured

**Done when:** Admin can browse audit logs filtered by criteria. Admin corrections on confirmed stock-in data are tracked with before/after values.

---

#### Day 7 (Fri 11 Apr) — Week 1 Integration & Smoke Testing

**Build order:**
1. End-to-end flow test: login → create master data → stock-in session → add items → finalize → verify inventory
2. Fix any issues found in integration testing
3. Run database seeders, verify full data pipeline
4. Write quick feature tests for auth + stock-in finalization happy path
5. Document any known issues or deferred items

**Done when:** Full stock-in flow works end-to-end. No blocking issues for Week 2.

---

### Week 1 Deliverables Summary

| Module | Controllers | Services | Form Requests | Resources |
|---|---|---|---|---|
| Auth | 1 | 1 | 1 | 1 |
| Users | 1 | — | 2 | 1 |
| Master Data | 4 | 4 | 8 | 4 |
| Stock-In | 2 | 3 | 4 | 2 |
| Inventory | 1 | 1 | — | 1 |
| QR & Print | 2 | 2 | 1 | 1 |
| Audit | 1 | 2 | — | 1 |
| **Total** | **12** | **13** | **16** | **11** |

### Sprint 1 Files To Create

#### Enums
| File | Purpose |
|---|---|
| `app/Enums/StockInStatus.php` | `draft`, `finalized`, `cancelled` |
| `app/Enums/LotStatus.php` | `available`, `supplied`, `used`, `disposed`, `holding` |
| `app/Enums/PrintJobStatus.php` | `queued`, `printed`, `failed` |
| `app/Enums/CaptureMethod.php` | `scan`, `manual` |
| `app/Enums/AuditAction.php` | `create`, `update`, `delete`, `confirm`, `correct`, `reprint`, `login`, `logout` |

#### Controllers (app/Http/Controllers/Api/V1/)
| Controller | Endpoints |
|---|---|
| `AuthController` | login, logout, me, permissions |
| `UserController` | index, show, store, update (admin) |
| `SupplierController` | index, show, store, update, destroy |
| `ClientController` | index, show, store, update, destroy |
| `ProductController` | index, show, store, update, destroy |
| `InstrumentSetController` | index, show, store, update, destroy |
| `StockInSessionController` | index, show, store, update, finalize |
| `StockInItemController` | index, store, update, destroy |
| `InventoryController` | index, show, lookupByLot, lookupByRef, ledger |
| `QrLabelController` | previewPayload, getPayload |
| `PrintJobController` | index, store, markPrinted, markFailed, reprint |
| `AuditLogController` | index, show |

#### Services (app/Services/)
| Service | Responsibility |
|---|---|
| `Auth/AuthService` | Login, logout, token management |
| `MasterData/SupplierService` | Supplier CRUD with validation |
| `MasterData/ClientService` | Client CRUD with classification |
| `MasterData/ProductService` | Product CRUD with ref_num uniqueness |
| `MasterData/InstrumentSetService` | Set management, master lot generation |
| `StockIn/StockInSessionService` | Session lifecycle management |
| `StockIn/StockInItemService` | Item capture, validation, duplicate check |
| `StockIn/StockInFinalizeService` | Atomic finalization transaction |
| `QrLabel/QrPayloadService` | Canonical payload generation & validation |
| `QrLabel/PrintJobService` | Print job creation, status tracking |
| `Audit/AuditLogService` | Audit event recording |
| `Audit/ErrorLogService` | System error logging |

#### Form Requests (app/Http/Requests/Api/V1/)
| Request | Validates |
|---|---|
| `Auth/LoginRequest` | email, password |
| `Supplier/StoreSupplierRequest` | name, code, contact details |
| `Supplier/UpdateSupplierRequest` | same, partial |
| `Client/StoreClientRequest` | name, type, classification |
| `Product/StoreProductRequest` | ref_num (unique), name, category |
| `Product/UpdateProductRequest` | same, partial |
| `StockIn/StoreStockInSessionRequest` | supplier_id, do_number, stock_in_at, pic_user_id |
| `StockIn/UpdateStockInSessionRequest` | same, partial |
| `StockIn/StoreStockInItemRequest` | product_id, lot_number (unique), batch_code, expiry |
| `StockIn/UpdateStockInItemRequest` | same, partial |
| `PrintJob/ReprintRequest` | lot_id, reason (mandatory) |

#### API Resources (app/Http/Resources/Api/V1/)
| Resource | Transforms |
|---|---|
| `UserResource` | User with role info |
| `SupplierResource` | Supplier details |
| `ClientResource` | Client with classification |
| `ProductResource` | Product with ref_num |
| `StockInSessionResource` | Session with items count |
| `StockInItemResource` | Item with lot details |
| `LotResource` | Lot with full movement history |
| `PrintJobResource` | Print job with status |
| `AuditLogResource` | Audit entry |

#### Seeders
| Seeder | Purpose |
|---|---|
| `RoleSeeder` | admin, logistic_staff |
| `PermissionSeeder` | ✅ Already exists |
| `AdminUserSeeder` | Default admin account |
| `SampleMasterDataSeeder` | Suppliers, clients, products for testing |

---

## 6. WEEK 2 — Consignment & Returns (12 Apr — 18 Apr)

> **Goal:** Complete consignment (stock-out), return session, and reconciliation with used item computation.

### Day-by-Day Execution Sequence

#### Day 8 (Sat 12 Apr) — Consignment Draft & Items

**Build order:**
1. Create `ConsignmentStatus` enum (draft, confirmed, cancelled)
2. Create `ConsignmentController` + `ConsignmentService` + `ConsignmentResource`
3. Create `StoreConsignmentRequest` (client_id, consignment_at, pic_user_id)
4. Implement consignment create (draft), update, list, show
5. Create `ConsignmentItemController` + `ConsignmentItemResource`
6. Create `StoreConsignmentItemRequest` (lot_id — must be `available`)
7. Implement add/remove items from draft consignment
8. Implement review endpoint showing all items with lot details

**Done when:** Logistic staff can create consignment note, add available lots, review before confirmation.

---

#### Day 9 (Sun 13 Apr) — Consignment Confirmation & Admin Edit

**Build order:**
1. Create `ConsignmentConfirmService` with atomic transaction:
   - Validate all items are `available`
   - Update lot statuses to `supplied`
   - Create lot movement records (action: `consigned`)
   - Set consignment to `confirmed`, record confirmed_at/by
   - Write audit logs
2. Create `ConsignmentPostConfirmEditService` (admin-only):
   - Validate admin permission (`consignments.edit_confirmed`)
   - Accept mandatory edit reason
   - Track `edited_after_confirmation`, `last_post_confirm_edit_at/by/reason`
   - Write audit log with change details
3. Create `PostConfirmEditRequest`
4. Block consignment of non-Available units with clear error message

**Done when:** Confirmation changes lot status to supplied. Admin can edit confirmed consignment with reason tracking. Non-available lots blocked.

---

#### Day 10 (Mon 14 Apr) — Return Session

**Build order:**
1. Create `ReturnSessionStatus` enum (in_progress, completed)
2. Create `ReturnSessionController` + `ReturnSessionService` + `ReturnSessionResource`
3. Create `StoreReturnSessionRequest` (consignment_id — must be confirmed)
4. Implement return session create linked to consignment
5. Create `ReturnScanService`:
   - Validate lot was `supplied` and belongs to the linked consignment
   - Prevent duplicate scanning (same lot in same session)
   - Block over-return
6. Create `ScanReturnItemRequest` (lot_id or lot_number)
7. Implement scan, remove-item, and complete endpoints

**Done when:** Return session linked to consignment. Only eligible lots scannable. Partial returns work. Over-return blocked.

---

#### Day 11 (Tue 15 Apr) — Reconciliation & Used Computation

**Build order:**
1. Create `ReconciliationStatus` enum (pending, finalized, reopened)
2. Create `ReconciliationController` + `ReconciliationService` + `ReconciliationResource`
3. Create `ReconciliationFinalizeService` with atomic transaction:
   - Get consigned lots and returned lots
   - Compute: **Used = Consigned − Returned**
   - Set returned lots back to `available`
   - Set used lots to `used` (locked permanently)
   - Create reconciliation items for each used/returned lot
   - Create lot movement records
   - Set reconciliation to `finalized`
   - Write audit logs
4. Create `ReconciliationReopenService` (admin-only):
   - Mandatory reason required
   - Revert used lots back to `supplied`
   - Set status to `reopened`
   - Write audit log with reason
5. Create `ReopenReconciliationRequest` (reason mandatory)

**Done when:** Finalization correctly computes used items. Used lots locked. Admin can reopen with reason.

---

#### Day 12 (Wed 16 Apr) — Week 2 Integration Testing

**Build order:**
1. End-to-end flow: stock-in → consignment → return → reconciliation → verify used/returned status
2. Test edge cases:
   - Consign lot that's already supplied → blocked
   - Return lot not in consignment → blocked
   - Over-return → blocked
   - Admin reopen → used lots revert to supplied
3. Fix issues found
4. Verify audit trail for entire consignment lifecycle
5. Write feature tests for consignment confirmation + reconciliation finalization

**Done when:** Full consignment-return-reconciliation cycle works. All edge cases handled.

---

### Week 2 Deliverables Summary

| Module | Controllers | Services | Form Requests | Resources |
|---|---|---|---|---|
| Consignment | 2 | 3 | 3 | 2 |
| Return Session | 1 | 2 | 2 | 1 |
| Reconciliation | 1 | 3 | 2 | 1 |
| **Total** | **4** | **8** | **7** | **4** |

---

### 6.1 Consignment Module (SRS 3.4)

#### Business Rules
- Consignment Note requires: destination (client_id), date/time, PIC
- Only `available` lots can be consigned
- Status transitions: `draft` → `confirmed`
- Confirmation sets lot status to `supplied`
- Admin-only editing post-confirmation (audit-logged)
- Editable fields post-confirmation: remarks, items (add/remove with reason)

#### Status Enum: `ConsignmentStatus`
- `draft` — items being added
- `confirmed` — locked, items supplied
- `cancelled` — voided before confirmation

#### Endpoints
| Method | Endpoint | Permission | Description |
|---|---|---|---|
| POST | `/api/v1/consignments` | `consignments.create` | Create draft consignment |
| GET | `/api/v1/consignments` | `consignments.view` | List with filters |
| GET | `/api/v1/consignments/{id}` | `consignments.view` | Detail with items |
| PUT | `/api/v1/consignments/{id}` | `consignments.edit_draft` | Update draft header |
| POST | `/api/v1/consignments/{id}/confirm` | `consignments.confirm` | Confirm consignment |
| PUT | `/api/v1/consignments/{id}/post-confirm-edit` | `consignments.edit_confirmed` | Admin edit (audit-logged) |
| POST | `/api/v1/consignments/{id}/items` | `consignments.edit_draft` | Add item to draft |
| DELETE | `/api/v1/consignments/{id}/items/{itemId}` | `consignments.edit_draft` | Remove item from draft |
| GET | `/api/v1/consignments/{id}/review` | `consignments.view` | Review before confirm |

#### Confirmation Transaction
1. Validate all items are `available` status
2. Validate no duplicate lot in consignment
3. Update each lot status to `supplied`
4. Create lot movement records (action: `consigned`)
5. Set consignment status to `confirmed`
6. Record confirmed_at and confirmed_by
7. Write audit log entries
8. Commit atomically

#### Files To Create
| Type | File | Purpose |
|---|---|---|
| Enum | `ConsignmentStatus.php` | draft, confirmed, cancelled |
| Controller | `ConsignmentController.php` | Consignment CRUD + confirm |
| Controller | `ConsignmentItemController.php` | Item management |
| Service | `Consignment/ConsignmentService.php` | Draft management |
| Service | `Consignment/ConsignmentConfirmService.php` | Confirmation transaction |
| Service | `Consignment/ConsignmentPostConfirmEditService.php` | Admin edit flow |
| FormRequest | `StoreConsignmentRequest.php` | client_id, date, pic |
| FormRequest | `StoreConsignmentItemRequest.php` | lot_id validation |
| FormRequest | `PostConfirmEditRequest.php` | reason (mandatory) |
| Resource | `ConsignmentResource.php` | Consignment with items |
| Resource | `ConsignmentItemResource.php` | Item with lot details |

---

### 6.2 Return Session Module (SRS 3.5)

#### Business Rules
- Return Session is linked to a specific Consignment Note
- Only `supplied` lots belonging to that consignment can be returned
- Returned items scanned one by one
- Partial returns supported
- Over-return blocked (cannot return more than consigned)
- Status transitions: `in_progress` → `completed`

#### Status Enum: `ReturnSessionStatus`
- `in_progress` — scanning returns
- `completed` — all returns captured, ready for reconciliation

#### Endpoints
| Method | Endpoint | Permission | Description |
|---|---|---|---|
| POST | `/api/v1/return-sessions` | `returns.create` | Create linked to consignment |
| GET | `/api/v1/return-sessions` | `returns.view` | List with filters |
| GET | `/api/v1/return-sessions/{id}` | `returns.view` | Detail with items |
| POST | `/api/v1/return-sessions/{id}/scan` | `returns.create` | Scan returned item |
| DELETE | `/api/v1/return-sessions/{id}/items/{itemId}` | `returns.create` | Remove scanned item |
| POST | `/api/v1/return-sessions/{id}/complete` | `returns.finalize` | Complete return session |

#### Files To Create
| Type | File |
|---|---|
| Enum | `ReturnSessionStatus.php` |
| Controller | `ReturnSessionController.php` |
| Service | `Return/ReturnSessionService.php` |
| Service | `Return/ReturnScanService.php` |
| FormRequest | `StoreReturnSessionRequest.php` |
| FormRequest | `ScanReturnItemRequest.php` |
| Resource | `ReturnSessionResource.php` |

---

### 6.3 Reconciliation Module (SRS 3.5)

#### Business Rules
- Reconciliation created after return session completion
- **Used = Consigned − Returned** (computed at finalization only)
- Finalization locks Used units permanently
- Admin can reopen with mandatory reason (audit-logged)
- Status transitions: `pending` → `finalized` ↔ `reopened` → `finalized`

#### Status Enum: `ReconciliationStatus`
- `pending` — awaiting finalization
- `finalized` — used items computed and locked
- `reopened` — admin reopened for correction

#### Endpoints
| Method | Endpoint | Permission | Description |
|---|---|---|---|
| POST | `/api/v1/reconciliations` | `returns.finalize` | Create from return session |
| GET | `/api/v1/reconciliations` | `returns.view` | List with filters |
| GET | `/api/v1/reconciliations/{id}` | `returns.view` | Detail with computation |
| POST | `/api/v1/reconciliations/{id}/finalize` | `returns.finalize` | Compute used, lock |
| POST | `/api/v1/reconciliations/{id}/reopen` | `returns.reopen_reconciliation` | Admin reopen (reason required) |

#### Finalization Transaction
1. Get all consigned lots for the consignment
2. Get all returned lots from the return session
3. Compute: Used = Consigned − Returned
4. Set returned lot status back to `available`
5. Set used lot status to `used`
6. Create lot movement records for each used/returned item
7. Create reconciliation items
8. Set reconciliation status to `finalized`
9. Write audit logs
10. Commit atomically

#### Files To Create
| Type | File |
|---|---|
| Enum | `ReconciliationStatus.php` |
| Controller | `ReconciliationController.php` |
| Service | `Reconciliation/ReconciliationService.php` |
| Service | `Reconciliation/ReconciliationFinalizeService.php` |
| Service | `Reconciliation/ReconciliationReopenService.php` |
| FormRequest | `FinalizeReconciliationRequest.php` |
| FormRequest | `ReopenReconciliationRequest.php` |
| Resource | `ReconciliationResource.php` |

---

## 7. WEEK 3 — Disposal, Holding & Reporting (19 Apr — 25 Apr)

> **Goal:** Complete disposal, return-to-supplier, holding area management, and all core reporting endpoints.

### Day-by-Day Execution Sequence

#### Day 13 (Sat 19 Apr) — Disposal & Return-to-Supplier

**Build order:**
1. Create `DisposalStatus` enum (draft, completed) + `DisposalCategory` enum (expired, damaged, lost, other)
2. Create `DisposalController` + `DisposalService` + `DisposalResource`
3. Create `StoreDisposalRequest` + `StoreDisposalItemRequest` (mandatory reason + category)
4. Implement disposal create, add items, complete
5. On completion: set lot status to `disposed`, create lot movement record
6. Create `SupplierReturnStatus` enum
7. Create `SupplierReturnController` + `SupplierReturnService` + `SupplierReturnResource`
8. Create `StoreSupplierReturnRequest` (mandatory reason + category)
9. Implement supplier return create, add items, complete
10. On completion: set lot status to `returned_to_supplier`, create lot movement record

**Done when:** Both disposal and supplier-return flows work with mandatory reasons, correct status changes, and movement history.

---

#### Day 14 (Sun 20 Apr) — Holding Area

**Build order:**
1. Create `HoldingAreaController` + `HoldingAreaService` + `HoldingAreaResource`
2. Create `AssignLotRequest` (lot_number mandatory, reason mandatory)
3. Implement holding area listing (all lots with status `holding`)
4. Implement lot assignment:
   - Admin-only (`holding_area.assign_lot`)
   - Set lot_number on the lot record
   - Update lot status from `holding` to `available`
   - Update lot_holding record with assigned_lot_number, assigned_by, assigned_at
   - Create lot movement record (action: `holding_released`)
   - Write audit log
5. Update stock-in finalization: if lot_number is empty, set status to `holding` and create lot_holding record
6. Update consignment validation: block lots with status `holding`

**Done when:** Lots without lot_number enter holding. Admin can assign lot_number. Holding lots blocked from consignment.

---

#### Day 15 (Mon 21 Apr) — Install Export Packages & Report Infrastructure

**Build order:**
1. Install `maatwebsite/excel` and `barryvdh/laravel-dompdf`
2. Create `ReportController` with permission checks
3. Create `ExportService` — handles CSV, XLSX, PDF generation
4. Create base export class structure in `app/Exports/`
5. Create `Reporting/StockInReportService`:
   - Stock-in analytics by supplier, date range, product
   - Aggregate counts, totals
6. Create `Exports/StockInExport.php` (CSV/XLSX)

**Done when:** Export packages installed. Stock-in report with filtering and CSV/XLSX export works.

---

#### Day 16 (Tue 22 Apr) — Consignment & Returns Reports

**Build order:**
1. Create `Reporting/ConsignmentReportService`:
   - Consignment reporting by client, date, product
   - Include item counts, status breakdown
2. Create `Reporting/ReturnsAnalysisService`:
   - Returns vs Used analysis
   - Show consigned count, returned count, used count per consignment
3. Create `Exports/ConsignmentExport.php`
4. Create `Reporting/DisposalReportService`:
   - Disposal & loss reports by category, supplier, date
5. Create `Exports/DisposalExport.php`

**Done when:** Consignment, returns analysis, and disposal reports return correct data with export support.

---

#### Day 17 (Wed 23 Apr) — Expiry Dashboard & Report Exports

**Build order:**
1. Create `Reporting/ExpiryDashboardService`:
   - Query lots expiring within 30, 60, 90 day windows
   - Group by window, product, supplier
   - Include lot details with expiry dates
2. Create `Exports/ExpiryExport.php`
3. Implement PDF export for all report types via dompdf
4. Implement `POST /api/v1/reports/{type}/export` with format parameter (csv/xlsx/pdf)
5. Add filter support to all report endpoints (date, supplier, client, product_ref)

**Done when:** Expiry dashboard shows lots in 30/60/90 day windows. All reports exportable in CSV, XLSX, PDF.

---

#### Day 18 (Thu 24 Apr) — Week 3 Integration Testing

**Build order:**
1. Test disposal flow end-to-end: select lot → add reason → complete → verify disposed status
2. Test supplier return flow
3. Test holding area: stock-in without lot → holding → admin assign → available
4. Verify holding lots blocked from consignment
5. Test all report endpoints with various filters
6. Test all export formats download correctly
7. Fix issues found

**Done when:** All Week 3 features work. Reports accurate. Exports download correctly.

---

### Week 3 Deliverables Summary

| Module | Controllers | Services | Form Requests | Resources |
|---|---|---|---|---|
| Disposal | 1 | 1 | 2 | 1 |
| Supplier Return | 1 | 1 | 1 | 1 |
| Holding Area | 1 | 1 | 1 | 1 |
| Reporting | 1 | 6 | — | — |
| Exports | — | — | — | 4 |
| **Total** | **4** | **9** | **4** | **7** |

---

### 7.1 Disposal Module (SRS 3.7)

#### Business Rules
- Unit-level disposal with mandatory reason
- Categories: `expired`, `damaged`, `lost`, `other`
- Lot status changes to `disposed`
- Full movement history maintained
- Status: `draft` → `completed`

#### Endpoints
| Method | Endpoint | Permission |
|---|---|---|
| POST | `/api/v1/disposals` | `disposals.create` |
| GET | `/api/v1/disposals` | `disposals.view` |
| GET | `/api/v1/disposals/{id}` | `disposals.view` |
| POST | `/api/v1/disposals/{id}/items` | `disposals.create` |
| POST | `/api/v1/disposals/{id}/complete` | `disposals.create` |

#### Files To Create
| Type | File |
|---|---|
| Enum | `DisposalStatus.php`, `DisposalCategory.php` |
| Controller | `DisposalController.php` |
| Service | `Disposal/DisposalService.php` |
| FormRequest | `StoreDisposalRequest.php`, `StoreDisposalItemRequest.php` |
| Resource | `DisposalResource.php` |

---

### 7.2 Return-to-Supplier Module (SRS 3.7)

#### Business Rules
- Similar to disposal but lot goes back to supplier
- Mandatory reason required
- Same category options: `expired`, `damaged`, `recalled`, `other`
- Lot status changes to `returned_to_supplier`
- Full movement history maintained

#### Endpoints
| Method | Endpoint | Permission |
|---|---|---|
| POST | `/api/v1/supplier-returns` | `supplier_returns.create` |
| GET | `/api/v1/supplier-returns` | `disposals.view` |
| GET | `/api/v1/supplier-returns/{id}` | `disposals.view` |
| POST | `/api/v1/supplier-returns/{id}/items` | `supplier_returns.create` |
| POST | `/api/v1/supplier-returns/{id}/complete` | `supplier_returns.create` |

#### Files To Create
| Type | File |
|---|---|
| Enum | `SupplierReturnStatus.php` |
| Controller | `SupplierReturnController.php` |
| Service | `Disposal/SupplierReturnService.php` |
| FormRequest | `StoreSupplierReturnRequest.php` |
| Resource | `SupplierReturnResource.php` |

---

### 7.3 Holding Area Module (SRS 3.8)

#### Business Rules
- Units without Lot Number enter `holding` status automatically during stock-in
- Holding units are **blocked from consignment**
- Admin can assign Lot Number → unit moves to `available`
- Lot assignment is audit-logged

#### Endpoints
| Method | Endpoint | Permission |
|---|---|---|
| GET | `/api/v1/holding-area` | `holding_area.view` |
| GET | `/api/v1/holding-area/{id}` | `holding_area.view` |
| POST | `/api/v1/holding-area/{id}/assign-lot` | `holding_area.assign_lot` |

#### Files To Create
| Type | File |
|---|---|
| Controller | `HoldingAreaController.php` |
| Service | `HoldingArea/HoldingAreaService.php` |
| FormRequest | `AssignLotRequest.php` |
| Resource | `HoldingAreaResource.php` |

---

### 7.4 Reporting Module (SRS 3.9)

#### Report Types
| Report | Endpoint | Filters |
|---|---|---|
| Stock-In Analytics | `GET /api/v1/reports/stock-in` | date, supplier, product |
| Consignment Report | `GET /api/v1/reports/consignments` | date, client, product |
| Returns vs Used | `GET /api/v1/reports/returns-analysis` | date, consignment |
| Disposal & Loss | `GET /api/v1/reports/disposals` | date, category, supplier |
| Expiry Dashboard | `GET /api/v1/reports/expiry` | window (30/60/90 days) |
| Export | `POST /api/v1/reports/{type}/export` | format (csv/pdf/xlsx) |

#### Files To Create
| Type | File |
|---|---|
| Controller | `ReportController.php` |
| Service | `Reporting/StockInReportService.php` |
| Service | `Reporting/ConsignmentReportService.php` |
| Service | `Reporting/ReturnsAnalysisService.php` |
| Service | `Reporting/DisposalReportService.php` |
| Service | `Reporting/ExpiryDashboardService.php` |
| Service | `Reporting/ExportService.php` |
| Export | `Exports/StockInExport.php` |
| Export | `Exports/ConsignmentExport.php` |
| Export | `Exports/DisposalExport.php` |
| Export | `Exports/ExpiryExport.php` |

#### Required Packages
- `maatwebsite/excel` — XLSX/CSV export
- `barryvdh/laravel-dompdf` — PDF generation

---

## 8. WEEK 4 — Integration, Testing & Hardening (26 Apr — 4 May)

> **Goal:** Usage summary, ERP integration, comprehensive testing, performance optimization, and deployment readiness.

### Day-by-Day Execution Sequence

#### Day 19 (Sat 26 Apr) — Usage Summary Generation

**Build order:**
1. Create `UsageSummaryController` + `UsageSummaryResource`
2. Create `UsageSummaryGenerateService`:
   - Generate from finalized reconciliation
   - Collect all used items with product details
   - Exclude pricing fields
   - Create usage_summary and usage_summary_items records
3. Create `GenerateUsageSummaryRequest`
4. Implement list, show, generate, export endpoints
5. Integrate auto-generation into reconciliation finalization flow

**Done when:** Usage summary auto-generated on reconciliation finalization. Summary viewable and exportable.

---

#### Day 20 (Sun 27 Apr) — ERP Push & Retry

**Build order:**
1. Create `UsageSummaryPushService` + `ErpPushService`:
   - POST JSON payload to configured ERP endpoint
   - Include Idempotency-Key header (UUID)
   - Log each attempt to usage_summary_push_logs (status, response, duration)
2. Create `Jobs/PushUsageSummaryJob` (queued):
   - Retry on 5xx errors (max 3 attempts, exponential backoff)
   - Mark as failed after max retries
3. Implement push and push-logs endpoints
4. Create `CheckExpiryCommand` — daily artisan command to flag expiring lots
5. Create `RetryFailedPushesCommand` — retry failed pushes every 15 min
6. Register scheduled tasks in `Console/Kernel.php`

**Done when:** ERP push works with retry logic. Push logs tracked. Expiry check command runs.

---

#### Day 21 (Mon 28 Apr) — Comprehensive Feature Tests

**Build order:**
1. Auth tests: login success/failure, token expiry, rate limiting, unauthorized access
2. Master data tests: CRUD operations, uniqueness constraints, permission enforcement
3. Stock-in tests: draft lifecycle, item validation, finalization success + rollback
4. Consignment tests: confirmation transaction, admin post-edit, non-available block
5. Return tests: scan eligibility, over-return prevention, partial return
6. Reconciliation tests: used computation accuracy, reopen with reason

**Done when:** Core happy paths and critical rejection cases covered by automated tests.

---

#### Day 22 (Tue 29 Apr) — More Tests & Edge Cases

**Build order:**
1. Disposal tests: mandatory reason, category validation, status change
2. Holding area tests: consignment block, lot assignment, status transition
3. Reporting tests: data accuracy, date filtering
4. QR payload tests: canonical format, validation, print job lifecycle
5. Audit log tests: event capture, admin-only access
6. Usage summary tests: generation accuracy, push idempotency

**Done when:** All modules have test coverage. Edge cases handled.

---

#### Day 23 (Wed 30 Apr) — Performance Optimization

**Build order:**
1. Add composite database indexes for frequent query patterns
2. Review all queries: add eager loading, fix N+1 issues
3. Add caching for permission lookups and master data
4. Verify lot search < 3s, scan-to-save < 2s under normal load
5. Enforce cursor-based pagination for inventory and audit log endpoints
6. Add rate limiting: 5 failed logins per IP per 15 min
7. Verify session timeout at 30 minutes
8. Add CORS configuration for known origins

**Done when:** Performance targets met. Security hardening in place.

---

#### Day 24-25 (Thu 1 May — Fri 2 May) — Stabilization & Deployment Prep

**Build order:**
1. Fix any remaining bugs from testing
2. Review all API responses for consistency
3. Verify all permissions enforced correctly across all endpoints
4. Create comprehensive database seeders for UAT
5. Create deployment checklist and environment configuration
6. Run full test suite, fix failures
7. Review audit log coverage — ensure all SRS-required events are logged
8. Document any known limitations

**Done when:** All tests pass. Deployment package ready. No critical bugs.

---

#### Day 26-27 (Sat 3 May — Sun 4 May) — Buffer & Final Polish

**Build order:**
1. Buffer days for any overflow from previous days
2. Final end-to-end smoke test of complete system
3. API documentation cleanup
4. Staging deployment test
5. Sprint completion review

**Done when:** Backend is production-ready. All SRS features implemented and tested.

---

### Week 4 Deliverables Summary

| Module | Controllers | Services | Form Requests | Jobs | Commands |
|---|---|---|---|---|---|
| Usage Summary | 1 | 2 | 1 | 1 | — |
| ERP Integration | — | 1 | — | — | 2 |
| Testing | — | — | — | — | — |
| **Total** | **1** | **3** | **1** | **1** | **2** |

---

## 8-REFERENCE. Sprint 4 Module Details

### 8.1 Usage Summary Module (SRS 3.6)

#### Business Rules
- Generated at reconciliation finalization
- Contains list of used items with product details
- Export: PDF, CSV, XLSX
- REST API push: POST JSON with Idempotency-Key header
- Exclude pricing fields
- Auto-retry on retryable errors (5xx, timeout)
- Log every push attempt

#### Endpoints
| Method | Endpoint | Permission |
|---|---|---|
| GET | `/api/v1/usage-summaries` | `usage_summary.view` |
| GET | `/api/v1/usage-summaries/{id}` | `usage_summary.view` |
| POST | `/api/v1/usage-summaries/{id}/generate` | `usage_summary.generate` |
| POST | `/api/v1/usage-summaries/{id}/push` | `usage_summary.generate` |
| GET | `/api/v1/usage-summaries/{id}/push-logs` | `usage_summary.view_logs` |
| POST | `/api/v1/usage-summaries/{id}/export` | `usage_summary.view` |

#### Files To Create
| Type | File |
|---|---|
| Controller | `UsageSummaryController.php` |
| Service | `UsageSummary/UsageSummaryGenerateService.php` |
| Service | `UsageSummary/UsageSummaryPushService.php` |
| Job | `Jobs/PushUsageSummaryJob.php` |
| FormRequest | `GenerateUsageSummaryRequest.php` |
| Resource | `UsageSummaryResource.php` |

### 8.2 ERP Integration Service

```php
class ErpPushService
{
    // POST JSON payload to ERP endpoint
    // Include Idempotency-Key header (UUID)
    // Exclude pricing fields
    // Retry on 5xx errors (max 3 attempts, exponential backoff)
    // Log each attempt to usage_summary_push_logs
}
```

### 8.3 Scheduled Tasks

| Task | Schedule | Description |
|---|---|---|
| `CheckExpiryCommand` | Daily 8:00 AM | Flag lots expiring in 30/60/90 days |
| `RetryFailedPushesCommand` | Every 15 min | Retry failed ERP pushes |
| `CleanupOldErrorLogsCommand` | Weekly | Archive error logs older than 90 days |
| `AuditLogRetentionCheckCommand` | Monthly | Verify 7-year audit log retention |

### 8.4 Performance Optimization

| Area | Action |
|---|---|
| Database Indexing | Add composite indexes for frequent query patterns |
| Query Optimization | Use eager loading, avoid N+1 queries |
| Caching | Cache permission lookups, master data lookups |
| Pagination | Enforce cursor-based pagination for large datasets |
| Response Time | Target lot search < 3s, scan-to-save < 2s |

### 8.5 Security Hardening

| Area | Implementation |
|---|---|
| Rate Limiting | 5 failed logins per IP per 15 min |
| Session Timeout | 30-minute idle timeout |
| Password Hashing | bcrypt (Laravel default) |
| HTTPS | TLS 1.2+ enforced |
| Input Sanitization | All inputs validated through FormRequest |
| CORS | Restrict to known origins |
| API Key | Bearer token for ERP integration endpoints |

---

## 9. Complete API Endpoint Registry

### Authentication (4 endpoints)
```
POST   /api/v1/auth/login
POST   /api/v1/auth/logout
GET    /api/v1/auth/me
GET    /api/v1/auth/permissions
```

### Users (4 endpoints)
```
GET    /api/v1/users
GET    /api/v1/users/{id}
POST   /api/v1/users
PUT    /api/v1/users/{id}
```

### Master Data (20 endpoints)
```
GET|POST         /api/v1/suppliers
GET|PUT|DELETE   /api/v1/suppliers/{id}
GET|POST         /api/v1/clients
GET|PUT|DELETE   /api/v1/clients/{id}
GET|POST         /api/v1/products
GET|PUT|DELETE   /api/v1/products/{id}
GET|POST         /api/v1/instrument-sets
GET|PUT|DELETE   /api/v1/instrument-sets/{id}
```

### Stock-In (9 endpoints)
```
GET|POST         /api/v1/stock-in-sessions
GET|PUT          /api/v1/stock-in-sessions/{id}
POST             /api/v1/stock-in-sessions/{id}/items
PUT|DELETE       /api/v1/stock-in-sessions/{id}/items/{itemId}
GET              /api/v1/stock-in-sessions/{id}/review
POST             /api/v1/stock-in-sessions/{id}/finalize
```

### Inventory (5 endpoints)
```
GET    /api/v1/inventory-units
GET    /api/v1/inventory-units/{id}
GET    /api/v1/inventory-units/lookup/by-lot/{lotNumber}
GET    /api/v1/inventory-units/lookup/by-ref/{refNum}
GET    /api/v1/inventory-ledger
```

### QR & Print (7 endpoints)
```
POST   /api/v1/labels/preview-payload
GET    /api/v1/labels/inventory-units/{id}/payload
GET|POST /api/v1/print-jobs
POST   /api/v1/print-jobs/{id}/mark-printed
POST   /api/v1/print-jobs/{id}/mark-failed
POST   /api/v1/inventory-units/{id}/reprint
```

### Consignment (9 endpoints)
```
GET|POST         /api/v1/consignments
GET|PUT          /api/v1/consignments/{id}
POST             /api/v1/consignments/{id}/confirm
PUT              /api/v1/consignments/{id}/post-confirm-edit
POST|DELETE      /api/v1/consignments/{id}/items
GET              /api/v1/consignments/{id}/review
```

### Return Sessions (6 endpoints)
```
GET|POST         /api/v1/return-sessions
GET              /api/v1/return-sessions/{id}
POST             /api/v1/return-sessions/{id}/scan
DELETE           /api/v1/return-sessions/{id}/items/{itemId}
POST             /api/v1/return-sessions/{id}/complete
```

### Reconciliation (5 endpoints)
```
GET|POST         /api/v1/reconciliations
GET              /api/v1/reconciliations/{id}
POST             /api/v1/reconciliations/{id}/finalize
POST             /api/v1/reconciliations/{id}/reopen
```

### Disposal (5 endpoints)
```
GET|POST         /api/v1/disposals
GET              /api/v1/disposals/{id}
POST             /api/v1/disposals/{id}/items
POST             /api/v1/disposals/{id}/complete
```

### Supplier Returns (5 endpoints)
```
GET|POST         /api/v1/supplier-returns
GET              /api/v1/supplier-returns/{id}
POST             /api/v1/supplier-returns/{id}/items
POST             /api/v1/supplier-returns/{id}/complete
```

### Holding Area (3 endpoints)
```
GET    /api/v1/holding-area
GET    /api/v1/holding-area/{id}
POST   /api/v1/holding-area/{id}/assign-lot
```

### Usage Summary (6 endpoints)
```
GET    /api/v1/usage-summaries
GET    /api/v1/usage-summaries/{id}
POST   /api/v1/usage-summaries/{id}/generate
POST   /api/v1/usage-summaries/{id}/push
GET    /api/v1/usage-summaries/{id}/push-logs
POST   /api/v1/usage-summaries/{id}/export
```

### Reporting (6 endpoints)
```
GET    /api/v1/reports/stock-in
GET    /api/v1/reports/consignments
GET    /api/v1/reports/returns-analysis
GET    /api/v1/reports/disposals
GET    /api/v1/reports/expiry
POST   /api/v1/reports/{type}/export
```

### Audit (3 endpoints)
```
GET    /api/v1/audit-logs
GET    /api/v1/audit-logs/{id}
GET    /api/v1/error-logs
```

**Total: ~97 endpoints**

---

## 10. Database Schema Summary

### Existing Tables (34 migrations)

| Group | Tables |
|---|---|
| **Core** | users, cache, jobs |
| **Auth** | sessions, login_attempts, personal_access_tokens, permissions, role_permissions |
| **Master Data** | products, suppliers, clients, instrument_sets |
| **Main Entity** | lots |
| **Stock-In** | stock_ins, stock_in_items |
| **QR/Print** | qr_labels, qr_print_jobs |
| **Consignment** | consignments, consignment_items |
| **Returns** | return_sessions, return_session_items |
| **Reconciliation** | reconciliations, reconciliation_items |
| **Usage** | usage_summaries, usage_summary_items, usage_summary_push_logs |
| **Disposal** | disposals, disposal_items |
| **Supplier Return** | supplier_returns, supplier_return_items |
| **Holding** | lot_holdings |
| **Movement** | lot_movements |
| **Governance** | audit_logs, error_logs |

### Critical Indexes (To Add)

```sql
-- Performance-critical indexes
CREATE INDEX idx_lots_lot_number ON lots(lot_number);
CREATE INDEX idx_lots_status ON lots(status);
CREATE INDEX idx_lots_expiry_date ON lots(expiry_date);
CREATE INDEX idx_lots_supplier_id ON lots(supplier_id);
CREATE INDEX idx_lots_product_id ON lots(product_id);
CREATE INDEX idx_products_ref_num ON products(ref_num);
CREATE INDEX idx_lot_movements_lot_id ON lot_movements(lot_id);
CREATE INDEX idx_audit_logs_user_id ON audit_logs(user_id);
CREATE INDEX idx_audit_logs_object_type_id ON audit_logs(object_type, object_id);
CREATE INDEX idx_audit_logs_created_at ON audit_logs(created_at);
CREATE INDEX idx_consignments_client_id ON consignments(client_id);
CREATE INDEX idx_consignments_status ON consignments(status);
```

### Status Lifecycle

```
Lot Status Flow:
                                    ┌──────────────┐
Stock-In (no lot) ──────────────►   │   holding    │
                                    └──────┬───────┘
                                           │ Admin assigns lot
                                           ▼
Stock-In (with lot) ────────────►   ┌──────────────┐
                                    │  available   │
                                    └──────┬───────┘
                                           │ Consigned
                                           ▼
                                    ┌──────────────┐
                                    │  supplied    │
                                    └──────┬───────┘
                                      ┌────┴────┐
                                      │         │
                              Returned│         │Used (reconciled)
                                      ▼         ▼
                               ┌──────────┐ ┌──────────┐
                               │available │ │  used    │
                               └──────────┘ └──────────┘
                                      │
                              Disposal│ or Return-to-Supplier
                                      ▼
                               ┌──────────┐
                               │ disposed │
                               └──────────┘
```

---

## 11. Service Layer Architecture

### Service Naming Convention
- One service per bounded context
- Transactional services suffixed with action name (e.g., `FinalizeService`, `ConfirmService`)
- Services injected via constructor dependency injection

### Complete Service Registry

| Sprint | Service | Methods |
|---|---|---|
| **S1** | `AuthService` | login, logout, getAuthenticatedUser |
| **S1** | `SupplierService` | list, find, create, update, delete |
| **S1** | `ClientService` | list, find, create, update, delete |
| **S1** | `ProductService` | list, find, create, update, delete, validateRefNum |
| **S1** | `InstrumentSetService` | list, find, create, update, delete |
| **S1** | `StockInSessionService` | list, find, create, update, cancel |
| **S1** | `StockInItemService` | list, add, update, remove, validateDuplicate |
| **S1** | `StockInFinalizeService` | finalize (atomic transaction) |
| **S1** | `QrPayloadService` | generate, validate, parse |
| **S1** | `PrintJobService` | create, markPrinted, markFailed, requestReprint |
| **S1** | `AuditLogService` | log, query |
| **S1** | `ErrorLogService` | log, query |
| **S2** | `ConsignmentService` | list, find, create, update, cancel |
| **S2** | `ConsignmentConfirmService` | confirm (atomic transaction) |
| **S2** | `ConsignmentPostConfirmEditService` | edit (admin, audit-logged) |
| **S2** | `ReturnSessionService` | list, find, create, scanItem, removeItem, complete |
| **S2** | `ReturnScanService` | validateEligibility, scanReturn |
| **S2** | `ReconciliationService` | list, find, create |
| **S2** | `ReconciliationFinalizeService` | finalize (compute used, atomic) |
| **S2** | `ReconciliationReopenService` | reopen (admin, reason required) |
| **S3** | `DisposalService` | list, find, create, addItem, complete |
| **S3** | `SupplierReturnService` | list, find, create, addItem, complete |
| **S3** | `HoldingAreaService` | list, find, assignLot |
| **S3** | `StockInReportService` | getAnalytics |
| **S3** | `ConsignmentReportService` | getReport |
| **S3** | `ReturnsAnalysisService` | getAnalysis |
| **S3** | `DisposalReportService` | getReport |
| **S3** | `ExpiryDashboardService` | getDashboard (30/60/90 days) |
| **S3** | `ExportService` | exportCsv, exportPdf, exportXlsx |
| **S4** | `UsageSummaryGenerateService` | generate |
| **S4** | `UsageSummaryPushService` | push, retry |
| **S4** | `ErpPushService` | sendPayload, handleRetry |

---

## 12. Cross-Cutting Concerns

### 12.1 Audit Logging Strategy

All audit-logged actions use the `AuditLogService`:

```php
AuditLogService::log([
    'user_id'     => auth()->id(),
    'role'        => auth()->user()->getRoleCode(),
    'ip_address'  => request()->ip(),
    'device_id'   => request()->header('X-Device-Id'),
    'action'      => AuditAction::CONFIRM,
    'object_type' => 'StockIn',
    'object_id'   => $stockIn->id,
    'changes'     => ['status' => ['draft', 'finalized']],
    'reason'      => null,
]);
```

### 12.2 Event-Driven Architecture (Optional Enhancement)

| Event | Listener |
|---|---|
| `StockInFinalized` | CreateLotMovements, GenerateQrPayloads, CreatePrintJobs |
| `ConsignmentConfirmed` | UpdateLotStatuses, CreateLotMovements |
| `ReconciliationFinalized` | ComputeUsedItems, GenerateUsageSummary |
| `LotDisposed` | CreateLotMovement, WriteAuditLog |
| `HoldingLotAssigned` | UpdateLotStatus, CreateLotMovement, WriteAuditLog |

### 12.3 Pagination Standard

All list endpoints use consistent pagination:
- Default: 15 per page
- Max: 100 per page
- Parameters: `page`, `per_page`
- Response uses `ApiResponse::paginated()`

### 12.4 Filter & Sort Standard

All list endpoints support:
- `filter[field]=value` — filter by field
- `sort=field` or `sort=-field` — sort ascending/descending
- `search=term` — full-text search where applicable

---

## 13. Testing Strategy

### 13.1 Test Pyramid

| Level | Count (est.) | Coverage |
|---|---|---|
| Unit Tests | ~40 | Services, validators, payload generators |
| Feature Tests | ~80 | API endpoints, auth, RBAC |
| Integration Tests | ~15 | Multi-service transactions |

### 13.2 Critical Test Scenarios

#### Week 1
- Auth: login success/failure, token expiry, rate limiting
- Products: ref_num uniqueness, CRUD operations
- Stock-In: draft lifecycle, item validation, finalization success/rollback
- QR: payload generation, validation, print job lifecycle
- Inventory: lot lookup, movement ledger

#### Week 2
- Consignment: only available lots, confirmation transaction, admin post-edit
- Returns: scan eligibility, over-return prevention, partial return
- Reconciliation: used computation accuracy, reopen with reason

#### Week 3
- Disposal: mandatory reason, category validation, status change
- Holding: consignment block, lot assignment, status transition
- Reports: data accuracy, date filtering, export format

#### Week 4
- Usage Summary: generation accuracy, push idempotency
- ERP Push: retry logic, error handling, push log
- Expiry: 30/60/90 day window accuracy

### 13.3 Test Data Strategy

- Use Laravel factories for all models
- `DatabaseSeeder` calls all seeders in order
- Dedicated `TestDataSeeder` for integration test scenarios

---

## 14. Deployment & DevOps

### 14.1 Environment Configuration

| Environment | Database | Queue | Purpose |
|---|---|---|---|
| Local | SQLite | sync | Development |
| Staging | MySQL 8 | database | Integration testing |
| Production | MySQL 8 | Redis | Live operations |

### 14.2 Required Environment Variables

```env
# Database
DB_CONNECTION=mysql
DB_HOST=
DB_PORT=3306
DB_DATABASE=tretech
DB_USERNAME=
DB_PASSWORD=

# Auth
SANCTUM_STATEFUL_DOMAINS=
SESSION_LIFETIME=30

# Queue
QUEUE_CONNECTION=database

# ERP Integration (Sprint 4)
ERP_API_URL=
ERP_API_KEY=
ERP_PUSH_MAX_RETRIES=3
ERP_PUSH_RETRY_DELAY=60

# Expiry Alert
EXPIRY_ALERT_WINDOWS=30,60,90
```

### 14.3 Deployment Checklist

- [ ] Run migrations: `php artisan migrate --force`
- [ ] Run seeders: `php artisan db:seed`
- [ ] Cache config: `php artisan config:cache`
- [ ] Cache routes: `php artisan route:cache`
- [ ] Start queue worker: `php artisan queue:work`
- [ ] Set up scheduled tasks: `php artisan schedule:run`
- [ ] Verify HTTPS configuration
- [ ] Confirm backup schedule

---

## 15. Risk Register

| # | Risk | Impact | Mitigation |
|---|---|---|---|
| 1 | Data model changes mid-sprint | High | Freeze schema before implementation starts |
| 2 | Reconciliation logic errors | Critical | Comprehensive unit tests, manual verification |
| 3 | ERP integration endpoint unavailable | Medium | Retry mechanism, manual push fallback |
| 4 | Performance under 50 concurrent users | High | Load testing in Sprint 4, query optimization |
| 5 | Audit log storage growth | Medium | Partitioning strategy, archival after 7 years |
| 6 | Mobile-backend API contract drift | Medium | OpenAPI documentation, contract tests |
| 7 | Print job Bluetooth unreliability | Low (backend) | Backend only manages job records, mobile handles Bluetooth |

---

## Appendix A: Package Dependencies

### Current (composer.json)
- `laravel/framework` ^13.0
- `laravel/sanctum` ^4.3
- `laravel/tinker` ^3.0

### To Add
| Package | Sprint | Purpose |
|---|---|---|
| `maatwebsite/excel` | Sprint 3 | XLSX/CSV export |
| `barryvdh/laravel-dompdf` | Sprint 3 | PDF generation |
| `laravel/horizon` | Sprint 4 | Queue monitoring (optional) |

---

## Appendix B: File Count Summary

| Category | Sprint 1 | Sprint 2 | Sprint 3 | Sprint 4 | Total |
|---|---|---|---|---|---|
| Enums | 5 | 3 | 3 | 0 | 11 |
| Controllers | 12 | 3 | 4 | 1 | 20 |
| Services | 12 | 8 | 9 | 3 | 32 |
| Form Requests | 11 | 6 | 4 | 1 | 22 |
| Resources | 9 | 4 | 3 | 1 | 17 |
| Jobs | 0 | 0 | 0 | 1 | 1 |
| Commands | 0 | 0 | 0 | 4 | 4 |
| Exports | 0 | 0 | 4 | 0 | 4 |
| **Total** | **49** | **24** | **27** | **11** | **111** |

---

*End of Document*
