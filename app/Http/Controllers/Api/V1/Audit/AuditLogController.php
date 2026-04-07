<?php

namespace App\Http\Controllers\Api\V1\Audit;

use App\Exceptions\ResourceNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Audit\ListAuditLogsRequest;
use App\Http\Resources\Api\V1\Audit\AuditLogResource;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\JsonResponse;

class AuditLogController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLogService)
    {
    }

    /**
     * GET /audit-logs
     *
     * Paginated list of audit log entries with optional filters.
     *
     * Query params:
     *   user_id         — integer
     *   auditable_type  — e.g. "App\Models\Lot"
     *   auditable_id    — integer
     *   action_type     — e.g. "lot.created"
     *   ip_address      — string
     *   device_id       — string
     *   from_date       — Y-m-d
     *   to_date         — Y-m-d
     *   per_page        — default 20, max 100
     */
    public function index(ListAuditLogsRequest $request): JsonResponse
    {
        $cursor    = $request->string('cursor')->toString() ?: null;
        $paginator = $this->auditLogService->paginate(
            $request->filters(),
            $request->perPage(),
            $cursor !== '' ? $cursor : null
        );

        if ($cursor !== null && $paginator instanceof \Illuminate\Contracts\Pagination\CursorPaginator) {
            return $this->cursorPaginatedResponse(
                items:     AuditLogResource::collection($paginator->items())->resolve(),
                paginator: $paginator,
                message:   'Audit logs fetched successfully'
            );
        }

        /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator */
        return $this->paginatedResponse(
            items:       AuditLogResource::collection($paginator->items())->resolve(),
            total:       $paginator->total(),
            perPage:     $paginator->perPage(),
            currentPage: $paginator->currentPage(),
            message:     'Audit logs fetched successfully'
        );
    }

    /**
     * GET /audit-logs/{id}
     *
     * Retrieve a single audit log entry including user details.
     */
    public function show(int $id): JsonResponse
    {
        $log = $this->auditLogService->find($id);

        if (!$log) {
            throw new ResourceNotFoundException('Audit log not found');
        }

        return $this->successResponse(
            (new AuditLogResource($log))->resolve(),
            'Audit log fetched successfully'
        );
    }
}
