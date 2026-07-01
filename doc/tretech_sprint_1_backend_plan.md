# TRETECH Sprint 1 Backend Implementation Plan

## 1. Sprint 1 context

**Project phase:** Development Phase - Sprint 1  
**Sprint window:** 16 Mar 2026 - 10 Apr 2026  
**Sprint milestone:** First Development Sprint Completion by 10 Apr 2026  
**Technology scope:** Laravel PHP backend, RESTful API, MySQL database  
**Focus:** Backend only

This sprint is the backend foundation sprint. It must deliver a production-grade API and database baseline that supports the earliest operational value of the system: **master data governance, stock-in session flow, internal QR labeling support, and inventory visibility**.

Sprint 1 should not try to finish the whole product. It should establish the backend core strongly enough so Sprint 2 can build consignment, returns, and reconciliation without reworking core data structures.

---

## 2. Sprint 1 objective

By the end of Sprint 1, the backend must support:

1. secure authentication and role-based access control
2. master data CRUD required for stock-in
3. stock-in session creation and confirmation workflow
4. unit-level item capture with lot, expiry, supplier batch, and reference validation
5. internal QR payload generation and label print job creation
6. inventory availability state after stock-in confirmation
7. inventory search and ledger inquiry APIs
8. audit logging for critical backend actions
9. test coverage for critical stock-in and inventory flows
10. deployment-ready staging backend for frontend and mobile integration

---

## 3. Sprint 1 functional scope

### Included in Sprint 1

#### A. Backend platform foundation
- Laravel project structure
- environment configuration
- API versioning strategy
- authentication
- authorization
- exception handling
- request validation standard
- API response standard
- queue setup for asynchronous jobs
- logging baseline

#### B. Master data required by stock-in
- users
- roles and permissions
- suppliers
- clients or hospitals
- locations
- product categories
- products
- instrument sets if needed for stock identity setup

#### C. Stock-In backend flow
- create stock-in session
- save session header details
- add scanned or manually entered units
- validate uniqueness and required fields
- review items before confirmation
- confirm stock-in session
- write inventory records and movement ledger
- set item status to `available`

#### D. QR and printing support
- generate canonical QR payload
- validate payload rules
- create print job entries
- support label reprint request structure
- store print status lifecycle

#### E. Inventory visibility
- inventory unit listing
- inventory availability search
- lot lookup
- movement ledger inquiry
- stock-in history view

#### F. Governance baseline
- audit logs for critical create, update, confirm, and correction actions
- error logging for system failures
- admin-only access enforcement where required

### Explicitly excluded from Sprint 1
- consignment confirmation flow
- return session flow
- reconciliation logic
- used item computation
- disposal and return-to-supplier execution
- ERP integration push
- reporting exports beyond basic admin query endpoints
- advanced dashboard analytics
- reopen workflows except where needed for admin correction design preparation

---

## 4. Sprint 1 success criteria

Sprint 1 is successful only if all of the following are true:

- users can authenticate and receive authorized API access by role
- admin can manage required master data through the backend
- logistic staff can create a stock-in session and add units
- system blocks invalid, duplicate, or incomplete stock entries
- system confirms stock-in only after review and valid data checks
- confirmed units become available inventory records
- every confirmed stock-in creates movement history
- QR payloads are generated in canonical format and linked to units
- label print jobs are stored with status tracking
- immutable rules are enforced after confirmation, except approved admin correction path
- critical actions are audit-logged
- automated tests cover the core happy path and key rejection cases

---

## 5. Sprint 1 backend deliverables

### Deliverable 1 - Backend project foundation
- Laravel application bootstrap
- base module structure
- API version prefix `/api/v1`
- environment files and example config
- database connection setup
- queue configuration
- centralized exception handler
- standardized JSON response format

### Deliverable 2 - Authentication and authorization
- login endpoint
- logout endpoint
- current user endpoint
- role and permission enforcement
- route protection middleware
- seeders for initial admin user and base roles

