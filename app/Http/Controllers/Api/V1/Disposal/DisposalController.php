<?php

namespace App\Http\Controllers\Api\V1\Disposal;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Disposal\ReopenDisposalRequest;
use App\Http\Requests\Api\V1\Disposal\StoreDisposalItemRequest;
use App\Http\Requests\Api\V1\Disposal\StoreDisposalRequest;
use App\Http\Requests\Api\V1\Disposal\UpdateDisposalRequest;
use App\Http\Resources\Api\V1\Disposal\DisposalItemResource;
use App\Http\Resources\Api\V1\Disposal\DisposalResource;
use App\Models\Disposal;
use App\Models\DisposalItem;
use App\Services\Audit\AuditLogService;
use App\Services\Disposal\DisposalCompleteService;
use App\Services\Disposal\DisposalItemService;
use App\Services\Disposal\DisposalReopenService;
use App\Services\Disposal\DisposalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DisposalController extends Controller
{
    public function __construct(
        private readonly DisposalService         $disposalService,
        private readonly DisposalItemService     $disposalItemService,
        private readonly DisposalCompleteService $disposalCompleteService,
        private readonly DisposalReopenService   $disposalReopenService,
        private readonly AuditLogService         $auditLogService,
    ) {
    }

    // -------------------------------------------------------------------------
    // Disposal CRUD
    // -------------------------------------------------------------------------

    public function index(Request $request): JsonResponse
    {
        $perPage    = max(1, min((int) $request->integer('per_page', 15), 100));
        $paginator  = $this->disposalService->paginate(
            $request->only(['search', 'status', 'from_date', 'to_date']),
            $perPage
        );

        return $this->paginatedResponse(
            items:       DisposalResource::collection($paginator->items())->resolve(),
            total:       $paginator->total(),
            perPage:     $paginator->perPage(),
            currentPage: $paginator->currentPage(),
            message:     'Disposals fetched successfully'
        );
    }

    public function store(StoreDisposalRequest $request): JsonResponse
    {
        $disposal = $this->disposalService->create($request->validated(), $request->user());

        $this->auditLogService->logEloquent(
            model:       $disposal,
            actionType:  AuditAction::DISPOSAL_CREATED,
            actor:       $request->user(),
            description: "Disposal {$disposal->disposal_no} created.",
            request:     $request,
            after:       $disposal->toArray(),
        );

        return $this->successResponse(
            new DisposalResource($disposal->load(['picUser:id,full_name'])),
            'Disposal created successfully',
            201
        );
    }

    public function show(Disposal $disposal): JsonResponse
    {
        $disposal->load([
            'picUser:id,full_name',
            'completedByUser:id,full_name',
            'disposalItems.lot.product:id,ref_num,product_name',
            'disposalItems.lot.supplier:id,supplier_name',
        ])->loadCount('disposalItems');

        return $this->successResponse(
            new DisposalResource($disposal),
            'Disposal fetched successfully'
        );
    }

    public function update(UpdateDisposalRequest $request, Disposal $disposal): JsonResponse
    {
        $before   = $disposal->toArray();
        $disposal = $this->disposalService->update($disposal, $request->validated());

        $this->auditLogService->logEloquent(
            model:       $disposal,
            actionType:  AuditAction::DISPOSAL_UPDATED,
            actor:       $request->user(),
            description: "Disposal {$disposal->disposal_no} updated.",
            request:     $request,
            before:      $before,
            after:       $disposal->toArray(),
        );

        return $this->successResponse(
            new DisposalResource($disposal->load(['picUser:id,full_name'])),
            'Disposal updated successfully'
        );
    }

    // -------------------------------------------------------------------------
    // Disposal Items
    // -------------------------------------------------------------------------

    public function indexItems(Disposal $disposal): JsonResponse
    {
        $items = $this->disposalItemService->listByDisposal($disposal);

        return $this->successResponse(
            DisposalItemResource::collection($items),
            'Disposal items fetched successfully'
        );
    }

    public function storeItem(StoreDisposalItemRequest $request, Disposal $disposal): JsonResponse
    {
        $item = $this->disposalItemService->addItem($disposal, $request->validated());

        $item->load(['lot.product:id,ref_num,product_name', 'lot.supplier:id,supplier_name']);

        $this->auditLogService->logModelAction(
            auditableType: DisposalItem::class,
            auditableId:   $item->id,
            actionType:    AuditAction::DISPOSAL_ITEM_ADDED,
            actor:         $request->user(),
            description:   "Added lot [{$item->lot?->lot_number}] to disposal {$disposal->disposal_no}.",
            ipAddress:     (string) $request->ip(),
            deviceId:      $request->header('X-Device-Id'),
            after:         $item->toArray(),
        );

        return $this->successResponse(
            new DisposalItemResource($item),
            'Item added to disposal successfully',
            201
        );
    }

    public function destroyItem(Request $request, Disposal $disposal, DisposalItem $disposalItem): JsonResponse
    {
        $before = $disposalItem->load('lot:id,lot_number')->toArray();

        $this->disposalItemService->removeItem($disposal, $disposalItem);

        $this->auditLogService->logModelAction(
            auditableType: DisposalItem::class,
            auditableId:   $disposalItem->id,
            actionType:    AuditAction::DISPOSAL_ITEM_REMOVED,
            actor:         $request->user(),
            description:   "Removed item from disposal {$disposal->disposal_no}.",
            ipAddress:     (string) $request->ip(),
            deviceId:      $request->header('X-Device-Id'),
            before:        $before,
        );

        return $this->successResponse(null, 'Item removed from disposal successfully');
    }

    // -------------------------------------------------------------------------
    // Complete Disposal
    // -------------------------------------------------------------------------

    public function complete(Request $request, Disposal $disposal): JsonResponse
    {
        $completed = $this->disposalCompleteService->complete($disposal, $request->user());

        $completed->load([
            'picUser:id,full_name',
            'completedByUser:id,full_name',
            'disposalItems.lot.product:id,ref_num,product_name',
        ])->loadCount('disposalItems');

        return $this->successResponse(
            new DisposalResource($completed),
            'Disposal completed successfully'
        );
    }

    public function reopen(ReopenDisposalRequest $request, Disposal $disposal): JsonResponse
    {
        $reopened = $this->disposalReopenService->reopen(
            $disposal,
            $request->validated('reopen_reason'),
            $request->user(),
        );

        $reopened->load([
            'picUser:id,full_name',
            'completedByUser:id,full_name',
            'disposalItems.lot.product:id,ref_num,product_name',
            'disposalItems.lot.supplier:id,supplier_name',
        ])->loadCount('disposalItems');

        return $this->successResponse(
            new DisposalResource($reopened),
            'Disposal reopened successfully'
        );
    }
}
