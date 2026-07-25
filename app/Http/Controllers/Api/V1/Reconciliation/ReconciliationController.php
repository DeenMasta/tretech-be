<?php

namespace App\Http\Controllers\Api\V1\Reconciliation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Reconciliation\FinalizeReconciliationRequest;
use App\Http\Requests\Api\V1\Reconciliation\ReopenReconciliationRequest;
use App\Http\Requests\Api\V1\Reconciliation\StoreReconciliationRequest;
use App\Http\Requests\Api\V1\Reconciliation\UpdateReconciliationItemRemarksRequest;
use App\Http\Requests\Api\V1\Reconciliation\UpdateReconciliationSetInstrumentResultRemarksRequest;
use App\Http\Resources\Api\V1\Reconciliation\ReconciliationItemResource;
use App\Http\Resources\Api\V1\Reconciliation\ReconciliationResource;
use App\Models\Reconciliation;
use App\Models\ReconciliationItem;
use App\Models\ReconciliationSetInstrumentResult;
use App\Services\Reconciliation\ReconciliationFinalizeService;
use App\Services\Reconciliation\ReconciliationReopenService;
use App\Services\Reconciliation\ReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Barryvdh\DomPDF\Facade\Pdf;

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
            'reconciliationItems.lot.product:id,ref_num,product_name,product_type',
            'reconciliationItems.lot.instrumentSet:id,set_code,set_name',
            'reconciliationItems.instrumentSet:id,set_code,set_name',
            'reconciliationItems.setInstrumentResults.product:id,ref_num,product_name',
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

    public function updateItemRemarks(UpdateReconciliationItemRemarksRequest $request, Reconciliation $reconciliation, ReconciliationItem $reconciliationItem): JsonResponse
    {
        $item = $this->reconciliationService->updateItemRemarks(
            $reconciliation,
            $reconciliationItem,
            $request->validated(),
            $request->user()
        );

        return $this->successResponse(new ReconciliationItemResource($item), 'Item remarks updated successfully');
    }

    public function updateSetComponentRemarks(
        UpdateReconciliationSetInstrumentResultRemarksRequest $request,
        Reconciliation $reconciliation,
        ReconciliationItem $reconciliationItem,
        ReconciliationSetInstrumentResult $component
    ): JsonResponse {
        $component = $this->reconciliationService->updateSetComponentRemarks(
            $reconciliation,
            $reconciliationItem,
            $component,
            $request->validated(),
            $request->user()
        );

        return $this->successResponse([
            'id' => $component->id,
            'product_id' => $component->product_id,
            'remarks' => $component->remarks,
        ], 'Set component remarks updated successfully');
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

    public function print(Request $request, Reconciliation $reconciliation): Response
    {
        $reconciliation->load([
            'consignment:id,consignment_no,client_id,pic_user_id,consignment_at,surgeon_name,case_date,case_name',
            'consignment.client:id,client_name',
            'consignment.picUser:id,full_name',
            'consignment.consignmentItems:id,consignment_id,entry_kind,instrument_set_id',
            'consignment.consignmentItems.instrumentSet:id,set_code,set_name',
            'returnSession:id,return_session_no',
            'picUser:id,full_name',
            'completedByUser:id,full_name',
            'reopenedByUser:id,full_name',
            'reconciliationItems.lot.product:id,ref_num,product_name,product_type',
            'reconciliationItems.lot.instrumentSet:id,set_code,set_name',
            'reconciliationItems.product:id,ref_num,product_name,product_type',
            'reconciliationItems.instrumentSet:id,set_code,set_name',
            'reconciliationItems.setInstrumentResults.product:id,ref_num,product_name',
        ]);

        $pdf = Pdf::loadView('exports.reconciliation-note', [
            'reconciliation' => $reconciliation,
            'printedBy' => $request->user(),
        ])->setPaper('a4', 'portrait');

        $fileName = sprintf('reconciliation_%s_%s.pdf', $reconciliation->reconciliation_no ?? $reconciliation->id, now()->format('Ymd_His'));

        return $pdf->download($fileName);
    }
}
