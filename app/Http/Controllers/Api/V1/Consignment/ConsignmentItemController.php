<?php

namespace App\Http\Controllers\Api\V1\Consignment;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Consignment\StoreConsignmentItemRequest;
use App\Http\Resources\Api\V1\Consignment\ConsignmentItemResource;
use App\Models\Consignment;
use App\Models\ConsignmentItem;
use App\Services\Audit\AuditLogService;
use App\Services\Consignment\ConsignmentItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsignmentItemController extends Controller
{
    public function __construct(
        private readonly ConsignmentItemService $consignmentItemService,
        private readonly AuditLogService $auditLogService
    ) {
    }

    public function index(Consignment $consignment): JsonResponse
    {
        $items = $this->consignmentItemService->listByConsignment($consignment);

        return $this->successResponse(
            ConsignmentItemResource::collection($items),
            'Consignment items fetched successfully'
        );
    }

    public function store(StoreConsignmentItemRequest $request, Consignment $consignment): JsonResponse
    {
        $item = $this->consignmentItemService->addItem(
            $consignment,
            $request->validated(),
            (int) $request->user()->id
        );

        $this->auditLogService->logModelAction(
            auditableType: ConsignmentItem::class,
            auditableId:   $item->id,
            actionType:    AuditAction::CONSIGNMENT_ITEM_ADDED,
            actor:         $request->user(),
            description:   "Added lot {$item->lot?->lot_number} to consignment {$consignment->consignment_no}",
            ipAddress:     (string) $request->ip(),
            deviceId:      $request->header('X-Device-Id'),
            before:        null,
            after:         $item->toArray()
        );

        return $this->successResponse(new ConsignmentItemResource($item), 'Item added to consignment successfully', 201);
    }

    public function destroy(Request $request, Consignment $consignment, ConsignmentItem $consignmentItem): JsonResponse
    {
        $before = $consignmentItem->load('lot:id,lot_number')->toArray();

        $this->consignmentItemService->removeItem($consignment, $consignmentItem);

        $this->auditLogService->logModelAction(
            auditableType: ConsignmentItem::class,
            auditableId:   $consignmentItem->id,
            actionType:    AuditAction::CONSIGNMENT_ITEM_REMOVED,
            actor:         $request->user(),
            description:   "Removed item from consignment {$consignment->consignment_no}",
            ipAddress:     (string) $request->ip(),
            deviceId:      $request->header('X-Device-Id'),
            before:        $before,
            after:         null
        );

        return $this->successResponse(null, 'Item removed from consignment successfully');
    }
}
