<?php

use App\Http\Controllers\Api\V1\Audit\AuditLogController;
use App\Http\Controllers\Api\V1\Audit\ErrorLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Consignment\ConsignmentController;
use App\Http\Controllers\Api\V1\Consignment\ConsignmentItemController;
use App\Http\Controllers\Api\V1\Dashboard\DashboardController;
use App\Http\Controllers\Api\V1\Disposal\DisposalController;
use App\Http\Controllers\Api\V1\HoldingArea\HoldingAreaController;
use App\Http\Controllers\Api\V1\Inventory\InventoryController;
use App\Http\Controllers\Api\V1\MasterData\ClientController;
use App\Http\Controllers\Api\V1\MasterData\InstrumentSetController;
use App\Http\Controllers\Api\V1\MasterData\InstrumentSetItemController;
use App\Http\Controllers\Api\V1\MasterData\ProductController;
use App\Http\Controllers\Api\V1\MasterData\SetInstrumentController;
use App\Http\Controllers\Api\V1\MasterData\SupplierController;
use App\Http\Controllers\Api\V1\MasterData\UserController;
use App\Http\Controllers\Api\V1\QrLabel\PrintJobController;
use App\Http\Controllers\Api\V1\QrLabel\QrLabelController;
use App\Http\Controllers\Api\V1\Reconciliation\ReconciliationController;
use App\Http\Controllers\Api\V1\Reporting\ReportController;
use App\Http\Controllers\Api\V1\ReturnSession\ReturnSessionController;
use App\Http\Controllers\Api\V1\StockIn\StockInItemController;
use App\Http\Controllers\Api\V1\StockIn\StockInSessionController;
use App\Http\Controllers\Api\V1\SupplierReturn\SupplierReturnController;
use Illuminate\Support\Facades\Route;

// Public health-check endpoint (legacy and versioned path support).
Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'API is healthy',
        'status_code' => 200,
        'data' => [
            'status' => 'ok',
        ],
        'timestamp' => now()->toIso8601String(),
    ]);
});