### Deliverable 3 - Master data APIs
- supplier CRUD
- client or hospital CRUD
- location CRUD
- product category CRUD
- product CRUD
- instrument set CRUD if implemented in Sprint 1
- list and search endpoints for scanner and admin consumption

### Deliverable 4 - Stock-In APIs
- create stock-in session
- update draft stock-in session
- add units into session
- edit draft unit lines
- remove draft unit lines
- review session summary
- finalize stock-in session
- fetch stock-in session details
- list stock-in sessions with filters

### Deliverable 5 - Inventory APIs
- list inventory units
- get inventory unit detail
- search by lot number
- search by product reference
- list available inventory only
- movement ledger query endpoint

### Deliverable 6 - QR and label print support
- canonical payload generation service
- QR validation service
- print job table and API for queued jobs
- reprint request endpoint with reason field

### Deliverable 7 - Governance baseline
- audit log table and service
- error log table and service
- admin-only audit log query endpoint

### Deliverable 8 - Testing and release assets
- feature tests
- unit tests for stock-in rules
- API collection or OpenAPI draft
- staging deployment checklist
- seed data for UAT or frontend integration

---

## 6. Sprint 1 module breakdown

## 6.1 Auth and access control module

### Objective
Provide a secure entry point for web admin and mobile operational users.

### Scope
- user model
- role model
- permission model
- token-based authentication
- session timeout policy support on backend side
- rate limit configuration for login

### Endpoints
- `POST /api/v1/auth/login`
- `POST /api/v1/auth/logout`
- `GET /api/v1/auth/me`
- `GET /api/v1/users/me/permissions`

### Implementation notes
- use Laravel Sanctum unless a stronger token requirement emerges
- permissions should be checked at endpoint and service level
- seed roles: `admin`, `logistic_staff`

### Done definition
- unauthorized access is blocked
- authenticated user gets correct role scope
- login failures are rate-limited

---

## 6.2 Master data module

### Objective
Provide the core reference data needed by stock-in and inventory.

### Scope
- suppliers
- clients or hospitals
- locations
- product categories
- products
- optional instrument sets

### Core rules
- product ref number must be globally unique
- supplier must exist before stock-in
- products should support active or inactive state
- hospitals and other client types should be classifiable

### Suggested endpoints
- `GET /api/v1/suppliers`
- `POST /api/v1/suppliers`
- `GET /api/v1/suppliers/{id}`
- `PUT /api/v1/suppliers/{id}`
- `GET /api/v1/clients`
- `POST /api/v1/clients`
- `GET /api/v1/locations`
- `POST /api/v1/locations`
- `GET /api/v1/product-categories`
- `POST /api/v1/product-categories`
- `GET /api/v1/products`
- `POST /api/v1/products`
- `GET /api/v1/products/{id}`
- `PUT /api/v1/products/{id}`

### Done definition
- required master data can be created, updated, listed, and validated by backend
- uniqueness constraints are enforced by both request validation and DB indexes

---

## 6.3 Stock-In session module

### Objective
Implement the first critical warehouse workflow: receiving stock at unit level.

### Scope
- stock-in session header
- draft state line capture
- unit capture validation
- review summary
- finalize action
- admin correction structure for immutable fields

### Session header fields
- supplier_id
- do_number
- stock_in_at
- pic_user_id
- remarks
- status

### Unit line fields
- stock_in_session_id
- product_id
- ref_num_snapshot
- lot_number
- manufacturing_date
- expiry_date
- barcode_raw
- capture_method (`scan`, `manual`)
- print_required
- remarks

### Core rules
- manufacturing date is mandatory per unit
- lot number is globally unique
- no duplicate unit capture within the same session
- manual lot or expiry entry must be audit-logged
- units remain draft until session finalization
- confirmation makes unit data immutable except controlled admin correction flow

