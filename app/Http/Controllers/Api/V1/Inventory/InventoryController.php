<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Inventory\InventoryUnitResource;
use App\Http\Resources\Api\V1\Inventory\LotMovementResource;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function __construct(private readonly InventoryService $inventoryService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->integer('per_page', 15), 100));
        $paginator = $this->inventoryService->paginateLots(
            filters: $request->only(['status', 'supplier_id', 'product_id', 'search']),
            perPage: $perPage
        );

        return $this->paginatedResponse(
            items: InventoryUnitResource::collection($paginator->items())->resolve(),
            total: $paginator->total(),
            perPage: $paginator->perPage(),
            currentPage: $paginator->currentPage(),
            message: 'Inventory units fetched successfully'
        );
    }

    public function show(int $lotId): JsonResponse
    {
        $lot = $this->inventoryService->findLot($lotId);

        if ($lot === null) {
            return $this->notFoundResponse('Inventory unit not found');
        }

        return $this->successResponse(new InventoryUnitResource($lot), 'Inventory unit fetched successfully');
    }

    public function lookupByLot(string $lotNumber): JsonResponse
    {
        $lot = $this->inventoryService->lookupByLotNumber($lotNumber);

        if ($lot === null) {
            return $this->notFoundResponse('Lot number not found');
        }

        return $this->successResponse(new InventoryUnitResource($lot), 'Inventory unit fetched by lot number successfully');
    }

    public function lookupByRef(string $refNum): JsonResponse
    {
        $lots = $this->inventoryService->lookupByRefNum($refNum);

        return $this->successResponse(
            InventoryUnitResource::collection($lots),
            'Inventory units fetched by product reference successfully'
        );
    }

    public function ledger(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->integer('per_page', 15), 100));
        $paginator = $this->inventoryService->paginateLedger(
            filters: $request->only(['lot_id', 'lot_number', 'movement_type', 'from_date', 'to_date']),
            perPage: $perPage
        );

        return $this->paginatedResponse(
            items: LotMovementResource::collection($paginator->items())->resolve(),
            total: $paginator->total(),
            perPage: $paginator->perPage(),
            currentPage: $paginator->currentPage(),
            message: 'Inventory ledger fetched successfully'
        );
    }
}
