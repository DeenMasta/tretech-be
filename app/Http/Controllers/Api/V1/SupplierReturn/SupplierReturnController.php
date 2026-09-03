<?php

namespace App\Http\Controllers\Api\V1\SupplierReturn;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SupplierReturn\ReopenSupplierReturnRequest;
use App\Http\Requests\Api\V1\SupplierReturn\StoreSupplierReturnItemRequest;
use App\Http\Requests\Api\V1\SupplierReturn\StoreSupplierReturnRequest;
use App\Http\Requests\Api\V1\SupplierReturn\UpdateSupplierReturnRequest;
use App\Http\Resources\Api\V1\SupplierReturn\SupplierReturnItemResource;
use App\Http\Resources\Api\V1\SupplierReturn\SupplierReturnResource;
use App\Models\SupplierReturn;
use App\Models\SupplierReturnItem;
use App\Services\Audit\AuditLogService;
use App\Services\SupplierReturn\SupplierReturnCompleteService;
use App\Services\SupplierReturn\SupplierReturnItemService;
use App\Services\SupplierReturn\SupplierReturnReopenService;
use App\Services\SupplierReturn\SupplierReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierReturnController extends Controller
{
    public function __construct(
        private readonly SupplierReturnService         $supplierReturnService,
        private readonly SupplierReturnItemService     $supplierReturnItemService,
        private readonly SupplierReturnCompleteService $supplierReturnCompleteService,
        private readonly SupplierReturnReopenService   $supplierReturnReopenService,
        private readonly AuditLogService               $auditLogService,
    ) {
    }

    // -------------------------------------------------------------------------
    // Supplier Return CRUD
    // -------------------------------------------------------------------------

    public function index(Request $request): JsonResponse
    {
        $perPage   = max(1, min((int) $request->integer('per_page', 15), 100));
        $paginator = $this->supplierReturnService->paginate(
            $request->only(['search', 'status', 'supplier_id', 'from_date', 'to_date']),
            $perPage
        );

        return $this->paginatedResponse(
            items:       SupplierReturnResource::collection($paginator->items())->resolve(),
            total:       $paginator->total(),
            perPage:     $paginator->perPage(),
            currentPage: $paginator->currentPage(),
            message:     'Supplier returns fetched successfully'
        );
    }

    public function store(StoreSupplierReturnRequest $request): JsonResponse
    {
        $supplierReturn = $this->supplierReturnService->create($request->validated(), $request->user());

        $this->auditLogService->logEloquent(
            model:       $supplierReturn,
            actionType:  AuditAction::SUPPLIER_RETURN_CREATED,
            actor:       $request->user(),
            description: "Supplier return {$supplierReturn->supplier_return_no} created.",
            request:     $request,
            after:       $supplierReturn->toArray(),
        );

        return $this->successResponse(
            new SupplierReturnResource($supplierReturn->load(['supplier:id,supplier_name', 'picUser:id,full_name'])),
            'Supplier return created successfully',
            201
        );
    }

    public function show(SupplierReturn $supplierReturn): JsonResponse
    {
        $supplierReturn->load([
            'supplier:id,supplier_name',
            'picUser:id,full_name',
            'completedByUser:id,full_name',
            'supplierReturnItems.lot.product:id,ref_num,product_name',
            'supplierReturnItems.lot.supplier:id,supplier_name',
        ])->loadCount('supplierReturnItems');

        return $this->successResponse(
            new SupplierReturnResource($supplierReturn),
            'Supplier return fetched successfully'
        );
    }

    public function update(UpdateSupplierReturnRequest $request, SupplierReturn $supplierReturn): JsonResponse
    {
        $before         = $supplierReturn->toArray();
        $supplierReturn = $this->supplierReturnService->update($supplierReturn, $request->validated());

        $this->auditLogService->logEloquent(
            model:       $supplierReturn,
            actionType:  AuditAction::SUPPLIER_RETURN_UPDATED,
            actor:       $request->user(),
            description: "Supplier return {$supplierReturn->supplier_return_no} updated.",
            request:     $request,
            before:      $before,
            after:       $supplierReturn->toArray(),
        );

        return $this->successResponse(
            new SupplierReturnResource($supplierReturn->load(['supplier:id,supplier_name', 'picUser:id,full_name'])),
            'Supplier return updated successfully'
        );
    }

    public function destroy(Request $request, SupplierReturn $supplierReturn): JsonResponse
    {
        $this->supplierReturnService->delete($supplierReturn, $request->user());

        return $this->successResponse(null, 'Supplier return deleted successfully');
    }

    // -------------------------------------------------------------------------
    // Supplier Return Items
    // -------------------------------------------------------------------------

    public function indexItems(SupplierReturn $supplierReturn): JsonResponse
    {
        $items = $this->supplierReturnItemService->listBySupplierReturn($supplierReturn);

        return $this->successResponse(
            SupplierReturnItemResource::collection($items),
            'Supplier return items fetched successfully'
        );
    }

    public function storeItem(StoreSupplierReturnItemRequest $request, SupplierReturn $supplierReturn): JsonResponse
    {
        $item = $this->supplierReturnItemService->addItem($supplierReturn, $request->validated());

        $item->load(['lot.product:id,ref_num,product_name', 'lot.supplier:id,supplier_name']);

        $this->auditLogService->logModelAction(
            auditableType: SupplierReturnItem::class,
            auditableId:   $item->id,
            actionType:    AuditAction::SUPPLIER_RETURN_ITEM_ADDED,
            actor:         $request->user(),
            description:   "Added lot [{$item->lot?->lot_number}] to supplier return {$supplierReturn->supplier_return_no}.",
            ipAddress:     (string) $request->ip(),
            deviceId:      $request->header('X-Device-Id'),
            after:         $item->toArray(),
        );

        return $this->successResponse(
            new SupplierReturnItemResource($item),
            'Item added to supplier return successfully',
            201
        );
    }

    public function destroyItem(Request $request, SupplierReturn $supplierReturn, SupplierReturnItem $supplierReturnItem): JsonResponse
    {
        $before = $supplierReturnItem->load('lot:id,lot_number')->toArray();

        $this->supplierReturnItemService->removeItem($supplierReturn, $supplierReturnItem);

        $this->auditLogService->logModelAction(
            auditableType: SupplierReturnItem::class,
            auditableId:   $supplierReturnItem->id,
            actionType:    AuditAction::SUPPLIER_RETURN_ITEM_REMOVED,
            actor:         $request->user(),
            description:   "Removed item from supplier return {$supplierReturn->supplier_return_no}.",
            ipAddress:     (string) $request->ip(),
            deviceId:      $request->header('X-Device-Id'),
            before:        $before,
        );

        return $this->successResponse(null, 'Item removed from supplier return successfully');
    }

    // -------------------------------------------------------------------------
    // Complete Supplier Return
    // -------------------------------------------------------------------------

    public function complete(Request $request, SupplierReturn $supplierReturn): JsonResponse
    {
        $completed = $this->supplierReturnCompleteService->complete($supplierReturn, $request->user());

        $completed->load([
            'supplier:id,supplier_name',
            'picUser:id,full_name',
            'completedByUser:id,full_name',
            'supplierReturnItems.lot.product:id,ref_num,product_name',
        ])->loadCount('supplierReturnItems');

        return $this->successResponse(
            new SupplierReturnResource($completed),
            'Supplier return completed successfully'
        );
    }

    public function reopen(ReopenSupplierReturnRequest $request, SupplierReturn $supplierReturn): JsonResponse
    {
        $reopened = $this->supplierReturnReopenService->reopen(
            $supplierReturn,
            $request->validated('reopen_reason'),
            $request->user(),
        );

        $reopened->load([
            'supplier:id,supplier_name',
            'picUser:id,full_name',
            'completedByUser:id,full_name',
            'supplierReturnItems.lot.product:id,ref_num,product_name',
            'supplierReturnItems.lot.supplier:id,supplier_name',
        ])->loadCount('supplierReturnItems');

        return $this->successResponse(
            new SupplierReturnResource($reopened),
            'Supplier return reopened successfully'
        );
    }
}