### Suggested endpoints
- `POST /api/v1/stock-in-sessions`
- `GET /api/v1/stock-in-sessions`
- `GET /api/v1/stock-in-sessions/{id}`
- `PUT /api/v1/stock-in-sessions/{id}`
- `POST /api/v1/stock-in-sessions/{id}/items`
- `PUT /api/v1/stock-in-sessions/{id}/items/{itemId}`
- `DELETE /api/v1/stock-in-sessions/{id}/items/{itemId}`
- `GET /api/v1/stock-in-sessions/{id}/review`
- `POST /api/v1/stock-in-sessions/{id}/finalize`

### Finalization behavior
On finalize, backend must:
1. validate session status is still draft
2. validate all mandatory line fields
3. validate no duplicate lots in DB or session
4. persist inventory unit records
5. create inventory movement records
6. set inventory status to `available`
7. generate QR payload for each unit
8. create print jobs where required
9. write audit entries
10. commit transaction atomically

### Done definition
- logistic staff can complete stock-in end-to-end from session creation to confirmation
- invalid finalization is rejected with clear validation messages
- rollback happens if any line fails during finalization

---

## 6.4 QR payload and print job module

### Objective
Support the internal labeling flow required immediately after stock-in confirmation.

### QR canonical format
`V=1;REF={RefNum};LOT={LotNumber};BATCH={ManufacturingDate};EXP={YYYY-MM-DD|-}`

### Scope
- QR payload generator
- QR payload validator
- print job creation
- print job state management
- reprint request registration

### Print job statuses
- queued
- printed
- failed

### Suggested endpoints
- `POST /api/v1/labels/preview-payload`
- `GET /api/v1/labels/inventory-units/{id}/payload`
- `POST /api/v1/print-jobs`
- `GET /api/v1/print-jobs`
- `POST /api/v1/print-jobs/{id}/mark-printed`
- `POST /api/v1/print-jobs/{id}/mark-failed`
- `POST /api/v1/inventory-units/{id}/reprint`

### Done definition
- canonical payload is always generated correctly
- one print job per confirmed unit can be created
- failed and reprint states are traceable

---

## 6.5 Inventory module

### Objective
Expose current stock visibility after stock-in confirmation.

### Scope
- inventory unit record
- availability status
- lot-based lookup
- stock-in traceability
- ledger-based movement history

### Suggested endpoints
- `GET /api/v1/inventory-units`
- `GET /api/v1/inventory-units/{id}`
- `GET /api/v1/inventory-units/lookup/by-lot/{lotNumber}`
- `GET /api/v1/inventory-units/lookup/by-ref/{refNum}`
- `GET /api/v1/inventory-ledger`

### Minimum filters
- status
- supplier_id
- product_id
- ref_num
- lot_number
- expiry_from
- expiry_to
- stock_in_date_from
- stock_in_date_to

### Done definition
- admin and authorized staff can locate newly received items by lot, product reference, or status
- movement history is queryable per unit

---

## 6.6 Audit and system logging module

### Objective
Provide backend governance from the first sprint, not later.

### Scope
- audit logs for business actions
- system error logs for backend failures
- admin-only access for audit viewing

### Audit events to capture in Sprint 1
- login success and login failure
- create or update supplier
- create or update product
- create stock-in session
- add stock-in item
- manual lot or expiry entry
- finalize stock-in session
- admin correction on immutable stock fields
- label reprint request

### Suggested endpoints
- `GET /api/v1/audit-logs`
- `GET /api/v1/audit-logs/{id}`
- `GET /api/v1/error-logs`

### Done definition
- all critical Sprint 1 actions write auditable records
- only admin can access audit log APIs

---

## 7. Recommended Sprint 1 database scope

## 7.1 Core tables to implement in Sprint 1

### Access and governance
- users
- roles
- permissions
- role_user or model_has_roles
- permission_role or model_has_permissions
- audit_logs
- error_logs

