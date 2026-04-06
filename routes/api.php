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
        Route::get('inventory-units', [InventoryController::class, 'index'])->middleware('permission:stock_in.view');
        Route::get('inventory-units/lookup/by-lot/{lotNumber}', [InventoryController::class, 'lookupByLot'])->middleware('permission:stock_in.view');
        Route::get('inventory-units/lookup/by-ref/{refNum}', [InventoryController::class, 'lookupByRef'])->middleware('permission:stock_in.view');
        Route::get('inventory-units/{lotId}', [InventoryController::class, 'show'])->middleware('permission:stock_in.view');
        Route::get('inventory-ledger', [InventoryController::class, 'ledger'])->middleware('permission:stock_in.view');
    });
});
