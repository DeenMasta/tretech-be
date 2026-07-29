<?php

namespace App\Http\Controllers\Api\V1\Consignment;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Consignment\StoreConsignmentItemRequest;
use App\Http\Requests\Api\V1\Consignment\UpdateConsignmentItemRequest;
use App\Http\Resources\Api\V1\Consignment\ConsignmentItemResource;
use App\Models\Consignment;
use App\Models\ConsignmentItem;
use App\Models\LotMovement;
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
        $componentLotNumbersByProduct = $this->componentLotNumbers($consignment);

        foreach ($items as $item) {
            $item->setAttribute('component_lot_numbers_by_product', $componentLotNumbersByProduct);
        }

        return $this->successResponse(
            ConsignmentItemResource::collection($items),
            'Consignment items fetched successfully'
        );
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

    public function update(UpdateConsignmentItemRequest $request, Consignment $consignment, ConsignmentItem $consignmentItem): JsonResponse
    {
        $before = $consignmentItem->toArray();

        $updatedItem = $this->consignmentItemService->updateItem(
            $consignment,
            $consignmentItem,
            $request->validated()
        );

        $this->auditLogService->logModelAction(
            auditableType: ConsignmentItem::class,
            auditableId:   $updatedItem->id,
            actionType:    AuditAction::CONSIGNMENT_ITEM_UPDATED,
            actor:         $request->user(),
            description:   "Updated item in consignment {$consignment->consignment_no}",
            ipAddress:     (string) $request->ip(),
            deviceId:      $request->header('X-Device-Id'),
            before:        $before,
            after:         $updatedItem->toArray()
        );

        return $this->successResponse(new ConsignmentItemResource($updatedItem), 'Item updated successfully');
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