### Master data
- suppliers
- clients
- locations
- product_categories
- products
- instrument_sets (optional if truly needed now)

### Stock-in and inventory
- stock_in_sessions
- stock_in_session_items
- inventory_units
- inventory_movements
- label_print_jobs

---

## 7.2 Suggested status design

### stock_in_sessions.status
- draft
- finalized
- cancelled

### inventory_units.status
- available
- holding

### label_print_jobs.status
- queued
- printed
- failed

Do not add future statuses prematurely unless Sprint 1 actually uses them.

---

## 7.3 Critical database constraints

- `products.ref_num` unique
- `inventory_units.lot_number` unique
- `stock_in_session_items` must reference valid session and product
- `stock_in_sessions.supplier_id` required
- `manufacturing_date` required per item
- foreign keys indexed
- frequent search fields indexed: `lot_number`, `ref_num`, `status`, `expiry_date`, `stock_in_at`

---

## 7.4 Data integrity rules

- session items may be edited only while header is draft
- finalized stock-in sessions are immutable except admin correction path
- inventory unit must not exist until finalization succeeds
- every inventory unit creation must create at least one movement ledger record
- every manual correction must be audit-logged with old and new values

---

## 8. Suggested service layer

Use services or actions so controllers stay thin.

### Required services
- `AuthService`
- `SupplierService`
- `ProductService`
- `StockInSessionService`
- `StockInFinalizeService`
- `InventoryService`
- `QrPayloadService`
- `LabelPrintJobService`
- `AuditLogService`
- `ErrorLogService`

### Recommended actions or jobs
- `CreateStockInSessionAction`
- `AddStockInItemAction`
- `FinalizeStockInSessionAction`
- `GenerateUnitQrPayloadAction`
- `QueueLabelPrintJobAction`

---

## 9. Sprint 1 technical standards

## 9.1 API design rules
- RESTful naming
- JSON only
- consistent envelope for success and error responses
- pagination for list endpoints
- filter and sort support on list endpoints
- validation errors returned in structured format

### Suggested success response shape
```json
{
  "success": true,
  "message": "Stock-in session finalized successfully.",
  "data": {}
}
```

### Suggested error response shape
```json
{
  "success": false,
  "message": "Validation failed.",
  "errors": {
    "lot_number": ["The lot number has already been taken."]
  }
}
```

## 9.2 Code structure rules
- controllers only coordinate request and response
- domain rules stay in services or actions
- Form Request classes handle validation
- policies or permissions gate access
- DB transactions wrap finalization actions
- enums or constants store statuses centrally

## 9.3 Audit and exception rules
- no silent failure for critical transactional actions
- every exception must be logged with structured context
- do not expose internal stack traces to clients
- store correction reasons for admin-only overrides

---

## 10. Sprint 1 work plan by week

## Week 1 - 16 Mar 2026 to 20 Mar 2026

### Main goal
Establish backend foundation and freeze implementation scaffolding.

### Tasks
- initialize Laravel project and repository structure
- configure environments
- set up MySQL connection and migration strategy
- set up auth package
- define roles and permissions
- create base middleware and API response standard
- create exception handling pattern
- prepare initial seeders
- finalize migration list for Sprint 1 tables
- agree API naming conventions and status enums

### Expected output
- backend skeleton ready
- auth baseline ready
- migration plan approved
- Sprint 1 technical conventions locked

---

## Week 2 - 23 Mar 2026 to 27 Mar 2026

### Main goal
Complete master data module and database baseline.

### Tasks
- build master tables migrations
- create models and relationships
- implement supplier CRUD
- implement client or hospital CRUD
- implement location CRUD
- implement product category CRUD
- implement product CRUD
- apply uniqueness and index constraints
- add feature tests for master data endpoints
- seed sample master data

### Expected output
- all stock-in prerequisite masters available through API
- database constraints validated
- role-based access applied to master data endpoints

---

