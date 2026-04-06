<?php

namespace App\Services\Reconciliation;

use App\Enums\AuditAction;
use App\Exceptions\BusinessLogicException;
use App\Models\Reconciliation;
use App\Models\ReturnSession;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ReconciliationService
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
        $status           = (string) ($filters['status'] ?? '');
        $consignmentId    = $filters['consignment_id'] ?? null;
        $returnSessionId  = $filters['return_session_id'] ?? null;
        $fromDate         = $filters['from_date'] ?? null;
        $toDate           = $filters['to_date'] ?? null;

        return Reconciliation::query()
            ->with([
                'consignment:id,consignment_no',
                'returnSession:id,return_session_no',
                'picUser:id,full_name',
            ])
            ->withCount('reconciliationItems')
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($consignmentId !== null, fn ($q) => $q->where('consignment_id', (int) $consignmentId))
            ->when($returnSessionId !== null, fn ($q) => $q->where('return_session_id', (int) $returnSessionId))
            ->when($fromDate !== null, fn ($q) => $q->whereDate('created_at', '>=', $fromDate))
            ->when($toDate !== null, fn ($q) => $q->whereDate('created_at', '<=', $toDate))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Create a pending reconciliation from a completed return session.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data, User $actor): Reconciliation
    {
        $returnSession = ReturnSession::query()
            ->with('consignment:id,consignment_no,status')
            ->findOrFail((int) $data['return_session_id']);

        if ($returnSession->status !== 'completed') {
            throw new BusinessLogicException('A reconciliation can only be created from a completed return session.');
        }

        if (Reconciliation::query()->where('return_session_id', $returnSession->id)->exists()) {
            throw new BusinessLogicException(
                "A reconciliation already exists for return session {$returnSession->return_session_no}."
            );
        }

        $reconciliation = Reconciliation::query()->create([
            'consignment_id'    => $returnSession->consignment_id,
            'return_session_id' => $returnSession->id,
            'reconciliation_no' => $this->generateReconciliationNo(),
            'pic_user_id'       => $data['pic_user_id'],
            'status'            => 'pending',
            'remarks'           => $data['remarks'] ?? null,
        ]);

        $this->auditLogService->logModelAction(
            auditableType: Reconciliation::class,
            auditableId:   $reconciliation->id,
            actionType:    AuditAction::RECONCILIATION_CREATED,
            actor:         $actor,
            description:   "Created reconciliation {$reconciliation->reconciliation_no} from return session {$returnSession->return_session_no}",
            after: $reconciliation->toArray(),
        );

        return $reconciliation->load([
            'consignment:id,consignment_no',
            'returnSession:id,return_session_no',
            'picUser:id,full_name',
        ]);
    }

    private function generateReconciliationNo(): string
    {
        $datePart = now()->format('Ymd');

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $sequence = str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            $no = "RCN-{$datePart}-{$sequence}";

            if (!Reconciliation::query()->where('reconciliation_no', $no)->exists()) {
                return $no;
            }
        }

        throw new BusinessLogicException('Unable to generate a unique reconciliation number. Please retry.');
    }
}
