<?php

namespace App\Http\Controllers\Api\V1\StockIn;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StockIn\StoreStockInSessionRequest;
use App\Http\Requests\Api\V1\StockIn\UpdateStockInSessionRequest;
use App\Http\Resources\Api\V1\StockIn\LotResource;
use App\Http\Resources\Api\V1\StockIn\StockInSessionResource;
use App\Models\StockIn;
use App\Services\Audit\AuditLogService;
use App\Services\StockIn\StockInFinalizeService;
use App\Services\StockIn\StockInSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockInSessionController extends Controller
{
    public function __construct(
        private readonly StockInSessionService $stockInSessionService,
        private readonly StockInFinalizeService $stockInFinalizeService,
        private readonly AuditLogService $auditLogService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->integer('per_page', 15), 100));
        $paginator = $this->stockInSessionService->paginate(
            $request->only(['search', 'status', 'supplier_id', 'from_date', 'to_date']),
            $perPage
        );

        return $this->paginatedResponse(
            items: StockInSessionResource::collection($paginator->items())->resolve(),
            total: $paginator->total(),
            perPage: $paginator->perPage(),
            currentPage: $paginator->currentPage(),
            message: 'Stock-in sessions fetched successfully'
        );
    }

    public function store(StoreStockInSessionRequest $request): JsonResponse
    {
        $session = $this->stockInSessionService->create($request->validated())
            ->load(['supplier:id,supplier_name', 'picUser:id,full_name'])
            ->loadCount('stockInItems');

        $this->auditLogService->logModelAction(
            auditableType: StockIn::class,
            auditableId: $session->id,
            actionType: 'create',
            actor: $request->user(),
            description: "Created stock-in session {$session->session_no}",
            ipAddress: (string) $request->ip(),
            deviceId: $request->header('X-Device-Id'),
            before: null,
            after: $session->toArray()
        );

        return $this->successResponse(new StockInSessionResource($session), 'Stock-in session created successfully', 201);
    }

    public function show(StockIn $stockIn): JsonResponse
    {
        $stockIn->load([
            'supplier:id,supplier_name',
            'picUser:id,full_name',
            'confirmedByUser:id,full_name',
            'stockInItems.product:id,ref_num,product_name',
            'stockInItems.lot:id,lot_number,status',
        ])->loadCount('stockInItems');

        return $this->successResponse(new StockInSessionResource($stockIn), 'Stock-in session fetched successfully');
    }

    public function update(UpdateStockInSessionRequest $request, StockIn $stockIn): JsonResponse
    {
        $before = $stockIn->toArray();
        $updated = $this->stockInSessionService->update($stockIn, $request->validated())
            ->load(['supplier:id,supplier_name', 'picUser:id,full_name'])
            ->loadCount('stockInItems');

        $this->auditLogService->logModelAction(
            auditableType: StockIn::class,
            auditableId: $updated->id,
            actionType: 'update',
            actor: $request->user(),
            description: "Updated stock-in session {$updated->session_no}",
            ipAddress: (string) $request->ip(),
            deviceId: $request->header('X-Device-Id'),
            before: $before,
            after: $updated->toArray()
        );

        return $this->successResponse(new StockInSessionResource($updated), 'Stock-in session updated successfully');
    }

    public function review(StockIn $stockIn): JsonResponse
    {
        $stockIn->load([
            'supplier:id,supplier_name',
            'picUser:id,full_name',
            'stockInItems.product:id,ref_num,product_name',
            'stockInItems.lot:id,lot_number,status',
        ])->loadCount('stockInItems');

        return $this->successResponse(new StockInSessionResource($stockIn), 'Stock-in review fetched successfully');
    }

    public function finalize(Request $request, StockIn $stockIn): JsonResponse
    {
        $before = $stockIn->toArray();
        $result = $this->stockInFinalizeService->finalize($stockIn, $request->user());
        $finalized = $result['stock_in'];
        $lots = $result['lots'];

        $this->auditLogService->logModelAction(
            auditableType: StockIn::class,
            auditableId: $finalized->id,
            actionType: 'confirm',
            actor: $request->user(),
            description: "Finalized stock-in session {$finalized->session_no}",
            ipAddress: (string) $request->ip(),
            deviceId: $request->header('X-Device-Id'),
            before: $before,
            after: $finalized->toArray()
        );

        return $this->successResponse([
            'session' => new StockInSessionResource($finalized),
            'created_lots_count' => $lots->count(),
            'created_lots' => LotResource::collection($lots),
        ], 'Stock-in session finalized successfully');
    }
}
