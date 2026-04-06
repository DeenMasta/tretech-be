<?php

namespace App\Http\Controllers\Api\V1\QrLabel;

use App\Exceptions\ResourceNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\QrLabel\CreatePrintJobRequest;
use App\Http\Requests\Api\V1\QrLabel\MarkFailedRequest;
use App\Http\Requests\Api\V1\QrLabel\MarkPrintedRequest;
use App\Http\Requests\Api\V1\QrLabel\ReprintRequest;
use App\Http\Resources\Api\V1\QrLabel\PrintJobResource;
use App\Models\Lot;
use App\Models\QrPrintJob;
use App\Services\Audit\AuditLogService;
use App\Services\QrLabel\PrintJobService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrintJobController extends Controller
{
    public function __construct(
        private readonly PrintJobService $printJobService,
        private readonly AuditLogService $auditLogService
    ) {
    }

    /**
     * GET /print-jobs
     *
     * List print jobs with optional filters.
     * The Flutter app polls this endpoint with ?device_id={id}&status=queued
     * to find jobs assigned to a specific printer device.
     *
     * Query params:
     *   status       - queued | printed | failed
     *   action_type  - print | reprint
     *   lot_id       - filter by lot
     *   device_id    - filter by Bluetooth device ID (key for mobile polling)
     *   from_date    - YYYY-MM-DD
     *   to_date      - YYYY-MM-DD
     *   per_page     - default 15, max 100
     */
    public function index(Request $request): JsonResponse
    {
        $perPage   = max(1, min((int) $request->integer('per_page', 15), 100));
        $paginator = $this->printJobService->paginate(
            $request->only(['status', 'action_type', 'lot_id', 'device_id', 'from_date', 'to_date']),
            $perPage
        );

        return $this->paginatedResponse(
            items: PrintJobResource::collection($paginator->items())->resolve(),
            total: $paginator->total(),
            perPage: $paginator->perPage(),
            currentPage: $paginator->currentPage(),
            message: 'Print jobs fetched successfully'
        );
    }

    /**
     * GET /print-jobs/{printJob}
     *
     * Retrieve a single print job by ID.
     * The Flutter app uses this to get the TSPL payload once it dequeues a job.
     */
    public function show(QrPrintJob $printJob): JsonResponse
    {
        $printJob->load([
            'lot:id,lot_number,product_id',
            'lot.product:id,ref_num,product_name',
            'requestedByUser:id,full_name',
        ]);

        return $this->successResponse(new PrintJobResource($printJob), 'Print job fetched successfully');
    }

    /**
     * POST /print-jobs
     *
     * Create a new print job for an already-finalized lot.
     * Intended for use by the Flutter app when the operator wants to
     * print a label outside the stock-in flow (e.g. re-sending to a
     * different Bluetooth printer).
     *
     * Body: { lot_id, printer_name?, device_id? }
     */
    public function store(CreatePrintJobRequest $request): JsonResponse
    {
        $lot = Lot::findOrFail($request->validated('lot_id'));
        $job = $this->printJobService->createPrintJob(
            lot: $lot,
            actor: $request->user(),
            printerName: $request->validated('printer_name'),
            deviceId: $request->validated('device_id')
        );

        $job->load([
            'lot:id,lot_number,product_id',
            'lot.product:id,ref_num,product_name',
            'requestedByUser:id,full_name',
        ]);

        $this->auditLogService->logModelAction(
            auditableType: QrPrintJob::class,
            auditableId: $job->id,
            actionType: 'create',
            actor: $request->user(),
            description: "Print job created for lot {$lot->lot_number}",
            ipAddress: (string) $request->ip(),
            deviceId: $request->header('X-Device-Id'),
            before: null,
            after: $job->toArray()
        );

        return $this->successResponse(new PrintJobResource($job), 'Print job created successfully', 201);
    }

    /**
     * POST /print-jobs/reprint
     *
     * Request a reprint. A mandatory reason must be supplied.
     * The backend creates a new QrPrintJob with action_type=reprint.
     * The Flutter app then processes the queued job as usual.
     *
     * Body: { lot_id, reason, printer_name?, device_id? }
     */
    public function reprint(ReprintRequest $request): JsonResponse
    {
        $lot = Lot::findOrFail($request->validated('lot_id'));
        $job = $this->printJobService->createReprintJob(
            lot: $lot,
            actor: $request->user(),
            reason: $request->validated('reason'),
            printerName: $request->validated('printer_name'),
            deviceId: $request->validated('device_id')
        );

        $job->load([
            'lot:id,lot_number,product_id',
            'lot.product:id,ref_num,product_name',
            'requestedByUser:id,full_name',
        ]);

        $this->auditLogService->logModelAction(
            auditableType: QrPrintJob::class,
            auditableId: $job->id,
            actionType: 'reprint',
            actor: $request->user(),
            description: "Reprint requested for lot {$lot->lot_number}. Reason: {$request->validated('reason')}",
            ipAddress: (string) $request->ip(),
            deviceId: $request->header('X-Device-Id'),
            before: null,
            after: $job->toArray()
        );

        return $this->successResponse(new PrintJobResource($job), 'Reprint job created successfully', 201);
    }

    /**
     * PATCH /print-jobs/{printJob}/mark-printed
     *
     * Called by the Flutter app after the BLE print succeeds.
     * Transitions the job status from "queued" → "printed".
     */
    public function markPrinted(MarkPrintedRequest $request, QrPrintJob $printJob): JsonResponse
    {
        // If a printer_name was reported by the device, update it
        if ($request->validated('printer_name') !== null) {
            $printJob->fill(['printer_name' => $request->validated('printer_name')])->save();
        }

        $job = $this->printJobService->markPrinted($printJob);

        $this->auditLogService->logModelAction(
            auditableType: QrPrintJob::class,
            auditableId: $job->id,
            actionType: 'update',
            actor: $request->user(),
            description: "Print job #{$job->id} marked as printed",
            ipAddress: (string) $request->ip(),
            deviceId: $request->header('X-Device-Id'),
        );

        return $this->successResponse(new PrintJobResource($job), 'Print job marked as printed');
    }

    /**
     * PATCH /print-jobs/{printJob}/mark-failed
     *
     * Called by the Flutter app when BLE printing fails.
     * Transitions the job status from "queued" → "failed".
     *
     * Body: { error_message }
     */
    public function markFailed(MarkFailedRequest $request, QrPrintJob $printJob): JsonResponse
    {
        $job = $this->printJobService->markFailed($printJob, $request->validated('error_message'));

        $this->auditLogService->logModelAction(
            auditableType: QrPrintJob::class,
            auditableId: $job->id,
            actionType: 'update',
            actor: $request->user(),
            description: "Print job #{$job->id} marked as failed: {$request->validated('error_message')}",
            ipAddress: (string) $request->ip(),
            deviceId: $request->header('X-Device-Id'),
        );

        return $this->successResponse(new PrintJobResource($job), 'Print job marked as failed');
    }
}
