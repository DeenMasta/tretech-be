<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;

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
});
