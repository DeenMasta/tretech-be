<?php

namespace App\Http\Controllers\Api\V1\StockAudit;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StockAudit\BulkCountStockAuditItemsRequest;
use App\Http\Requests\Api\V1\StockAudit\CancelStockAuditRequest;
use App\Http\Requests\Api\V1\StockAudit\CountStockAuditItemRequest;
use App\Http\Requests\Api\V1\StockAudit\LookupStockAuditItemRequest;
use App\Http\Requests\Api\V1\StockAudit\StoreStockAuditRequest;
use App\Http\Requests\Api\V1\StockAudit\SubmitStockAuditRequest;
use App\Http\Requests\Api\V1\StockAudit\UpdateStockAuditRequest;
use App\Http\Resources\Api\V1\StockAudit\StockAuditItemResource;
use App\Http\Resources\Api\V1\StockAudit\StockAuditResource;
use App\Models\StockAudit;
use App\Models\StockAuditItem;
use App\Services\Audit\AuditLogService;
use App\Services\Reporting\ExportService;
use App\Services\StockAudit\StockAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockAuditController extends Controller
{
    public function __construct(
        private readonly StockAuditService $stockAuditService,
        private readonly AuditLogService $auditLogService,
        private readonly ExportService $exportService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->integer('per_page', 15), 100));
        $paginator = $this->stockAuditService->paginate(
            $request->only(['search', 'status', 'from_date', 'to_date']),
            $perPage,
        );

        return $this->paginatedResponse(
            StockAuditResource::collection($paginator->items())->resolve(),
            $paginator->total(),
            $paginator->perPage(),
            $paginator->currentPage(),
            'Stock audits fetched successfully',
        );
    }

    public function store(StoreStockAuditRequest $request): JsonResponse
    {
        $audit = $this->stockAuditService->create($request->validated());
        $this->log($request, $audit, AuditAction::STOCK_AUDIT_CREATED, "Created stock audit {$audit->audit_no}");

        return $this->successResponse(new StockAuditResource($audit), 'Stock audit created successfully', 201);
    }

    public function show(StockAudit $stockAudit): JsonResponse
    {
        return $this->successResponse(
            new StockAuditResource($this->stockAuditService->find($stockAudit)),
            'Stock audit fetched successfully',
        );
    }

    public function update(UpdateStockAuditRequest $request, StockAudit $stockAudit): JsonResponse
    {
        $before = $stockAudit->toArray();
        $updated = $this->stockAuditService->updateDraft($stockAudit, $request->validated());
        $this->log($request, $updated, AuditAction::STOCK_AUDIT_UPDATED, "Updated stock audit {$updated->audit_no}", $before);

        return $this->successResponse(new StockAuditResource($updated), 'Stock audit updated successfully');
    }

    public function start(Request $request, StockAudit $stockAudit): JsonResponse
    {
        $started = $this->stockAuditService->start($stockAudit, $request->user());
        $this->log($request, $started, AuditAction::STOCK_AUDIT_STARTED, "Started stock audit {$started->audit_no}");

        return $this->successResponse(new StockAuditResource($started), 'Stock audit started successfully');
    }

    public function items(Request $request, StockAudit $stockAudit): JsonResponse
    {
        $perPage = max(1, min((int) $request->integer('per_page', 50), 100));
        $paginator = $this->stockAuditService->paginateItems(
            $stockAudit,
            $request->only(['search', 'result']),
            $perPage,
        );

        return $this->paginatedResponse(
            StockAuditItemResource::collection($paginator->items())->resolve(),
            $paginator->total(),
            $paginator->perPage(),
            $paginator->currentPage(),
            'Stock audit items fetched successfully',
        );
    }

    public function lookup(LookupStockAuditItemRequest $request, StockAudit $stockAudit): JsonResponse
    {
        $item = $this->stockAuditService->lookup($stockAudit, $request->validated());

        return $this->successResponse(new StockAuditItemResource($item), 'Stock audit item resolved successfully');
    }

    public function count(
        CountStockAuditItemRequest $request,
        StockAudit $stockAudit,
        StockAuditItem $stockAuditItem,
    ): JsonResponse {
        $before = $stockAuditItem->toArray();
        $item = $this->stockAuditService->countItem($stockAudit, $stockAuditItem, $request->validated(), $request->user());
        $this->log($request, $stockAudit, AuditAction::STOCK_AUDIT_ITEM_COUNTED, "Counted {$item->snapshot['lot_number']} in {$stockAudit->audit_no}", $before, $item->toArray());

        return $this->successResponse(new StockAuditItemResource($item), 'Physical count saved successfully');
    }

    public function bulkCount(BulkCountStockAuditItemsRequest $request, StockAudit $stockAudit): JsonResponse
    {
        $items = $this->stockAuditService->bulkCountItems(
            $stockAudit,
            $request->validated('items'),
            $request->user(),
        );
        $this->log(
            $request,
            $stockAudit,
            AuditAction::STOCK_AUDIT_ITEM_COUNTED,
            "Saved {$items->count()} stock counts in {$stockAudit->audit_no}",
            after: ['item_ids' => $items->pluck('id')->all()],
        );

        return $this->successResponse(
            StockAuditItemResource::collection($items),
            'Stock count changes saved successfully',
        );
    }

    public function submit(SubmitStockAuditRequest $request, StockAudit $stockAudit): JsonResponse
    {
        $submitted = $this->stockAuditService->submit(
            $stockAudit,
            $request->user(),
        );
        $this->log($request, $submitted, AuditAction::STOCK_AUDIT_SUBMITTED, "Submitted stock audit {$submitted->audit_no} for review");

        return $this->successResponse(new StockAuditResource($submitted), 'Stock audit submitted for review');
    }

    public function conflicts(StockAudit $stockAudit): JsonResponse
    {
        $count = $this->stockAuditService->detectAndMarkConflicts($stockAudit);
        $items = $this->stockAuditService->paginateItems($stockAudit, ['result' => 'movement_conflict'], 100);

        return $this->successResponse([
            'count' => $count,
            'items' => StockAuditItemResource::collection($items->items()),
        ], 'Stock audit conflicts checked successfully');
    }

    public function refreshConflicts(Request $request, StockAudit $stockAudit): JsonResponse
    {
        $updated = $this->stockAuditService->refreshConflicts($stockAudit);
        $this->log($request, $updated, AuditAction::STOCK_AUDIT_CONFLICTS_REFRESHED, "Refreshed conflicts for {$updated->audit_no}");

        return $this->successResponse(new StockAuditResource($updated), 'Conflicts refreshed; affected lines must be recounted');
    }

    public function complete(Request $request, StockAudit $stockAudit): JsonResponse
    {
        $completed = $this->stockAuditService->complete($stockAudit, $request->user());
        $this->log($request, $completed, AuditAction::STOCK_AUDIT_COMPLETED, "Completed stock audit {$completed->audit_no}");

        return $this->successResponse(new StockAuditResource($completed), 'Stock audit completed successfully');
    }

    public function cancel(CancelStockAuditRequest $request, StockAudit $stockAudit): JsonResponse
    {
        $cancelled = $this->stockAuditService->cancel($stockAudit, $request->validated('reason'), $request->user());
        $this->log($request, $cancelled, AuditAction::STOCK_AUDIT_CANCELLED, "Cancelled stock audit {$cancelled->audit_no}");

        return $this->successResponse(new StockAuditResource($cancelled), 'Stock audit cancelled successfully');
    }

    public function report(StockAudit $stockAudit): JsonResponse
    {
        $report = $this->stockAuditService->report($stockAudit);

        return $this->successResponse([
            'audit' => new StockAuditResource($report['audit']),
            'summary' => $report['summary'],
            'items' => StockAuditItemResource::collection($report['items']),
        ], 'Stock audit report fetched successfully');
    }

    public function export(Request $request, StockAudit $stockAudit): mixed
    {
        $validated = $request->validate(['format' => ['required', 'in:csv,xlsx,pdf']]);
        $report = $this->stockAuditService->report($stockAudit);
        $this->log($request, $stockAudit, AuditAction::STOCK_AUDIT_EXPORTED, "Exported stock audit {$stockAudit->audit_no} as {$validated['format']}");

        return $this->exportService->download(
            'stock-audit',
            $validated['format'],
            $report['rows'],
            ['Audit Number' => $stockAudit->audit_no, ...$report['summary']],
        );
    }

    private function log(
        Request $request,
        StockAudit $audit,
        string $action,
        string $description,
        ?array $before = null,
        ?array $after = null,
    ): void {
        $this->auditLogService->logModelAction(
            StockAudit::class,
            $audit->id,
            $action,
            $request->user(),
            $description,
            (string) $request->ip(),
            $request->header('X-Device-Id'),
            $before,
            $after ?? $audit->toArray(),
        );
    }
}
