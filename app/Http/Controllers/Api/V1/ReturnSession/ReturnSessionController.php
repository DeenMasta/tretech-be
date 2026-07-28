<?php

namespace App\Http\Controllers\Api\V1\ReturnSession;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ReturnSession\ScanReturnItemRequest;
use App\Http\Requests\Api\V1\ReturnSession\StoreReturnSessionRequest;
use App\Http\Requests\Api\V1\ReturnSession\UpdateReturnSessionItemRemarksRequest;
use App\Http\Resources\Api\V1\ReturnSession\ReturnSessionItemResource;
use App\Http\Resources\Api\V1\ReturnSession\ReturnSessionResource;
use App\Models\Consignment;
use App\Models\LotMovement;
use App\Models\ReturnSession;
use App\Models\ReturnSessionItem;
use App\Services\Return\ReturnScanService;
use App\Services\Return\ReturnSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Barryvdh\DomPDF\Facade\Pdf;

class ReturnSessionController extends Controller
{
    public function __construct(
        private readonly ReturnSessionService $returnSessionService,
        private readonly ReturnScanService $returnScanService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->integer('per_page', 15), 100));

        $paginator = $this->returnSessionService->paginate(
            $request->only(['status', 'consignment_id', 'from_date', 'to_date']),
            $perPage
        );

        return $this->paginatedResponse(
            items: ReturnSessionResource::collection($paginator->items())->resolve(),
            total: $paginator->total(),
            perPage: $paginator->perPage(),
            currentPage: $paginator->currentPage(),
            message: 'Return sessions fetched successfully'
        );
    }

    public function store(StoreReturnSessionRequest $request): JsonResponse
    {
        $session = $this->returnSessionService->create($request->validated(), $request->user());

        return $this->successResponse(new ReturnSessionResource($session), 'Return session created successfully', 201);
    }

    public function show(ReturnSession $returnSession): JsonResponse
    {
        $returnSession->load([
            'consignment:id,consignment_no',
            'picUser:id,full_name',
            'completedByUser:id,full_name',
            'returnSessionItems.lot.product:id,ref_num,product_name',
            'returnSessionItems.instrumentSet:id,set_name',
            'returnSessionItems.product:id,ref_num,product_name',
            'returnSessionItems.setInstrumentItems.product:id,ref_num,product_name',
            'reconciliation.reconciliationItems.lot.product:id,ref_num,product_name',
            'reconciliation.reconciliationItems.instrumentSet:id,set_name',
            'reconciliation.reconciliationItems.product:id,ref_num,product_name',
            'reconciliation.reconciliationItems.setInstrumentResults.product:id,ref_num,product_name',
            'reconciliation.componentConsignmentMovements.lot:id,product_id,lot_number',
        ])->loadCount('returnSessionItems');

        return $this->successResponse(new ReturnSessionResource($returnSession), 'Return session fetched successfully');
    }

    public function scan(ScanReturnItemRequest $request, ReturnSession $returnSession): JsonResponse
    {
        $item = $this->returnScanService->scan($returnSession, $request->validated(), $request->user());

        return $this->successResponse(new ReturnSessionItemResource($item), 'Item scanned successfully', 201);
    }

    public function removeItem(Request $request, ReturnSession $returnSession, ReturnSessionItem $returnSessionItem): JsonResponse
    {
        $this->returnScanService->removeItem($returnSession, $returnSessionItem, $request->user());

        return $this->successResponse(null, 'Item removed from return session successfully');
    }

    public function updateItemRemarks(UpdateReturnSessionItemRemarksRequest $request, ReturnSession $returnSession, ReturnSessionItem $returnSessionItem): JsonResponse
    {
        $item = $this->returnScanService->updateItemRemarks(
            $returnSession,
            $returnSessionItem,
            $request->validated(),
            $request->user()
        );

        return $this->successResponse(new ReturnSessionItemResource($item), 'Item remarks updated successfully');
    }

    public function complete(Request $request, ReturnSession $returnSession): JsonResponse
    {
        $completed = $this->returnSessionService->completeWithReconciliation($returnSession, $request->user());

        return $this->successResponse(new ReturnSessionResource($completed), 'Return session completed successfully');
    }

    public function reopen(Request $request, ReturnSession $returnSession): JsonResponse
    {
        $reopened = $this->returnSessionService->reopenWithReconciliation(
            $returnSession,
            $request->input('reopen_reason', 'Reopened by user request'),
            $request->user()
        );

        return $this->successResponse(new ReturnSessionResource($reopened), 'Return session reopened successfully');
    }

    public function print(Request $request, ReturnSession $returnSession): Response
    {
        $returnSession->load([
            'reconciliation.consignment:id,consignment_no,client_id,pic_user_id,consignment_at,surgeon_name,case_date,case_name',
            'reconciliation.consignment.client:id,client_name',
            'reconciliation.consignment.picUser:id,full_name',
            'reconciliation.consignment.consignmentItems:id,consignment_id,entry_kind,instrument_set_id',
            'reconciliation.consignment.consignmentItems.instrumentSet:id,set_code,set_name',
            'reconciliation.returnSession:id,return_session_no',
            'reconciliation.picUser:id,full_name',
            'reconciliation.completedByUser:id,full_name',
            'reconciliation.reopenedByUser:id,full_name',
            'reconciliation.reconciliationItems.lot.product:id,ref_num,product_name,product_type',
            'reconciliation.reconciliationItems.lot.instrumentSet:id,set_code,set_name',
            'reconciliation.reconciliationItems.product:id,ref_num,product_name,product_type',
            'reconciliation.reconciliationItems.instrumentSet:id,set_code,set_name',
            'reconciliation.reconciliationItems.setInstrumentResults.product:id,ref_num,product_name',
        ]);

        if (!$returnSession->reconciliation) {
            abort(404, 'No reconciliation report generated for this return session yet.');
        }

        $componentLotNumbers = LotMovement::query()
            ->with('lot:id,product_id,lot_number')
            ->where('reference_type', Consignment::class)
            ->where('reference_id', $returnSession->reconciliation->consignment_id)
            ->where('movement_type', 'consigned')
            ->where('remarks', 'like', 'Set component consigned via%')
            ->get()
            ->groupBy(fn (LotMovement $movement) => $movement->lot?->product_id)
            ->map(fn ($movements) => $movements
                ->pluck('lot.lot_number')
                ->filter()
                ->unique()
                ->values()
                ->all())
            ->all();

        // We use the existing reconciliation-note view, but pass the embedded reconciliation
        $pdf = Pdf::loadView('exports.reconciliation-note', [
            'reconciliation' => $returnSession->reconciliation,
            'componentLotNumbers' => $componentLotNumbers,
            'printedBy' => $request->user(),
        ])->setPaper('a4', 'portrait');

        $fileName = sprintf('return_usage_%s_%s.pdf', $returnSession->return_session_no, now()->format('Ymd_His'));

        return $pdf->download($fileName);
    }
}
