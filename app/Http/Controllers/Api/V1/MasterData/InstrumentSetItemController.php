<?php

namespace App\Http\Controllers\Api\V1\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MasterData\StoreInstrumentSetItemRequest;
use App\Http\Requests\Api\V1\MasterData\UpdateInstrumentSetItemRequest;
use App\Http\Resources\Api\V1\MasterData\InstrumentSetItemResource;
use App\Models\InstrumentSet;
use App\Models\InstrumentSetItem;
use App\Services\Audit\AuditLogService;
use App\Services\MasterData\InstrumentSetItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InstrumentSetItemController extends Controller
{
    public function __construct(
        private readonly InstrumentSetItemService $instrumentSetItemService,
        private readonly AuditLogService $auditLogService
    ) {
    }

    public function index(InstrumentSet $instrumentSet): JsonResponse
    {
        $items = $this->instrumentSetItemService->listBySet($instrumentSet);

        return $this->successResponse(
            InstrumentSetItemResource::collection($items),
            'Instrument set items fetched successfully'
        );
    }

    public function store(StoreInstrumentSetItemRequest $request, InstrumentSet $instrumentSet): JsonResponse
    {
        $item = $this->instrumentSetItemService->create($instrumentSet, $request->validated());

        $this->auditLogService->logModelAction(
            auditableType: InstrumentSetItem::class,
            auditableId: $item->id,
            actionType: 'create',
            actor: $request->user(),
            description: "Added product {$item->product_id} to instrument set {$instrumentSet->set_name}",
            ipAddress: (string) $request->ip(),
            deviceId: $request->header('X-Device-Id'),
            before: null,
            after: $item->toArray()
        );

        return $this->successResponse(new InstrumentSetItemResource($item), 'Instrument set item created successfully', 201);
    }

    public function update(UpdateInstrumentSetItemRequest $request, InstrumentSet $instrumentSet, InstrumentSetItem $instrumentSetItem): JsonResponse
    {
        $before = $instrumentSetItem->toArray();
        $item = $this->instrumentSetItemService->update($instrumentSet, $instrumentSetItem, $request->validated());

        $this->auditLogService->logModelAction(
            auditableType: InstrumentSetItem::class,
            auditableId: $item->id,
            actionType: 'update',
            actor: $request->user(),
            description: "Updated item {$item->id} in instrument set {$instrumentSet->set_name}",
            ipAddress: (string) $request->ip(),
            deviceId: $request->header('X-Device-Id'),
            before: $before,
            after: $item->toArray()
        );

        return $this->successResponse(new InstrumentSetItemResource($item), 'Instrument set item updated successfully');
    }

    public function destroy(Request $request, InstrumentSet $instrumentSet, InstrumentSetItem $instrumentSetItem): JsonResponse
    {
        $before = $instrumentSetItem->toArray();
        $id = $instrumentSetItem->id;

        $this->instrumentSetItemService->delete($instrumentSet, $instrumentSetItem);

        $this->auditLogService->logModelAction(
            auditableType: InstrumentSetItem::class,
            auditableId: $id,
            actionType: 'delete',
            actor: $request->user(),
            description: "Deleted item {$id} from instrument set {$instrumentSet->set_name}",
            ipAddress: (string) $request->ip(),
            deviceId: $request->header('X-Device-Id'),
            before: $before,
            after: null
        );

        return $this->successResponse(null, 'Instrument set item deleted successfully');
    }
}
