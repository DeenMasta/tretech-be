<?php

namespace App\Http\Controllers\Api\V1\Consignment;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Consignment\PostConfirmEditRequest;
use App\Http\Requests\Api\V1\Consignment\StoreConsignmentRequest;
use App\Http\Requests\Api\V1\Consignment\UpdateConsignmentRequest;
use App\Http\Resources\Api\V1\Consignment\ConsignmentResource;
use App\Models\Consignment;
use App\Models\LotMovement;
use App\Services\Audit\AuditLogService;
use App\Services\Consignment\ConsignmentConfirmService;
use App\Services\Consignment\ConsignmentPostConfirmEditService;
use App\Services\Consignment\ConsignmentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ConsignmentController extends Controller
{
    public function __construct(
        private readonly ConsignmentService $consignmentService,
        private readonly ConsignmentConfirmService $consignmentConfirmService,
        private readonly ConsignmentPostConfirmEditService $postConfirmEditService,
        private readonly AuditLogService $auditLogService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->integer('per_page', 15), 100));

        $paginator = $this->consignmentService->paginate(
            $request->only(['search', 'status', 'client_id', 'from_date', 'to_date', 'has_return_session']),
            $perPage
        );

        return $this->paginatedResponse(
            items: ConsignmentResource::collection($paginator->items())->resolve(),
            total: $paginator->total(),
            perPage: $paginator->perPage(),
            currentPage: $paginator->currentPage(),
            message: 'Consignments fetched successfully'
        );
    }

    public function store(StoreConsignmentRequest $request): JsonResponse
    {
        $consignment = $this->consignmentService->create($request->validated())
            ->load(['client:id,client_name', 'picUser:id,full_name'])
            ->loadCount('consignmentItems');

        $this->auditLogService->logModelAction(
            auditableType: Consignment::class,
            auditableId:   $consignment->id,
            actionType:    AuditAction::CONSIGNMENT_CREATED,
            actor:         $request->user(),
            description:   "Created consignment note {$consignment->consignment_no}",
            ipAddress:     (string) $request->ip(),
            deviceId:      $request->header('X-Device-Id'),
            before:        null,
            after:         $consignment->toArray()
        );

        return $this->successResponse(new ConsignmentResource($consignment), 'Consignment created successfully', 201);
    }

    public function show(Consignment $consignment): JsonResponse
    {
        $consignment->load([
            'client:id,client_name',
            'picUser:id,full_name',
            'confirmedByUser:id,full_name',
            'lastPostConfirmEditByUser:id,full_name',
            'consignmentItems.lot.product:id,ref_num,product_name,product_type',
            'consignmentItems.instrumentSet.instrumentSetItems.product:id,ref_num,product_name',
        ])->loadCount('consignmentItems');

        $consignment->setAttribute('component_lot_numbers_by_product', $this->componentLotNumbers($consignment));

        return $this->successResponse(new ConsignmentResource($consignment), 'Consignment fetched successfully');
    }

    public function update(UpdateConsignmentRequest $request, Consignment $consignment): JsonResponse
    {
        $before = $consignment->toArray();

        $updated = $this->consignmentService->update($consignment, $request->validated())
            ->load(['client:id,client_name', 'picUser:id,full_name'])
            ->loadCount('consignmentItems');

        $this->auditLogService->logModelAction(
            auditableType: Consignment::class,
            auditableId:   $updated->id,
            actionType:    AuditAction::CONSIGNMENT_UPDATED,
            actor:         $request->user(),
            description:   "Updated consignment {$updated->consignment_no}",
            ipAddress:     (string) $request->ip(),
            deviceId:      $request->header('X-Device-Id'),
            before:        $before,
            after:         $updated->toArray()
        );

        return $this->successResponse(new ConsignmentResource($updated), 'Consignment updated successfully');
    }

    public function destroy(Request $request, Consignment $consignment): JsonResponse
    {
        $before = $consignment->toArray();

        $this->consignmentService->delete($consignment);

        $this->auditLogService->logModelAction(
            auditableType: Consignment::class,
            auditableId:   $consignment->id,
            actionType:    AuditAction::CONSIGNMENT_DELETED,
            actor:         $request->user(),
            description:   "Deleted draft consignment {$consignment->consignment_no}",
            ipAddress:     (string) $request->ip(),
            deviceId:      $request->header('X-Device-Id'),
            before:        $before,
            after:         null
        );

        return $this->successResponse(null, 'Consignment deleted successfully');
    }

    public function review(Consignment $consignment): JsonResponse
    {
        $consignment->load([
            'client:id,client_name',
            'picUser:id,full_name',
            'consignmentItems.lot.product:id,ref_num,product_name',
            'consignmentItems.instrumentSet.instrumentSetItems.product:id,ref_num,product_name',
        ])->loadCount('consignmentItems');

        $consignment->setAttribute('component_lot_numbers_by_product', $this->componentLotNumbers($consignment));

        return $this->successResponse(new ConsignmentResource($consignment), 'Consignment review fetched successfully');
    }

    public function confirm(Request $request, Consignment $consignment): JsonResponse
    {
        $confirmed = $this->consignmentConfirmService->confirm($consignment, $request->user());

        return $this->successResponse(new ConsignmentResource($confirmed), 'Consignment confirmed successfully');
    }

    public function postConfirmEdit(PostConfirmEditRequest $request, Consignment $consignment): JsonResponse
    {
        $updated = $this->postConfirmEditService->edit($consignment, $request->validated(), $request->user());

        return $this->successResponse(new ConsignmentResource($updated), 'Consignment updated successfully');
    }

    public function print(Request $request, Consignment $consignment): Response
    {
        $consignment->load([
            'client:id,client_name',
            'picUser:id,full_name',
            'consignmentItems.lot.product:id,ref_num,product_name,product_type',
            'consignmentItems.lot:id,lot_number,product_id',
            'consignmentItems.instrumentSet:id,set_code,set_name',
            'consignmentItems.instrumentSet.instrumentSetItems.product:id,product_name,ref_num',
            'returnSession.returnSessionItems.setInstrumentItems',
        ]);

        // Generic instrument sets are supplied from their individual component
        // lots. Keep the lot traceability visible on the consignment note.
        $componentLotNumbers = $this->componentLotNumbers($consignment);

        $pdf = Pdf::loadView('exports.consignment-note', [
            'consignment' => $consignment,
            'componentLotNumbers' => $componentLotNumbers,
            'printedBy' => $request->user(),
            'surgeon'   => $consignment->surgeon_name ?? $request->query('surgeon', ''),
            'dateCase'  => $consignment->case_date ? $consignment->case_date->format('Y-m-d') : $request->query('date_case', ''),
            'case'      => $consignment->case_name ?? $request->query('case', ''),
        ])->setPaper('a4', 'portrait');

        $fileName = sprintf('consignment_%s_%s.pdf', $consignment->consignment_no, now()->format('Ymd_His'));

        return $pdf->download($fileName);
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function componentLotNumbers(Consignment $consignment): array
    {
        return LotMovement::query()
            ->with('lot:id,product_id,lot_number')
            ->where('reference_type', Consignment::class)
            ->where('reference_id', $consignment->id)
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
    }
}
