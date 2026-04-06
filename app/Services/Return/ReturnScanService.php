<?php

namespace App\Services\Return;

use App\Enums\AuditAction;
use App\Exceptions\BusinessLogicException;
use App\Models\ConsignmentItem;
use App\Models\Lot;
use App\Models\ReturnSession;
use App\Models\ReturnSessionItem;
use App\Models\User;
use App\Services\Audit\AuditLogService;

class ReturnScanService
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {
    }

    /**
     * Scan / register a returned lot into the return session.
     *
     * @param array<string, mixed> $data  Validated data: lot_id OR lot_number, source_qr_payload?, remarks?
     */
    public function scan(ReturnSession $session, array $data, User $actor): ReturnSessionItem
    {
        if ($session->status !== 'in_progress') {
            throw new BusinessLogicException('Items can only be scanned into an in-progress return session.');
        }

        $lot = $this->resolveLot($data);

        // Lot must be supplied (i.e. it was consigned)
        if ($lot->status !== 'supplied') {
            throw new BusinessLogicException(
                "Lot {$lot->lot_number} cannot be returned (current status: {$lot->status})."
            );
        }

        // Lot must belong to the consignment linked to this session
        $belongsToConsignment = ConsignmentItem::query()
            ->where('consignment_id', $session->consignment_id)
            ->where('lot_id', $lot->id)
            ->exists();

        if (!$belongsToConsignment) {
            throw new BusinessLogicException(
                "Lot {$lot->lot_number} was not part of the consignment linked to this return session."
            );
        }

        // Prevent duplicate scanning within the same session
        $alreadyScanned = ReturnSessionItem::query()
            ->where('return_session_id', $session->id)
            ->where('lot_id', $lot->id)
            ->exists();

        if ($alreadyScanned) {
            throw new BusinessLogicException("Lot {$lot->lot_number} has already been scanned in this return session.");
        }

        $item = ReturnSessionItem::query()->create([
            'return_session_id'    => $session->id,
            'lot_id'               => $lot->id,
            'returned_at'          => now(),
            'returned_by_user_id'  => $actor->id,
            'source_qr_payload'    => $data['source_qr_payload'] ?? null,
            'remarks'              => $data['remarks'] ?? null,
        ]);

        $this->auditLogService->logModelAction(
            auditableType: ReturnSessionItem::class,
            auditableId:   $item->id,
            actionType:    AuditAction::RETURN_SESSION_ITEM_SCANNED,
            actor:         $actor,
            description:   "Scanned lot {$lot->lot_number} into return session {$session->return_session_no}",
            after: $item->toArray(),
        );

        return $item->load(['lot.product:id,ref_num,product_name']);
    }

    /**
     * Remove a previously scanned item from an in-progress session.
     */
    public function removeItem(ReturnSession $session, ReturnSessionItem $item, User $actor): void
    {
        if ($session->status !== 'in_progress') {
            throw new BusinessLogicException('Items can only be removed from an in-progress return session.');
        }

        if ($item->return_session_id !== $session->id) {
            throw new BusinessLogicException('This item does not belong to the specified return session.');
        }

        $before = $item->load('lot:id,lot_number')->toArray();

        $item->delete();

        $this->auditLogService->logModelAction(
            auditableType: ReturnSessionItem::class,
            auditableId:   $item->id,
            actionType:    AuditAction::RETURN_SESSION_ITEM_REMOVED,
            actor:         $actor,
            description:   "Removed item from return session {$session->return_session_no}",
            before: $before,
        );
    }

    /**
     * Resolve a Lot from either lot_id or lot_number.
     *
     * @param array<string, mixed> $data
     */
    private function resolveLot(array $data): Lot
    {
        if (!empty($data['lot_id'])) {
            return Lot::query()->findOrFail((int) $data['lot_id']);
        }

        $lotNumber = trim((string) ($data['lot_number'] ?? ''));

        if ($lotNumber === '') {
            throw new BusinessLogicException('Either lot_id or lot_number must be provided.');
        }

        $lot = Lot::query()->where('lot_number', $lotNumber)->first();

        if ($lot === null) {
            throw new BusinessLogicException("Lot with number '{$lotNumber}' not found.");
        }

        return $lot;
    }
}