Route::prefix('v1')->group(function () {
    // Public health-check endpoint.
    Route::get('/health', function () {
        return response()->json([
            'success' => true,
            'message' => 'API is healthy',
            'status_code' => 200,
            'data' => [
                'status' => 'ok',
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    });

    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
            Route::get('/permissions', [AuthController::class, 'permissions']);
        });
    });

    // RBAC sanity-check endpoint guarded by permission middleware.
    Route::middleware(['auth:sanctum', 'permission:system.manage_roles'])->get('/rbac/check', function () {
        return response()->json([
            'success' => true,
            'message' => 'RBAC check passed',
            'status_code' => 200,
            'data' => null,
            'timestamp' => now()->toIso8601String(),
        ]);
    });

    Route::get('/dashboard/summary', [DashboardController::class, 'summary'])
        ->middleware(['auth:sanctum', 'permission:dashboard.view']);

    Route::prefix('master-data')->middleware('auth:sanctum')->group(function () {
        Route::get('users', [UserController::class, 'index'])->middleware('permission:system.manage_users');
        Route::get('users/roles', [UserController::class, 'roles'])->middleware('permission:system.manage_users');
        Route::post('users', [UserController::class, 'store'])->middleware('permission:system.manage_users');
        Route::get('users/{user}', [UserController::class, 'show'])->middleware('permission:system.manage_users');
        Route::put('users/{user}', [UserController::class, 'update'])->middleware('permission:system.manage_users');
        Route::patch('users/{user}', [UserController::class, 'update'])->middleware('permission:system.manage_users');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->middleware('permission:system.manage_users');

        Route::get('products', [ProductController::class, 'index'])->middleware('permission:products.view');
        Route::post('products', [ProductController::class, 'store'])->middleware('permission:products.create');
        Route::get('products/{product}', [ProductController::class, 'show'])->middleware('permission:products.view');
        Route::put('products/{product}', [ProductController::class, 'update'])->middleware('permission:products.edit');
        Route::patch('products/{product}', [ProductController::class, 'update'])->middleware('permission:products.edit');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->middleware('permission:products.delete');

        Route::get('suppliers', [SupplierController::class, 'index'])->middleware('permission:suppliers.view,suppliers.manage');
        Route::post('suppliers', [SupplierController::class, 'store'])->middleware('permission:suppliers.manage');
        Route::get('suppliers/{supplier}', [SupplierController::class, 'show'])->middleware('permission:suppliers.view,suppliers.manage');
        Route::put('suppliers/{supplier}', [SupplierController::class, 'update'])->middleware('permission:suppliers.manage');
        Route::patch('suppliers/{supplier}', [SupplierController::class, 'update'])->middleware('permission:suppliers.manage');
        Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy'])->middleware('permission:suppliers.manage');

        Route::get('clients', [ClientController::class, 'index'])->middleware('permission:clients.view,clients.manage');
        Route::post('clients', [ClientController::class, 'store'])->middleware('permission:clients.manage');
        Route::get('clients/{client}', [ClientController::class, 'show'])->middleware('permission:clients.view,clients.manage');
        Route::put('clients/{client}', [ClientController::class, 'update'])->middleware('permission:clients.manage');
        Route::patch('clients/{client}', [ClientController::class, 'update'])->middleware('permission:clients.manage');
        Route::delete('clients/{client}', [ClientController::class, 'destroy'])->middleware('permission:clients.manage');

        Route::get('instrument-sets', [InstrumentSetController::class, 'index'])->middleware('permission:instrument_sets.view,instrument_sets.manage');
        Route::post('instrument-sets', [InstrumentSetController::class, 'store'])->middleware('permission:instrument_sets.manage');
        Route::get('instrument-sets/{instrumentSet}', [InstrumentSetController::class, 'show'])->middleware('permission:instrument_sets.view,instrument_sets.manage');
        Route::put('instrument-sets/{instrumentSet}', [InstrumentSetController::class, 'update'])->middleware('permission:instrument_sets.manage');
        Route::patch('instrument-sets/{instrumentSet}', [InstrumentSetController::class, 'update'])->middleware('permission:instrument_sets.manage');
        Route::delete('instrument-sets/{instrumentSet}', [InstrumentSetController::class, 'destroy'])->middleware('permission:instrument_sets.manage');
        Route::get('instrument-sets/{instrumentSet}/items', [InstrumentSetItemController::class, 'index'])->middleware('permission:instrument_sets.view,instrument_sets.manage');
        Route::post('instrument-sets/{instrumentSet}/items', [InstrumentSetItemController::class, 'store'])->middleware('permission:instrument_sets.manage');
        Route::patch('instrument-sets/{instrumentSet}/items/{instrumentSetItem}', [InstrumentSetItemController::class, 'update'])->middleware('permission:instrument_sets.manage');
        Route::delete('instrument-sets/{instrumentSet}/items/{instrumentSetItem}', [InstrumentSetItemController::class, 'destroy'])->middleware('permission:instrument_sets.manage');

        // Non-product instruments registered directly under a set.
        Route::get('instrument-sets/{instrumentSet}/instruments', [SetInstrumentController::class, 'index'])->middleware('permission:instrument_sets.view,instrument_sets.manage');
        Route::post('instrument-sets/{instrumentSet}/instruments', [SetInstrumentController::class, 'store'])->middleware('permission:instrument_sets.manage');
        Route::patch('instrument-sets/{instrumentSet}/instruments/{setInstrument}', [SetInstrumentController::class, 'update'])->middleware('permission:instrument_sets.manage');
        Route::delete('instrument-sets/{instrumentSet}/instruments/{setInstrument}', [SetInstrumentController::class, 'destroy'])->middleware('permission:instrument_sets.manage');
    });

    Route::prefix('stock-in-sessions')->middleware('auth:sanctum')->group(function () {
        Route::get('/', [StockInSessionController::class, 'index'])->middleware('permission:stock_in.view');
        Route::post('/', [StockInSessionController::class, 'store'])->middleware('permission:stock_in.create');
        Route::get('/{stockIn}', [StockInSessionController::class, 'show'])->middleware('permission:stock_in.view');
        Route::get('/{stockIn}/print', [StockInSessionController::class, 'print'])->middleware('permission:stock_in.view');
        Route::put('/{stockIn}', [StockInSessionController::class, 'update'])->middleware('permission:stock_in.edit_draft');
        Route::patch('/{stockIn}', [StockInSessionController::class, 'update'])->middleware('permission:stock_in.edit_draft');
        Route::get('/{stockIn}/review', [StockInSessionController::class, 'review'])->middleware('permission:stock_in.view');
        Route::post('/{stockIn}/finalize', [StockInSessionController::class, 'finalize'])->middleware('permission:stock_in.confirm');

        Route::get('/{stockIn}/items', [StockInItemController::class, 'index'])->middleware('permission:stock_in.view');
        Route::post('/{stockIn}/items', [StockInItemController::class, 'store'])->middleware('permission:stock_in.edit_draft');
        Route::put('/{stockIn}/items/{stockInItem}', [StockInItemController::class, 'update'])->middleware('permission:stock_in.edit_draft');
        Route::patch('/{stockIn}/items/{stockInItem}', [StockInItemController::class, 'update'])->middleware('permission:stock_in.edit_draft');
        Route::delete('/{stockIn}/items/{stockInItem}', [StockInItemController::class, 'destroy'])->middleware('permission:stock_in.edit_draft');
        Route::patch('/{stockIn}/items/{stockInItem}/correct', [StockInItemController::class, 'correct'])->middleware('permission:stock_in.correct_confirmed');
    });

    Route::middleware('auth:sanctum')->group(function () {
        // -------------------------------------------------------------------------
        // Inventory Units
        //
        // IMPORTANT: static sub-paths (summary, expiring-soon, lookup/*, ledger)
        // MUST be declared BEFORE the wildcard {lot} route to avoid route conflicts.
        // -------------------------------------------------------------------------

        // Dashboard summary: counts per status
        Route::get('inventory-units/summary', [InventoryController::class, 'summary'])
            ->middleware('permission:stock_in.view');

        // Expiry-alert list
        Route::get('inventory-units/expiring-soon', [InventoryController::class, 'expiringSoon'])
            ->middleware('permission:stock_in.view');

        // Exact lot-number lookup (mobile QR scan)
        Route::get('inventory-units/lookup/by-lot/{lotNumber}', [InventoryController::class, 'lookupByLot'])
            ->middleware('permission:stock_in.view');

        // Product ref-num lookup
        Route::get('inventory-units/lookup/by-ref/{refNum}', [InventoryController::class, 'lookupByRef'])
            ->middleware('permission:stock_in.view');

        // Paginated list
        Route::get('inventory-units', [InventoryController::class, 'index'])
            ->middleware('permission:stock_in.view');

        // Single lot detail (route model binding on {lot})
        Route::get('inventory-units/{lot}', [InventoryController::class, 'show'])
            ->middleware('permission:stock_in.view');

        // Per-lot movement timeline
        Route::get('inventory-units/{lot}/movements', [InventoryController::class, 'movements'])
            ->middleware('permission:stock_in.view');

        // Global ledger across all lots
        Route::get('inventory-ledger', [InventoryController::class, 'ledger'])
            ->middleware('permission:stock_in.view');
    });

    // -------------------------------------------------------------------------
    // QR Labels
    // GET  /v1/qr-labels/{lot}         — fetch/create the persisted label for a lot
    // GET  /v1/qr-labels/{lot}/preview — generate payload + TSPL without persisting
    // -------------------------------------------------------------------------
    Route::prefix('qr-labels')->middleware('auth:sanctum')->group(function () {
        Route::get('/{lot}', [QrLabelController::class, 'show'])->middleware('permission:stock_in.view');
        Route::get('/{lot}/preview', [QrLabelController::class, 'preview'])->middleware('permission:stock_in.view');
    });

    // -------------------------------------------------------------------------
    // Print Jobs  (consumed primarily by the Flutter mobile app)
    //
    // GET   /v1/print-jobs                        — list (filter by device_id, status…)
    // GET   /v1/print-jobs/{printJob}             — get single job + TSPL payload
    // POST  /v1/print-jobs                        — create a new print job
    // POST  /v1/print-jobs/reprint                — create a reprint job (reason mandatory)
    // PATCH /v1/print-jobs/{printJob}/mark-printed — mobile confirms successful print
    // PATCH /v1/print-jobs/{printJob}/mark-failed  — mobile reports print failure
    // -------------------------------------------------------------------------
    Route::prefix('print-jobs')->middleware('auth:sanctum')->group(function () {
        Route::get('/', [PrintJobController::class, 'index'])->middleware('permission:stock_in.view');
        Route::post('/reprint', [PrintJobController::class, 'reprint'])->middleware('permission:stock_in.view');
        Route::get('/{printJob}', [PrintJobController::class, 'show'])->middleware('permission:stock_in.view');
        Route::post('/', [PrintJobController::class, 'store'])->middleware('permission:stock_in.view');
        Route::patch('/{printJob}/mark-printed', [PrintJobController::class, 'markPrinted'])->middleware('permission:stock_in.view');
        Route::patch('/{printJob}/mark-failed', [PrintJobController::class, 'markFailed'])->middleware('permission:stock_in.view');
    });

    // -------------------------------------------------------------------------
    // Audit Logs (admin/system-manager only)
    // GET  /v1/audit-logs         — paginated list with filters
    // GET  /v1/audit-logs/{id}    — single entry
    // -------------------------------------------------------------------------
    Route::prefix('audit-logs')->middleware(['auth:sanctum', 'permission:system.manage_roles'])->group(function () {
        Route::get('/', [AuditLogController::class, 'index']);
        Route::get('/{id}', [AuditLogController::class, 'show']);
    });

    // -------------------------------------------------------------------------
    // Error Logs (admin/system-manager only)
    // GET  /v1/error-logs         — paginated list with filters
    // GET  /v1/error-logs/{id}    — single entry
    // -------------------------------------------------------------------------
    Route::prefix('error-logs')->middleware(['auth:sanctum', 'permission:system.manage_roles'])->group(function () {
        Route::get('/', [ErrorLogController::class, 'index']);
        Route::get('/{id}', [ErrorLogController::class, 'show']);
    });

    // =========================================================================
    // WEEK 2 — Consignment (Stock-Out)
    // =========================================================================

    // -------------------------------------------------------------------------
    // Consignment Notes
    // POST   /v1/consignments                            — create draft
    // GET    /v1/consignments                            — list with filters
    // GET    /v1/consignments/{consignment}              — detail with items
    // PUT    /v1/consignments/{consignment}              — update draft header
    // GET    /v1/consignments/{consignment}/review       — review before confirm
    // POST   /v1/consignments/{consignment}/confirm      — confirm (atomic)
    // PUT    /v1/consignments/{consignment}/post-confirm-edit — admin edit (reason required)
    // GET    /v1/consignments/{consignment}/items        — list items
    // POST   /v1/consignments/{consignment}/items        — add item to draft
    // DELETE /v1/consignments/{consignment}/items/{consignmentItem} — remove item
    // -------------------------------------------------------------------------
    Route::prefix('consignments')->middleware('auth:sanctum')->group(function () {
        Route::get('/', [ConsignmentController::class, 'index'])
            ->middleware('permission:consignments.view');
        Route::post('/', [ConsignmentController::class, 'store'])
            ->middleware('permission:consignments.create');
        Route::get('/{consignment}/review', [ConsignmentController::class, 'review'])
            ->middleware('permission:consignments.view');
        Route::get('/{consignment}/print', [ConsignmentController::class, 'print'])
            ->middleware('permission:consignments.view');
        Route::get('/{consignment}', [ConsignmentController::class, 'show'])
            ->middleware('permission:consignments.view');
        Route::put('/{consignment}', [ConsignmentController::class, 'update'])
            ->middleware('permission:consignments.edit_draft');
        Route::patch('/{consignment}', [ConsignmentController::class, 'update'])
            ->middleware('permission:consignments.edit_draft');
        Route::post('/{consignment}/confirm', [ConsignmentController::class, 'confirm'])
            ->middleware('permission:consignments.confirm');
        Route::put('/{consignment}/post-confirm-edit', [ConsignmentController::class, 'postConfirmEdit'])
            ->middleware('permission:consignments.edit_confirmed');

        Route::get('/{consignment}/items', [ConsignmentItemController::class, 'index'])
            ->middleware('permission:consignments.view');
        Route::post('/{consignment}/items', [ConsignmentItemController::class, 'store'])
            ->middleware('permission:consignments.edit_draft');
        Route::delete('/{consignment}/items/{consignmentItem}', [ConsignmentItemController::class, 'destroy'])
            ->middleware('permission:consignments.edit_draft');
    });

    // =========================================================================
    // WEEK 2 — Return Session
    // =========================================================================

    // -------------------------------------------------------------------------
    // Return Sessions
    // POST   /v1/return-sessions                                    — create (linked to confirmed consignment)
    // GET    /v1/return-sessions                                    — list with filters
    // GET    /v1/return-sessions/{returnSession}                    — detail with items
    // POST   /v1/return-sessions/{returnSession}/scan               — scan a returned lot
    // DELETE /v1/return-sessions/{returnSession}/items/{item}       — remove scanned item
    // POST   /v1/return-sessions/{returnSession}/complete           — mark session complete
    // -------------------------------------------------------------------------
    Route::prefix('return-sessions')->middleware('auth:sanctum')->group(function () {
        Route::get('/', [ReturnSessionController::class, 'index'])
            ->middleware('permission:returns.view');
        Route::post('/', [ReturnSessionController::class, 'store'])
            ->middleware('permission:returns.create');
        Route::get('/{returnSession}', [ReturnSessionController::class, 'show'])
            ->middleware('permission:returns.view');
        Route::post('/{returnSession}/scan', [ReturnSessionController::class, 'scan'])
            ->middleware('permission:returns.create');
        Route::delete('/{returnSession}/items/{returnSessionItem}', [ReturnSessionController::class, 'removeItem'])
            ->middleware('permission:returns.create');
        Route::post('/{returnSession}/complete', [ReturnSessionController::class, 'complete'])
            ->middleware('permission:returns.finalize');
    });

    // =========================================================================
    // WEEK 2 — Reconciliation (Used Computation)
    // =========================================================================

    // -------------------------------------------------------------------------
    // Reconciliations
    // POST   /v1/reconciliations                                  — create (linked to completed return session)
    // GET    /v1/reconciliations                                  — list with filters
    // GET    /v1/reconciliations/{reconciliation}                 — detail with items
    // POST   /v1/reconciliations/{reconciliation}/finalize        — finalize: compute used vs returned (atomic)
    // POST   /v1/reconciliations/{reconciliation}/reopen          — admin reopen finalized reconciliation
    // -------------------------------------------------------------------------
    Route::prefix('reconciliations')->middleware('auth:sanctum')->group(function () {
        Route::get('/', [ReconciliationController::class, 'index'])
            ->middleware('permission:returns.view');
        Route::post('/', [ReconciliationController::class, 'store'])
            ->middleware('permission:returns.finalize');
        Route::get('/{reconciliation}', [ReconciliationController::class, 'show'])
            ->middleware('permission:returns.view');
        Route::post('/{reconciliation}/finalize', [ReconciliationController::class, 'finalize'])
            ->middleware('permission:returns.finalize');
        Route::post('/{reconciliation}/reopen', [ReconciliationController::class, 'reopen'])
            ->middleware('permission:returns.reopen_reconciliation');
    });

    // =========================================================================
    // WEEK 3 — Disposal
    // =========================================================================

    // -------------------------------------------------------------------------
    // Disposals
    // POST   /v1/disposals                              — create draft
    // GET    /v1/disposals                              — list with filters
    // GET    /v1/disposals/{disposal}                   — detail with items
    // PUT    /v1/disposals/{disposal}                   — update draft header
    // PATCH  /v1/disposals/{disposal}                   — update draft header
    // GET    /v1/disposals/{disposal}/items             — list items
    // POST   /v1/disposals/{disposal}/items             — add item
    // DELETE /v1/disposals/{disposal}/items/{item}      — remove item
    // POST   /v1/disposals/{disposal}/complete          — complete (atomic)
    // -------------------------------------------------------------------------
    Route::prefix('disposals')->middleware('auth:sanctum')->group(function () {
        Route::get('/', [DisposalController::class, 'index'])
            ->middleware('permission:disposals.view');
        Route::post('/', [DisposalController::class, 'store'])
            ->middleware('permission:disposals.create');
        Route::get('/{disposal}', [DisposalController::class, 'show'])
            ->middleware('permission:disposals.view');
        Route::put('/{disposal}', [DisposalController::class, 'update'])
            ->middleware('permission:disposals.create');
        Route::patch('/{disposal}', [DisposalController::class, 'update'])
            ->middleware('permission:disposals.create');
        Route::get('/{disposal}/items', [DisposalController::class, 'indexItems'])
            ->middleware('permission:disposals.view');
        Route::post('/{disposal}/items', [DisposalController::class, 'storeItem'])
            ->middleware('permission:disposals.create');
        Route::delete('/{disposal}/items/{disposalItem}', [DisposalController::class, 'destroyItem'])
            ->middleware('permission:disposals.create');
        Route::post('/{disposal}/complete', [DisposalController::class, 'complete'])
            ->middleware('permission:disposals.create');
    });

    // =========================================================================
    // WEEK 3 — Supplier Returns
    // =========================================================================

    // -------------------------------------------------------------------------
    // Supplier Returns
    // POST   /v1/supplier-returns                                   — create draft
    // GET    /v1/supplier-returns                                   — list with filters
    // GET    /v1/supplier-returns/{supplierReturn}                  — detail with items
    // PUT    /v1/supplier-returns/{supplierReturn}                  — update draft header
    // PATCH  /v1/supplier-returns/{supplierReturn}                  — update draft header
    // GET    /v1/supplier-returns/{supplierReturn}/items            — list items
    // POST   /v1/supplier-returns/{supplierReturn}/items            — add item
    // DELETE /v1/supplier-returns/{supplierReturn}/items/{item}     — remove item
    // POST   /v1/supplier-returns/{supplierReturn}/complete         — complete (atomic)
    // -------------------------------------------------------------------------
    Route::prefix('supplier-returns')->middleware('auth:sanctum')->group(function () {
        Route::get('/', [SupplierReturnController::class, 'index'])
            ->middleware('permission:supplier_returns.view');
        Route::post('/', [SupplierReturnController::class, 'store'])
            ->middleware('permission:supplier_returns.create');
        Route::get('/{supplierReturn}', [SupplierReturnController::class, 'show'])
            ->middleware('permission:supplier_returns.view');
        Route::put('/{supplierReturn}', [SupplierReturnController::class, 'update'])
            ->middleware('permission:supplier_returns.create');
        Route::patch('/{supplierReturn}', [SupplierReturnController::class, 'update'])
            ->middleware('permission:supplier_returns.create');
        Route::get('/{supplierReturn}/items', [SupplierReturnController::class, 'indexItems'])
            ->middleware('permission:supplier_returns.view');
        Route::post('/{supplierReturn}/items', [SupplierReturnController::class, 'storeItem'])
            ->middleware('permission:supplier_returns.create');
        Route::delete('/{supplierReturn}/items/{supplierReturnItem}', [SupplierReturnController::class, 'destroyItem'])
            ->middleware('permission:supplier_returns.create');
        Route::post('/{supplierReturn}/complete', [SupplierReturnController::class, 'complete'])
            ->middleware('permission:supplier_returns.create');
    });

    // WEEK 3 — Holding Area
    // GET    /v1/holding-area                       — list all holding units (paginated)
    // GET    /v1/holding-area/{lot}                 — detail view
    // POST   /v1/holding-area/{lot}/assign-lot      — assign real lot number & release to available
    Route::prefix('holding-area')->middleware('auth:sanctum')->group(function () {
        Route::get('/', [HoldingAreaController::class, 'index'])
            ->middleware('permission:holding_area.view');
        Route::get('/{lot}', [HoldingAreaController::class, 'show'])
            ->middleware('permission:holding_area.view');
        Route::post('/{lot}/assign-lot', [HoldingAreaController::class, 'assignLot'])
            ->middleware('permission:holding_area.assign_lot');
    });

    // WEEK 3 — Reporting
    // GET  /v1/reports/stock-in                 — stock-in analytics
    // GET  /v1/reports/consignments             — consignment report
    // GET  /v1/reports/returns-analysis         — returns vs used analysis
    // GET  /v1/reports/disposals                — disposal & loss report
    // GET  /v1/reports/expiry                   — expiry dashboard (30/60/90 day windows)
    // POST /v1/reports/{type}/export            — download CSV/XLSX/PDF
    Route::prefix('reports')->middleware('auth:sanctum')->group(function () {
        Route::get('/stock-in', [ReportController::class, 'stockIn'])
            ->middleware('permission:reports.view');
        Route::get('/consignments', [ReportController::class, 'consignments'])
            ->middleware('permission:reports.view');
        Route::get('/returns-analysis', [ReportController::class, 'returnsAnalysis'])
            ->middleware('permission:reports.view');
        Route::get('/disposals', [ReportController::class, 'disposals'])
            ->middleware('permission:reports.view');
        Route::get('/expiry', [ReportController::class, 'expiry'])
            ->middleware('permission:reports.view');
        Route::post('/{type}/export', [ReportController::class, 'export'])
            ->middleware('permission:reports.export');
    });

});