## Week 3 - 30 Mar 2026 to 3 Apr 2026

### Main goal
Implement stock-in session and unit capture flow.

### Tasks
- create stock_in_sessions migration and model
- create stock_in_session_items migration and model
- implement draft session create and update APIs
- implement add, edit, delete session item APIs
- implement duplicate detection logic
- implement manual entry audit logging
- implement session review endpoint
- add validation and service-layer tests

### Expected output
- logistic staff can create a stock-in session and populate draft lines
- system blocks invalid or duplicate captures before finalization

---

## Week 4 - 6 Apr 2026 to 10 Apr 2026

### Main goal
Finalize inventory, QR, print job, audit, and stabilization.

### Tasks
- create inventory_units migration and model
- create inventory_movements migration and model
- implement stock-in finalization transaction
- generate QR payload per confirmed unit
- create label_print_jobs table and APIs
- implement inventory query endpoints
- implement audit log query endpoint
- add error log handling
- write end-to-end feature tests for stock-in finalization
- prepare staging deployment package
- fix defects and stabilize for milestone review

### Expected output
- stock-in works end-to-end
- confirmed units become searchable inventory
- print jobs and audit logs are available
- Sprint 1 milestone ready for review by 10 Apr 2026

---

## 11. Suggested task board structure

## Epic 1 - Platform foundation
- project bootstrap
- env config
- auth setup
- roles and permissions
- base middleware
- exception handling
- response standard

## Epic 2 - Master data
- suppliers API
- clients API
- locations API
- categories API
- products API
- seeders and fixtures

## Epic 3 - Stock-In
- session header APIs
- session item APIs
- review flow
- finalization transaction
- manual entry audit

## Epic 4 - Inventory and labeling
- inventory units
- movement ledger
- QR generation
- print jobs
- reprint request

## Epic 5 - Governance and QA
- audit logs
- error logs
- feature tests
- API docs
- staging readiness

---

## 12. Recommended user stories for Sprint 1

### Auth
- As an admin, I can sign in and access protected endpoints.
- As a logistic staff user, I can sign in and access only operational endpoints allowed to my role.

### Master data
- As an admin, I can create a supplier so stock-in sessions can reference it.
- As an admin, I can create products with globally unique reference numbers.
- As an admin, I can manage client and location records.

### Stock-In
- As a logistic staff user, I can create a stock-in session with supplier, DO number, date, and PIC.
- As a logistic staff user, I can add units into a draft session by scan or manual entry.
- As a logistic staff user, I cannot add a duplicate lot number.
- As a logistic staff user, I can review captured units before confirmation.
- As a logistic staff user, I can finalize a valid stock-in session.

### Inventory and label
- As a user, I can search inventory by lot number after stock-in confirmation.
- As a user, I can view inventory movement history for a unit.
- As a logistic staff user, I can trigger a label print job for confirmed units.
- As an admin, I can review audit history for stock-in actions.

---

## 13. Acceptance criteria by module

## 13.1 Auth
- valid credentials return token and user profile
- invalid credentials are rejected
- role-restricted endpoint access is enforced

## 13.2 Master data
- duplicate product reference number is rejected
- inactive master records cannot be used where business rules block them
- list endpoints support pagination and basic search

## 13.3 Stock-In session
- session cannot be finalized without at least one valid unit line
- item without manufacturing date is rejected
- duplicate lot number is rejected
- manual entry is audit-logged
- finalized session cannot be edited by normal staff

## 13.4 Inventory
- finalization creates inventory unit records
- all new units default to `available`
- inventory lookup by lot returns exact unit detail
- movement ledger contains stock-in entry for each finalized unit

## 13.5 QR and print jobs
- payload format matches canonical specification
- invalid payload generation is blocked
- one print job per unit can be generated on confirmation
- reprint requires reason

## 13.6 Audit
- create, update, finalize, and override events are written to audit logs
- audit viewing is admin-only

---

