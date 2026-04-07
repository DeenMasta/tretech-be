<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Inventory\InventoryUnitResource;
use App\Http\Resources\Api\V1\Inventory\LotMovementResource;
use App\Models\Lot;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function __construct(private readonly InventoryService $inventoryService)
    {
    }

    /**
     * GET /inventory-units
     *
     * Paginated inventory list.
     *
     * Query params:
     *   status            — available | supplied | used | disposed | holding
     *   supplier_id       — integer
     *   product_id        — integer
     *   instrument_set_id — integer
     *   expiry_from       — YYYY-MM-DD
     *   expiry_to         — YYYY-MM-DD
     *   search            — lot_number / batch_code / ref_num / product_name
     *   per_page          — default 15, max 100
     */
    public function index(Request $request): JsonResponse
    {
        $perPage   = max(1, min((int) $request->integer('per_page', 15), 100));
        $cursor    = $request->string('cursor')->toString() ?: null;
        $paginator = $this->inventoryService->paginateLots(
            $request->only(['status', 'supplier_id', 'product_id', 'instrument_set_id', 'expiry_from', 'expiry_to', 'search']),
            $perPage,
            $cursor
        );

        if ($cursor !== null && $paginator instanceof \Illuminate\Contracts\Pagination\CursorPaginator) {
            return $this->cursorPaginatedResponse(
                items:     InventoryUnitResource::collection($paginator->items())->resolve(),
                paginator: $paginator,
                message:   'Inventory units fetched successfully'
            );
        }

        /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator */
        return $this->paginatedResponse(
            items: InventoryUnitResource::collection($paginator->items())->resolve(),
            total: $paginator->total(),
            perPage: $paginator->perPage(),
            currentPage: $paginator->currentPage(),
            message: 'Inventory units fetched successfully'
        );
    }

    /**
     * GET /inventory-units/summary
     *
     * Returns count per status — useful for dashboard overview cards.
     */
    public function summary(): JsonResponse
    {
        return $this->successResponse(
            $this->inventoryService->summary(),
            'Inventory summary fetched successfully'
        );
    }

    /**
     * GET /inventory-units/expiring-soon
     *
     * Lots whose expiry_date falls within the next N days.
     *
     * Query params:
     *   days        — look-ahead window in days (default 30, max 365)
     *   status      — narrow to a specific status (default: all except used/disposed)
     *   supplier_id — integer
     *   product_id  — integer
     *   per_page    — default 15, max 100
     */
    public function expiringSoon(Request $request): JsonResponse
    {
        $days    = max(1, min((int) $request->integer('days', 30), 365));
        $perPage = max(1, min((int) $request->integer('per_page', 15), 100));

        $paginator = $this->inventoryService->expiringSoon(
            $days,
            $request->only(['status', 'supplier_id', 'product_id']),
            $perPage
        );

        return $this->paginatedResponse(
            items: InventoryUnitResource::collection($paginator->items())->resolve(),
            total: $paginator->total(),
            perPage: $paginator->perPage(),
            currentPage: $paginator->currentPage(),
            message: "Lots expiring within {$days} days fetched successfully"
        );
    }

    /**
     * GET /inventory-units/lookup/by-lot/{lotNumber}
     *
     * Exact lot-number lookup — used by the mobile QR scan flow.
     * Returns lot + QR payload + holding info if applicable.
     */
    public function lookupByLot(string $lotNumber): JsonResponse
    {
        $lot = $this->inventoryService->lookupByLotNumber($lotNumber);

        if ($lot === null) {
            return $this->notFoundResponse('Lot number not found');
        }

        return $this->successResponse(new InventoryUnitResource($lot), 'Inventory unit fetched by lot number successfully');
    }

    /**
     * GET /inventory-units/lookup/by-ref/{refNum}
     *
     * Look up all lots for a product reference number.
     */
    public function lookupByRef(string $refNum): JsonResponse
    {
        $lots = $this->inventoryService->lookupByRefNum($refNum);

        return $this->successResponse(
            InventoryUnitResource::collection($lots),
            'Inventory units fetched by product reference successfully'
        );
    }

    /**
     * GET /inventory-units/{lot}
     *
     * Full detail for a single lot: product, supplier, QR label, holding, movement count.
     */
    public function show(Lot $lot): JsonResponse
    {
        $lot->load([
            'product:id,ref_num,product_name,product_type,uom',
            'supplier:id,supplier_name',
            'instrumentSet:id,set_name',
            'qrLabel:id,lot_id,qr_payload,generated_at',
            'lotHolding',
        ])->loadCount('lotMovements');

        return $this->successResponse(new InventoryUnitResource($lot), 'Inventory unit fetched successfully');
    }

    /**
     * GET /inventory-units/{lot}/movements
     *
     * Chronological movement timeline for a specific lot.
     *
     * Query params:
     *   movement_type — stock_in | consigned | returned | used | disposed | …
     *   from_date     — YYYY-MM-DD
     *   to_date       — YYYY-MM-DD
     *   per_page      — default 15, max 100
     */
    public function movements(Request $request, Lot $lot): JsonResponse
    {
        $perPage   = max(1, min((int) $request->integer('per_page', 15), 100));
        $paginator = $this->inventoryService->paginateLotMovements(
            $lot,
            $request->only(['movement_type', 'from_date', 'to_date']),
            $perPage
        );

        return $this->paginatedResponse(
            items: LotMovementResource::collection($paginator->items())->resolve(),
            total: $paginator->total(),
            perPage: $paginator->perPage(),
            currentPage: $paginator->currentPage(),
            message: 'Lot movements fetched successfully'
        );
    }

    /**
     * GET /inventory-ledger
     *
     * Global ledger across all lots (for admin / reporting views).
     *
     * Query params:
     *   lot_id        — integer
     *   lot_number    — exact match
     *   movement_type — filter
     *   from_date     — YYYY-MM-DD
     *   to_date       — YYYY-MM-DD
     *   per_page      — default 15, max 100
     */
    public function ledger(Request $request): JsonResponse
    {
        $perPage   = max(1, min((int) $request->integer('per_page', 15), 100));
        $paginator = $this->inventoryService->paginateLedger(
            $request->only(['lot_id', 'lot_number', 'movement_type', 'from_date', 'to_date']),
            $perPage
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
