<?php

namespace App\Http\Controllers\Api\V1\Audit;

use App\Exceptions\ResourceNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Audit\ListErrorLogsRequest;
use App\Http\Resources\Api\V1\Audit\ErrorLogResource;
use App\Services\Audit\ErrorLogService;
use Illuminate\Http\JsonResponse;

class ErrorLogController extends Controller
{
    public function __construct(private readonly ErrorLogService $errorLogService)
    {
    }

    /**
     * GET /error-logs
     *
     * Paginated list of error log entries with optional filters.
     *
     * Query params:
     *   source      — e.g. "app", "stock_in.finalize"
     *   source_id   — integer
     *   from_date   — Y-m-d
     *   to_date     — Y-m-d
     *   per_page    — default 20, max 100
     */
    public function index(ListErrorLogsRequest $request): JsonResponse
    {
        $paginator = $this->errorLogService->paginate(
            $request->filters(),
            $request->perPage()
        );

        return $this->paginatedResponse(
            items:       ErrorLogResource::collection($paginator->items())->resolve(),
            total:       $paginator->total(),
            perPage:     $paginator->perPage(),
            currentPage: $paginator->currentPage(),
            message:     'Error logs fetched successfully'
        );
    }

    /**
     * GET /error-logs/{id}
     *
     * Retrieve a single error log entry.
     */
    public function show(int $id): JsonResponse
    {
        $log = $this->errorLogService->find($id);

        if (!$log) {
            throw new ResourceNotFoundException('Error log not found');
        }

        return $this->successResponse(
            (new ErrorLogResource($log))->resolve(),
            'Error log fetched successfully'
        );
    }
}
