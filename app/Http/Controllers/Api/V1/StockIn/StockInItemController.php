<?php

namespace App\Http\Controllers\Api\V1\StockIn;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StockIn\CorrectStockInItemRequest;
use App\Http\Requests\Api\V1\StockIn\StoreStockInItemRequest;
use App\Http\Requests\Api\V1\StockIn\UpdateStockInItemRequest;
use App\Http\Resources\Api\V1\StockIn\StockInItemResource;
use App\Models\StockIn;
use App\Models\StockInItem;
use App\Services\Audit\AuditLogService;
use App\Services\StockIn\StockInItemCorrectService;
use App\Services\StockIn\StockInItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockInItemController extends Controller
{
    public function __construct(
        private readonly StockInItemService $stockInItemService,
        private readonly StockInItemCorrectService $stockInItemCorrectService,
        private readonly AuditLogService $auditLogService
    ) {
    }

    public function index(StockIn $stockIn): JsonResponse
    {
        $items = $this->stockInItemService->listBySession($stockIn);

        return $this->successResponse(
            StockInItemResource::collection($items),
            'Stock-in items fetched successfully'
        );
    }

    public function store(StoreStockInItemRequest $request, StockIn $stockIn): JsonResponse
    {
        $item = $this->stockInItemService->addItem($stockIn, $request->validated());

        $this->auditLogService->logModelAction(
            auditableType: StockInItem::class,
            auditableId: $item->id,
            actionType: 'create',
            actor: $request->user(),
            description: "Added item {$item->id} to stock-in session {$stockIn->session_no}",
            ipAddress: (string) $request->ip(),
            deviceId: $request->header('X-Device-Id'),
            before: null,
            after: $item->toArray()
        );

        return $this->successResponse(new StockInItemResource($item), 'Stock-in item created successfully', 201);
    }

    public function update(UpdateStockInItemRequest $request, StockIn $stockIn, StockInItem $stockInItem): JsonResponse
    {
        $this->assertBelongsToSession($stockIn, $stockInItem);

        $before = $stockInItem->toArray();
        $updated = $this->stockInItemService->updateItem($stockIn, $stockInItem, $request->validated());

        $this->auditLogService->logModelAction(
            auditableType: StockInItem::class,
            auditableId: $updated->id,
            actionType: 'update',
            actor: $request->user(),
            description: "Updated item {$updated->id} in stock-in session {$stockIn->session_no}",
            ipAddress: (string) $request->ip(),
            deviceId: $request->header('X-Device-Id'),
            before: $before,
            after: $updated->toArray()
        );

        return $this->successResponse(new StockInItemResource($updated), 'Stock-in item updated successfully');
    }

    public function destroy(Request $request, StockIn $stockIn, StockInItem $stockInItem): JsonResponse
    {
        $this->assertBelongsToSession($stockIn, $stockInItem);

        $before = $stockInItem->toArray();
        $this->stockInItemService->deleteItem($stockIn, $stockInItem);

        $this->auditLogService->logModelAction(
            auditableType: StockInItem::class,
            auditableId: $stockInItem->id,
            actionType: 'delete',
            actor: $request->user(),
            description: "Deleted item {$stockInItem->id} from stock-in session {$stockIn->session_no}",
            ipAddress: (string) $request->ip(),
            deviceId: $request->header('X-Device-Id'),
            before: $before,
            after: null
        );

        return $this->successResponse(null, 'Stock-in item deleted successfully');
    }

    /**
     * Admin-only correction of immutable fields (lot_number, manufacturing_date, expiry_date)
     * on a finalized stock-in session item. Requires mandatory admin_reason.
     */
    public function correct(CorrectStockInItemRequest $request, StockIn $stockIn, StockInItem $stockInItem): JsonResponse
    {
        $this->assertBelongsToSession($stockIn, $stockInItem);

        $corrected = $this->stockInItemCorrectService->correct(
            $stockIn,
            $stockInItem,
            $request->validated(),
            $request->user()
        );

        return $this->successResponse(new StockInItemResource($corrected), 'Stock-in item corrected successfully');
    }

    private function assertBelongsToSession(StockIn $stockIn, StockInItem $stockInItem): void
    {
        if ($stockInItem->stock_in_id !== $stockIn->id) {
            abort(404, 'Stock-in item not found for this session.');
        }
    }
}
