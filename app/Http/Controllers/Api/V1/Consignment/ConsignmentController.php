<?php

namespace App\Http\Controllers\Api\V1\Consignment;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Consignment\PostConfirmEditRequest;
use App\Http\Requests\Api\V1\Consignment\StoreConsignmentRequest;
use App\Http\Requests\Api\V1\Consignment\UpdateConsignmentRequest;
use App\Http\Resources\Api\V1\Consignment\ConsignmentResource;
use App\Models\Consignment;
use App\Services\Audit\AuditLogService;
use App\Services\Consignment\ConsignmentConfirmService;
use App\Services\Consignment\ConsignmentPostConfirmEditService;
use App\Services\Consignment\ConsignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            $request->only(['search', 'status', 'client_id', 'from_date', 'to_date']),
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
            'consignmentItems.lot.product:id,ref_num,product_name',
        ])->loadCount('consignmentItems');

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

    public function review(Consignment $consignment): JsonResponse
    {
        $consignment->load([
            'client:id,client_name',
            'picUser:id,full_name',
            'consignmentItems.lot.product:id,ref_num,product_name',
        ])->loadCount('consignmentItems');

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
}
