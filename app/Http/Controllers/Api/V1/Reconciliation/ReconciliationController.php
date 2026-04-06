<?php

namespace App\Http\Controllers\Api\V1\Reconciliation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Reconciliation\FinalizeReconciliationRequest;
use App\Http\Requests\Api\V1\Reconciliation\ReopenReconciliationRequest;
use App\Http\Requests\Api\V1\Reconciliation\StoreReconciliationRequest;
use App\Http\Resources\Api\V1\Reconciliation\ReconciliationResource;
use App\Models\Reconciliation;
use App\Services\Reconciliation\ReconciliationFinalizeService;
use App\Services\Reconciliation\ReconciliationReopenService;
use App\Services\Reconciliation\ReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReconciliationController extends Controller
{
    public function __construct(
        private readonly ReconciliationService $reconciliationService,
        private readonly ReconciliationFinalizeService $reconciliationFinalizeService,
        private readonly ReconciliationReopenService $reconciliationReopenService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->integer('per_page', 15), 100));

        $paginator = $this->reconciliationService->paginate(
            $request->only(['search', 'status', 'consignment_id', 'from_date', 'to_date']),
            $perPage
        );

        return $this->paginatedResponse(
            items: ReconciliationResource::collection($paginator->items())->resolve(),
            total: $paginator->total(),
            perPage: $paginator->perPage(),
            currentPage: $paginator->currentPage(),
            message: 'Reconciliations fetched successfully'
        );
    }

    public function store(StoreReconciliationRequest $request): JsonResponse
    {
        $reconciliation = $this->reconciliationService->create(
            $request->validated(),
            $request->user()
        )->load([
            'consignment:id,consignment_no',
            'returnSession:id,return_session_no',
            'picUser:id,full_name',
        ]);

        return $this->successResponse(
            new ReconciliationResource($reconciliation),
            'Reconciliation created successfully',
            201
        );
    }

    public function show(Reconciliation $reconciliation): JsonResponse
    {
        $reconciliation->load([
            'consignment:id,consignment_no',
            'returnSession:id,return_session_no',
            'picUser:id,full_name',
            'completedByUser:id,full_name',
            'reopenedByUser:id,full_name',
            'reconciliationItems.lot.product:id,ref_num,product_name',
        ])->loadCount('reconciliationItems');

        return $this->successResponse(
            new ReconciliationResource($reconciliation),
            'Reconciliation fetched successfully'
        );
    }

    public function finalize(FinalizeReconciliationRequest $request, Reconciliation $reconciliation): JsonResponse
    {
        $finalized = $this->reconciliationFinalizeService->finalize($reconciliation, $request->user());

        return $this->successResponse(
            new ReconciliationResource($finalized),
            'Reconciliation finalized successfully'
        );
    }

    public function reopen(ReopenReconciliationRequest $request, Reconciliation $reconciliation): JsonResponse
    {
        $reopened = $this->reconciliationReopenService->reopen(
            $reconciliation,
            $request->validated('reopen_reason'),
            $request->user()
        );

        return $this->successResponse(
            new ReconciliationResource($reopened),
            'Reconciliation reopened successfully'
        );
    }
}