## 14. Test plan for Sprint 1 backend

## 14.1 Unit tests
- QR payload builder
- duplicate lot validation logic
- stock-in finalization service
- status transition guards

## 14.2 Feature tests
- login and auth guard behavior
- supplier CRUD
- product CRUD with ref uniqueness
- create stock-in session
- add stock-in session items
- finalize stock-in success path
- finalize stock-in rollback on invalid duplicate
- inventory lookup after confirmation
- audit access restricted to admin

## 14.3 Database tests
- unique constraints work at DB level
- foreign key protections work
- transaction rollback preserves consistency on failure

## 14.4 Manual integration checks
- mobile app can consume auth and stock-in APIs
- label print workflow can read print jobs
- inventory search response shape is usable for web admin

---

## 15. Risks and controls for Sprint 1

## Risk 1 - Data model instability
If stock-in tables keep changing during implementation, Sprint 1 will slip.

**Control:** freeze Sprint 1 schema before Week 2 development completes.

## Risk 2 - Overbuilding future modules
Trying to include consignment and returns inside Sprint 1 will dilute the milestone.

**Control:** enforce strict sprint boundary and push downstream features to Sprint 2.

## Risk 3 - Weak uniqueness and integrity rules
If duplicate lot logic is weak, later reconciliation becomes unreliable.

**Control:** enforce validation in request layer, service layer, and DB constraints.

## Risk 4 - QR or printing design unclear
If printer integration assumptions are unstable, Sprint 1 may deliver unusable print job flows.

**Control:** treat printer execution as an integration boundary but fully complete backend print job orchestration now.

## Risk 5 - Poor test coverage
Stock-in defects will cascade into all later modules.

**Control:** make stock-in finalization and inventory creation fully covered by automated tests.

---

## 16. Dependencies and assumptions

### Dependencies
- approved Sprint 1 schema
- approved role matrix
- finalized QR payload format
- confirmed product reference and lot uniqueness rules
- stable staging MySQL environment
- frontend and mobile teams aligned on API contracts

### Assumptions
- project timeline remains unchanged
- Sprint 1 ends on 10 Apr 2026
- backend uses Laravel and MySQL only
- no offline-first requirement in Sprint 1
- printer device integration on mobile side can consume backend print job records later

---

## 17. Definition of done for Sprint 1 milestone

Sprint 1 is done only when:

- all in-scope migrations are applied cleanly
- seeders create usable base data
- auth and roles work in staging
- master data APIs are complete and tested
- stock-in draft and finalization flow is complete and tested
- inventory units and movement ledger are generated correctly
- QR payloads and print jobs are persisted correctly
- audit logs exist for critical actions
- API collection or OpenAPI draft is prepared
- staging deployment passes smoke testing
- known issues are documented and do not block Sprint 2

---

## 18. Recommended handoff output at end of Sprint 1

At Sprint 1 review, backend team should hand over:

1. deployed staging API
2. migration files and seeders
3. endpoint documentation
4. test report
5. known limitation list
6. sample credentials by role
7. sample stock-in workflow demo data
8. Sprint 2 readiness notes

---

## 19. My recommendation on implementation priority inside Sprint 1

Build in this order:

1. auth and access control
2. master data and DB constraints
3. stock-in draft flow
4. stock-in finalization transaction
5. inventory unit and ledger queries
6. QR payload and print jobs
7. audit logs and stabilization
8. automated test hardening

This order minimizes rework and protects the transactional core.

---

## 20. Sprint 1 summary

Sprint 1 should deliver the **backend foundation plus the full stock-in and inventory core**, not just scaffolding. If this sprint is done correctly, Sprint 2 can safely build consignment and returns on top of stable inventory identity, auditability, and movement history.

The most important backend outcome of Sprint 1 is this:

**A unit enters the system once, is validated correctly, becomes available inventory through a controlled transaction, receives traceable QR identity, and leaves a complete audit trail.**

