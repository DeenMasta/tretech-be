<?php

namespace App\Services\Consignment;

use App\Enums\AuditAction;
use App\Exceptions\BusinessLogicException;
use App\Models\Consignment;
use App\Models\User;
use App\Services\Audit\AuditLogService;

class ConsignmentPostConfirmEditService
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {
    }

    /**
     * @param array<string, mixed> $data  Validated data: remarks?, reason (mandatory)
     */
    public function edit(Consignment $consignment, array $data, User $actor): Consignment
    {
        if ($consignment->status !== 'confirmed') {
            throw new BusinessLogicException('Post-confirmation edits are only allowed on confirmed consignments.');
        }

        $reason = (string) ($data['reason'] ?? '');
        if ($reason === '') {
            throw new BusinessLogicException('A reason is mandatory for post-confirmation edits.');
        }

        $before = $consignment->toArray();

        $updatePayload = [
            'edited_after_confirmation'           => true,
            'last_post_confirm_edit_at'            => now(),
            'last_post_confirm_edit_by_user_id'    => $actor->id,
            'last_post_confirm_edit_reason'        => $reason,
        ];

        if (array_key_exists('remarks', $data)) {
            $updatePayload['remarks'] = $data['remarks'];
        }

        $consignment->fill($updatePayload)->save();

        $this->auditLogService->logModelAction(
            auditableType: Consignment::class,
            auditableId:   $consignment->id,
            actionType:    AuditAction::CONSIGNMENT_POST_CONFIRM_EDIT,
            actor:         $actor,
            description:   "Post-confirmation edit on consignment {$consignment->consignment_no}: {$reason}",
            before: $before,
            after: $consignment->refresh()->toArray(),
        );

        return $consignment->refresh()->load([
            'client:id,client_name',
            'picUser:id,full_name',
            'confirmedByUser:id,full_name',
            'lastPostConfirmEditByUser:id,full_name',
            'consignmentItems.lot:id,lot_number,status',
        ]);
    }
}
