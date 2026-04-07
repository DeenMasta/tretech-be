<?php

namespace App\Http\Controllers\Api\V1\UsageSummary;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UsageSummary\GenerateUsageSummaryRequest;
use App\Http\Resources\Api\V1\UsageSummary\UsageSummaryResource;
use App\Models\Reconciliation;
use App\Models\UsageSummary;
use App\Services\Audit\AuditLogService;
use App\Services\Reporting\ExportService;
use App\Services\UsageSummary\UsageSummaryGenerateService;
use App\Services\UsageSummary\UsageSummaryPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsageSummaryController extends Controller
{
    public function __construct(
        private readonly UsageSummaryGenerateService $generateService,
        private readonly UsageSummaryPushService     $pushService,
        private readonly ExportService               $exportService,
        private readonly AuditLogService             $auditLogService,
    ) {
    }

    /**
     * GET /api/v1/usage-summaries
     * List all usage summaries with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->integer('per_page', 15), 100));

        $query = UsageSummary::query()
            ->with([
                'reconciliation:id,reconciliation_no,consignment_id',
                'reconciliation.consignment:id,consignment_no,client_id',
                'reconciliation.consignment.client:id,client_name',
                'generatedByUser:id,full_name',
            ])
            ->withCount('usageSummaryItems');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('from_date')) {
            $query->whereDate('generated_at', '>=', $request->input('from_date'));
        }
        if ($request->filled('to_date')) {
            $query->whereDate('generated_at', '<=', $request->input('to_date'));
        }

        $paginator = $query->orderByDesc('generated_at')->paginate($perPage);

        return $this->paginatedResponse(
            items:       UsageSummaryResource::collection($paginator->items())->resolve(),
            total:       $paginator->total(),
            perPage:     $paginator->perPage(),
            currentPage: $paginator->currentPage(),
            message:     'Usage summaries fetched successfully'
        );
    }

    /**
     * GET /api/v1/usage-summaries/{usageSummary}
     * Show a single usage summary with all items.
     */
    public function show(UsageSummary $usageSummary): JsonResponse
    {
        $usageSummary->load([
            'reconciliation:id,reconciliation_no,status,consignment_id',
            'reconciliation.consignment:id,consignment_no,client_id',
            'reconciliation.consignment.client:id,client_name',
            'generatedByUser:id,full_name',
            'usageSummaryItems.product:id,ref_num,product_name,uom',
            'usageSummaryItems.lot:id,lot_number,supplier_batch_code,expiry_date',
        ]);

        return $this->successResponse(
            new UsageSummaryResource($usageSummary),
            'Usage summary fetched successfully'
        );
    }

    /**
     * POST /api/v1/usage-summaries/generate
     * Manually generate (or regenerate) a usage summary for a finalized reconciliation.
     */
    public function generate(GenerateUsageSummaryRequest $request): JsonResponse
    {
        $reconciliation = Reconciliation::findOrFail($request->integer('reconciliation_id'));
        $summary        = $this->generateService->generate($reconciliation, $request->user());

        return $this->successResponse(
            new UsageSummaryResource($summary),
            'Usage summary generated successfully',
            201
        );
    }

    /**
     * POST /api/v1/usage-summaries/{usageSummary}/push
     * Dispatch a queued ERP push.
     */
    public function push(UsageSummary $usageSummary, Request $request): JsonResponse
    {
        $this->pushService->dispatchPush($usageSummary, $request->user());

        return $this->successResponse(
            ['status' => 'push_pending'],
            'ERP push dispatched. Check push-logs for status updates.'
        );
    }

    /**
     * GET /api/v1/usage-summaries/{usageSummary}/push-logs
     * Return all push attempt logs for a usage summary.
     */
    public function pushLogs(UsageSummary $usageSummary): JsonResponse
    {
        $logs = $usageSummary->usageSummaryPushLogs()
            ->with('pushedByUser:id,full_name')
            ->orderByDesc('pushed_at')
            ->get()
            ->map(fn ($log) => [
                'id'              => $log->id,
                'status'          => $log->status,
                'http_status_code' => $log->http_status_code,
                'pushed_at'       => $log->pushed_at?->toIso8601String(),
                'next_retry_at'   => $log->next_retry_at?->toIso8601String(),
                'retry_count'     => $log->retry_count,
                'error_message'   => $log->error_message,
                'pushed_by'       => $log->pushedByUser?->full_name,
            ]);

        return $this->successResponse($logs, 'Push logs fetched successfully');
    }

    /**
     * POST /api/v1/usage-summaries/{usageSummary}/export
     * Download the usage summary as CSV, XLSX, or PDF.
     * Query param: format=csv|xlsx|pdf
     */
    public function export(UsageSummary $usageSummary, Request $request): mixed
    {
        $format = strtolower($request->input('format', 'xlsx'));

        if (!in_array($format, ExportService::FORMATS, true)) {
            return $this->errorResponse(
                "Unsupported format '{$format}'. Allowed: " . implode(', ', ExportService::FORMATS),
                422
            );
        }

        $usageSummary->load([
            'usageSummaryItems.product:id,ref_num,product_name,uom',
            'usageSummaryItems.lot:id,lot_number,supplier_batch_code,expiry_date',
            'reconciliation.consignment.client:id,client_name',
        ]);

        $rows = $usageSummary->usageSummaryItems->map(fn ($item) => [
            'Product Ref'              => $item->product?->ref_num,
            'Product Name'             => $item->product?->product_name,
            'UOM'                      => $item->product?->uom,
            'Lot Number'               => $item->lot?->lot_number,
            'Batch Code'               => $item->lot?->supplier_batch_code,
            'Expiry Date'              => $item->lot?->expiry_date?->format('Y-m-d'),
            'Qty Consigned'            => $item->qty_consigned,
            'Qty Returned'             => $item->qty_returned,
            'Qty Used'                 => $item->qty_used,
            'Qty Disposed'             => $item->qty_disposed,
            'Qty Returned to Supplier' => $item->qty_returned_to_supplier,
        ])->toArray();

        $summary = [
            'Summary No'     => $usageSummary->summary_no,
            'Client'         => $usageSummary->reconciliation?->consignment?->client?->client_name,
            'Consignment No' => $usageSummary->reconciliation?->consignment?->consignment_no,
            'Generated At'   => $usageSummary->generated_at?->format('Y-m-d H:i'),
        ];

        $this->auditLogService->logModelAction(
            auditableType: UsageSummary::class,
            auditableId:   $usageSummary->id,
            actionType:    AuditAction::USAGE_SUMMARY_EXPORTED,
            actor:         $request->user(),
            description:   "Usage summary {$usageSummary->summary_no} exported as {$format}.",
            after: ['format' => $format],
        );

        return $this->exportService->download('stock-in', $format, $rows, $summary);
    }
}
