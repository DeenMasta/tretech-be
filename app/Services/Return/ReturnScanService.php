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

        $isGenericSet = !empty($data['instrument_set_id']);
        $isGenericProduct = !empty($data['product_id']);
        $isLot = !empty($data['lot_id']) || !empty($data['lot_number']);

        if ($isLot) {
            $lot = $this->resolveLot($data);

            $qty = $data['quantity'] ?? 1;

            $consignedItem = ConsignmentItem::query()
                ->where('consignment_id', $session->consignment_id)
                ->where('lot_id', $lot->id)
                ->first();

            if (!$consignedItem) {
                throw new BusinessLogicException(
                    "Lot {$lot->lot_number} was not part of the consignment linked to this return session."
                );
            }

            $alreadyScanned = ReturnSessionItem::query()
                ->where('return_session_id', $session->id)
                ->where('lot_id', $lot->id)
                ->exists();

            if ($alreadyScanned) {
                throw new BusinessLogicException("Lot {$lot->lot_number} has already been scanned in this return session.");
            }

            $totalScannedQty = $qty + ($data['used_quantity'] ?? 0) + ($data['damaged_quantity'] ?? 0) + ($data['missing_quantity'] ?? 0);
            if ($totalScannedQty > ($consignedItem->quantity ?? 1)) {
                throw new BusinessLogicException("Sum of quantities cannot exceed consigned quantity for lot {$lot->lot_number}.");
            }

            if ($lot->isSetInstance() && empty($data['instrument_results'])) {
                throw new BusinessLogicException('Instrument results must be provided when scanning a set instance.');
            }

            $item = ReturnSessionItem::query()->create([
                'return_session_id'    => $session->id,
                'lot_id'               => $lot->id,
                'returned_at'          => now(),
                'returned_by_user_id'  => $actor->id,
                'source_qr_payload'    => $data['source_qr_payload'] ?? null,
                'remarks'              => $data['remarks'] ?? null,
                'quantity'             => $qty,
                'used_quantity'        => $data['used_quantity'] ?? 0,
                'damaged_quantity'     => $data['damaged_quantity'] ?? 0,
                'missing_quantity'     => $data['missing_quantity'] ?? 0,
            ]);

            $description = "Scanned lot {$lot->lot_number} into return session {$session->return_session_no}";
            $isSet = $lot->isSetInstance();

        } else if ($isGenericSet) {
            $setId = (int) $data['instrument_set_id'];

            $qty = (int) ($data['quantity'] ?? 1);

            $belongsToConsignment = ConsignmentItem::query()
                ->where('consignment_id', $session->consignment_id)
                ->where('instrument_set_id', $setId)
                ->whereNull('lot_id')
                ->first();

            if (!$belongsToConsignment) {
                throw new BusinessLogicException("This instrument set was not part of the consignment linked to this return session.");
            }

            $alreadyScanned = ReturnSessionItem::query()
                ->where('return_session_id', $session->id)
                ->where('instrument_set_id', $setId)
                ->exists();

            if ($alreadyScanned) {
                throw new BusinessLogicException("This generic instrument set has already been scanned in this return session.");
            }

            if ($qty > ($belongsToConsignment->quantity ?? 1)) {
                throw new BusinessLogicException("Cannot return more than consigned quantity for this instrument set.");
            }

            if (empty($data['instrument_results'])) {
                throw new BusinessLogicException('Instrument results must be provided when scanning an instrument set.');
            }

            $item = ReturnSessionItem::query()->create([
                'return_session_id'    => $session->id,
                'instrument_set_id'    => $setId,
                'returned_at'          => now(),
                'returned_by_user_id'  => $actor->id,
                'source_qr_payload'    => $data['source_qr_payload'] ?? null,
                'remarks'              => $data['remarks'] ?? null,
                'quantity'             => $qty,
            ]);

            $description = "Scanned generic set into return session {$session->return_session_no}";
            $isSet = true;

        } else if ($isGenericProduct) {
            $productId = (int) $data['product_id'];

            $qty = (int) ($data['quantity'] ?? 1);

            $belongsToConsignment = ConsignmentItem::query()
                ->where('consignment_id', $session->consignment_id)
                ->where('product_id', $productId)
                ->whereNull('lot_id')
                ->first();

            if (!$belongsToConsignment) {
                throw new BusinessLogicException("This product was not part of the consignment linked to this return session.");
            }

            $alreadyScanned = ReturnSessionItem::query()
                ->where('return_session_id', $session->id)
                ->where('product_id', $productId)
                ->exists();

            if ($alreadyScanned) {
                throw new BusinessLogicException("This generic product has already been scanned in this return session.");
            }

            if ($qty > ($belongsToConsignment->quantity ?? 1)) {
                throw new BusinessLogicException("Cannot return more than consigned quantity for this generic product.");
            }

            $item = ReturnSessionItem::query()->create([
                'return_session_id'    => $session->id,
                'product_id'           => $productId,
                'returned_at'          => now(),
                'returned_by_user_id'  => $actor->id,
                'source_qr_payload'    => $data['source_qr_payload'] ?? null,
                'remarks'              => $data['remarks'] ?? null,
                'quantity'             => $qty,
            ]);

            $description = "Scanned generic product into return session {$session->return_session_no}";
            $isSet = false;
        } else {
            throw new BusinessLogicException('No valid item provided for scanning.');
        }

        $this->auditLogService->logModelAction(
            auditableType: ReturnSessionItem::class,
            auditableId:   $item->id,
            actionType:    AuditAction::RETURN_SESSION_ITEM_SCANNED,
            actor:         $actor,
            description:   $description,
            after: $item->toArray(),
        );

        if ($isSet && !empty($data['instrument_results'])) {
            foreach ($data['instrument_results'] as $result) {
                $item->setInstrumentItems()->create([
                    'product_id'        => $result['product_id'] ?? null,
                    'returned_quantity' => $result['returned_quantity'],
                    'remarks'           => null,
                ]);
            }
        }

        return $item->load([
            'lot.product:id,ref_num,product_name',
            'setInstrumentItems.product:id,ref_num,product_name',
            'instrumentSet',
            'product:id,ref_num,product_name',
        ]);
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
