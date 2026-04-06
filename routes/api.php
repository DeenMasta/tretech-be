<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\MasterData\UserController;
use App\Http\Controllers\Api\V1\MasterData\ClientController;
use App\Http\Controllers\Api\V1\MasterData\ProductController;
use App\Http\Controllers\Api\V1\MasterData\SupplierController;
use App\Http\Controllers\Api\V1\MasterData\InstrumentSetController;
use App\Http\Controllers\Api\V1\Inventory\InventoryController;
use App\Http\Controllers\Api\V1\StockIn\StockInItemController;
use App\Http\Controllers\Api\V1\StockIn\StockInSessionController;
use App\Http\Controllers\Api\V1\QrLabel\QrLabelController;
use App\Http\Controllers\Api\V1\QrLabel\PrintJobController;
use App\Http\Controllers\Api\V1\Audit\AuditLogController;
use App\Http\Controllers\Api\V1\Audit\ErrorLogController;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);

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

    Route::prefix('master-data')->middleware('auth:sanctum')->group(function () {
        Route::get('users', [UserController::class, 'index'])->middleware('permission:system.manage_users');
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
    });

    Route::prefix('stock-in-sessions')->middleware('auth:sanctum')->group(function () {
        Route::get('/', [StockInSessionController::class, 'index'])->middleware('permission:stock_in.view');
        Route::post('/', [StockInSessionController::class, 'store'])->middleware('permission:stock_in.create');
        Route::get('/{stockIn}', [StockInSessionController::class, 'show'])->middleware('permission:stock_in.view');
        Route::put('/{stockIn}', [StockInSessionController::class, 'update'])->middleware('permission:stock_in.edit_draft');
        Route::patch('/{stockIn}', [StockInSessionController::class, 'update'])->middleware('permission:stock_in.edit_draft');
        Route::get('/{stockIn}/review', [StockInSessionController::class, 'review'])->middleware('permission:stock_in.view');
        Route::post('/{stockIn}/finalize', [StockInSessionController::class, 'finalize'])->middleware('permission:stock_in.confirm');

        Route::get('/{stockIn}/items', [StockInItemController::class, 'index'])->middleware('permission:stock_in.view');
        Route::post('/{stockIn}/items', [StockInItemController::class, 'store'])->middleware('permission:stock_in.edit_draft');
        Route::put('/{stockIn}/items/{stockInItem}', [StockInItemController::class, 'update'])->middleware('permission:stock_in.edit_draft');
        Route::patch('/{stockIn}/items/{stockInItem}', [StockInItemController::class, 'update'])->middleware('permission:stock_in.edit_draft');
        Route::delete('/{stockIn}/items/{stockInItem}', [StockInItemController::class, 'destroy'])->middleware('permission:stock_in.edit_draft');
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
});
