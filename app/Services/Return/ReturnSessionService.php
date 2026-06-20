<?php

namespace App\Services\Return;

use App\Enums\AuditAction;
use App\Exceptions\BusinessLogicException;
use App\Models\Consignment;
use App\Models\LotMovement;
use App\Models\ReturnSession;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ReturnSessionService
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $status        = (string) ($filters['status'] ?? '');
        $consignmentId = $filters['consignment_id'] ?? null;
        $fromDate      = $filters['from_date'] ?? null;
        $toDate        = $filters['to_date'] ?? null;

        return ReturnSession::query()
            ->with([
                'consignment:id,consignment_no',
                'picUser:id,full_name',
            ])
            ->withCount('returnSessionItems')
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($consignmentId !== null, fn ($q) => $q->where('consignment_id', (int) $consignmentId))
            ->when($fromDate !== null, fn ($q) => $q->whereDate('started_at', '>=', $fromDate))
            ->when($toDate !== null, fn ($q) => $q->whereDate('started_at', '<=', $toDate))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Create a return session linked to a confirmed consignment.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data, User $actor): ReturnSession
    {
        $consignment = Consignment::query()->findOrFail((int) $data['consignment_id']);

        if ($consignment->status !== 'confirmed') {
            throw new BusinessLogicException('A return session can only be created for a confirmed consignment.');
        }

        // One return session per consignment (enforced by unique key in DB too)
        if (ReturnSession::query()->where('consignment_id', $consignment->id)->exists()) {
            throw new BusinessLogicException("A return session already exists for consignment {$consignment->consignment_no}.");
        }

        $session = ReturnSession::query()->create([
            'consignment_id'      => $consignment->id,
            'return_session_no'   => $this->generateSessionNo(),
            'pic_user_id'         => $data['pic_user_id'],
            'status'              => 'in_progress',
            'remarks'             => $data['remarks'] ?? null,
            'started_at'          => now(),
        ]);

        $this->auditLogService->logModelAction(
            auditableType: ReturnSession::class,
            auditableId:   $session->id,
            actionType:    AuditAction::RETURN_SESSION_CREATED,
            actor:         $actor,
            description:   "Created return session {$session->return_session_no} for consignment {$consignment->consignment_no}",
            after: $session->toArray(),
        );

        return $session->load([
            'consignment:id,consignment_no',
            'picUser:id,full_name',
        ]);
    }

    /**
     * Mark a return session as completed.
     */
    public function complete(ReturnSession $session, User $actor): ReturnSession
    {
        return DB::transaction(function () use ($session, $actor) {
            /** @var ReturnSession $locked */
            $locked = ReturnSession::query()
                ->lockForUpdate()
                ->with(['returnSessionItems'])
                ->findOrFail($session->id);

            if ($locked->status !== 'in_progress') {
                throw new BusinessLogicException('Only in-progress return sessions can be completed.');
            }

            $locked->fill([
                'status'               => 'completed',
                'completed_at'         => now(),
                'completed_by_user_id' => $actor->id,
            ])->save();

            $this->auditLogService->logModelAction(
                auditableType: ReturnSession::class,
                auditableId:   $locked->id,
                actionType:    AuditAction::RETURN_SESSION_COMPLETED,
                actor:         $actor,
                description:   sprintf(
                    'Return session %s completed — %d item(s) returned.',
                    $locked->return_session_no,
                    $locked->returnSessionItems->count()
                ),
                after: [
                    'status'       => 'completed',
                    'completed_at' => now()->toIso8601String(),
                    'total_items'  => $locked->returnSessionItems->count(),
                ],
            );

            return $locked->refresh()->load([
                'consignment:id,consignment_no',
                'picUser:id,full_name',
                'completedByUser:id,full_name',
            ]);
        });
    }

    private function generateSessionNo(): string
    {
        $yearPart = now()->format('y');
        $prefix = "TCNR{$yearPart}-";

        $lastSession = ReturnSession::query()
            ->where('return_session_no', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->first();

        $nextSequence = 1;
        if ($lastSession) {
            $lastSequence = (int) substr($lastSession->return_session_no, strlen($prefix));
            $nextSequence = max(1, $lastSequence + 1);
        }

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $sequenceStr = str_pad((string) $nextSequence, 4, '0', STR_PAD_LEFT);
            $no = "{$prefix}{$sequenceStr}";

            if (!ReturnSession::query()->where('return_session_no', $no)->exists()) {
                return $no;
            }
            
            $nextSequence++;
        }

        throw new BusinessLogicException('Unable to generate a unique return session number. Please retry.');
    }
}
