<?php

namespace App\Http\Controllers\Api\V1\HoldingArea;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\HoldingArea\AssignLotRequest;
use App\Http\Resources\Api\V1\HoldingArea\HoldingAreaResource;
use App\Models\Lot;
use App\Services\HoldingArea\HoldingAreaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HoldingAreaController extends Controller
{
    public function __construct(
        private readonly HoldingAreaService $holdingAreaService,
    ) {
    }

    /**
     * GET /api/v1/holding-area
     *
     * Paginated list of all lots currently in holding status.
     * Supports filters: search, supplier_id, product_id, from_date, to_date
     */
    public function index(Request $request): JsonResponse
    {
        $perPage   = max(1, min((int) $request->integer('per_page', 15), 100));
        $paginator = $this->holdingAreaService->paginate(
            $request->only(['search', 'supplier_id', 'product_id', 'from_date', 'to_date']),
            $perPage
        );

        return $this->paginatedResponse(
            items:       HoldingAreaResource::collection($paginator->items())->resolve(),
            total:       $paginator->total(),
            perPage:     $paginator->perPage(),
            currentPage: $paginator->currentPage(),
            message:     'Holding area units fetched successfully'
        );
    }

    /**
     * GET /api/v1/holding-area/{lot}
     *
     * Single holding unit detail.
     */
    public function show(Lot $lot): JsonResponse
    {
        if ($lot->status !== 'holding') {
            return $this->errorResponse('This unit is not in holding status.', 422);
        }

        $lot->load([
            'product:id,ref_num,product_name,product_type,uom',
            'supplier:id,supplier_name',
            'lotHolding.assignedByUser:id,full_name',
        ]);

        return $this->successResponse(
            new HoldingAreaResource($lot),
            'Holding unit fetched successfully'
        );
    }

    /**
     * POST /api/v1/holding-area/{lot}/assign-lot
     *
     * Admin-only: assign a real lot number to a holding unit, releasing it to available status.
     * Generates a fresh QR label and queues a print job automatically.
     */
    public function assignLot(AssignLotRequest $request, Lot $lot): JsonResponse
    {
        if ($lot->status !== 'holding') {
            return $this->errorResponse('This unit is not in holding status.', 422);
        }

        $released = $this->holdingAreaService->assignLot(
            $lot,
            $request->validated(),
            $request->user()
        );

        return $this->successResponse(
            new HoldingAreaResource($released),
            'Lot number assigned successfully. A print job has been queued.'
        );
    }
}
