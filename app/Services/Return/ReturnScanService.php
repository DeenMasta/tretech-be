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
use Illuminate\Support\Facades\DB;

class ReturnScanService
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {}

    /**
     * Scan / register a returned lot into the return session.
     *
     * @param  array<string, mixed>  $data  Validated data: lot_id OR lot_number, source_qr_payload?, remarks?
     */
    public function scan(ReturnSession $session, array $data, User $actor): ReturnSessionItem
    {
        return DB::transaction(function () use ($session, $data, $actor) {
            // Serialise scans for a session so two scanner requests cannot race
            // past the duplicate lookup and create conflicting rows.
            $session = ReturnSession::query()->lockForUpdate()->findOrFail($session->id);

            if ($session->status !== 'in_progress') {
                throw new BusinessLogicException('Items can only be scanned into an in-progress return session.');
            }

            $isGenericSet = ! empty($data['instrument_set_id']);
            $isGenericProduct = ! empty($data['product_id']);
            $isLot = ! empty($data['lot_id']) || ! empty($data['lot_number']);

            if ($isLot) {
                $lot = $this->resolveLot($data);

                $consignedItem = ConsignmentItem::query()
                    ->where('consignment_id', $session->consignment_id)
                    ->where('lot_id', $lot->id)
                    ->first();

                if (! $consignedItem) {
                    throw new BusinessLogicException(
                        "Lot {$lot->lot_number} was not part of the consignment linked to this return session."
                    );
                }

                $existingItem = ReturnSessionItem::query()
                    ->where('return_session_id', $session->id)
                    ->where('lot_id', $lot->id)
                    ->first();

                $totalScannedQty = $this->combinedQuantity($existingItem, $data);
                if ($totalScannedQty > ($consignedItem->quantity ?? 1)) {
                    throw new BusinessLogicException("Sum of quantities cannot exceed consigned quantity for lot {$lot->lot_number}.");
                }

                if ($lot->isSetInstance() && empty($data['instrument_results'])) {
                    throw new BusinessLogicException('Instrument results must be provided when scanning a set instance.');
                }

                $item = $this->createOrIncrementItem($existingItem, [
                    'return_session_id' => $session->id,
                    'lot_id' => $lot->id,
                    'returned_at' => now(),
                    'returned_by_user_id' => $actor->id,
                    'source_qr_payload' => $data['source_qr_payload'] ?? null,
                    'remarks' => $data['remarks'] ?? null,
                ], $data);

                $description = "Scanned lot {$lot->lot_number} into return session {$session->return_session_no}";
                $isSet = $lot->isSetInstance();

            } elseif ($isGenericSet) {
                $setId = (int) $data['instrument_set_id'];

                $belongsToConsignment = ConsignmentItem::query()
                    ->where('consignment_id', $session->consignment_id)
                    ->where('instrument_set_id', $setId)
                    ->whereNull('lot_id')
                    ->first();

                if (! $belongsToConsignment) {
                    throw new BusinessLogicException('This instrument set was not part of the consignment linked to this return session.');
                }

                $alreadyScanned = ReturnSessionItem::query()
                    ->where('return_session_id', $session->id)
                    ->where('instrument_set_id', $setId)
                    ->exists();

                if ($alreadyScanned) {
                    throw new BusinessLogicException('This generic instrument set has already been scanned in this return session.');
                }

                if ((int) ($data['quantity'] ?? 1) > ($belongsToConsignment->quantity ?? 1)) {
                    throw new BusinessLogicException('Cannot return more than consigned quantity for this instrument set.');
                }

                if (empty($data['instrument_results'])) {
                    throw new BusinessLogicException('Instrument results must be provided when scanning an instrument set.');
                }

                $item = ReturnSessionItem::query()->create([
                    'return_session_id' => $session->id,
                    'instrument_set_id' => $setId,
                    'returned_at' => now(),
                    'returned_by_user_id' => $actor->id,
                    'source_qr_payload' => $data['source_qr_payload'] ?? null,
                    'remarks' => $data['remarks'] ?? null,
                    'quantity' => (int) ($data['quantity'] ?? 1),
                ]);

                $description = "Scanned generic set into return session {$session->return_session_no}";
                $isSet = true;

            } elseif ($isGenericProduct) {
                $productId = (int) $data['product_id'];

                $belongsToConsignment = ConsignmentItem::query()
                    ->where('consignment_id', $session->consignment_id)
                    ->where('product_id', $productId)
                    ->whereNull('lot_id')
                    ->first();

                if (! $belongsToConsignment) {
                    throw new BusinessLogicException('This product was not part of the consignment linked to this return session.');
                }

                $alreadyScanned = ReturnSessionItem::query()
                    ->where('return_session_id', $session->id)
                    ->where('product_id', $productId)
                    ->exists();

                if ($alreadyScanned) {
                    throw new BusinessLogicException('This generic product has already been scanned in this return session.');
                }

                if ((int) ($data['quantity'] ?? 1) > ($belongsToConsignment->quantity ?? 1)) {
                    throw new BusinessLogicException('Cannot return more than consigned quantity for this generic product.');
                }

                $item = ReturnSessionItem::query()->create([
                    'return_session_id' => $session->id,
                    'product_id' => $productId,
                    'returned_at' => now(),
                    'returned_by_user_id' => $actor->id,
                    'source_qr_payload' => $data['source_qr_payload'] ?? null,
                    'remarks' => $data['remarks'] ?? null,
                    'quantity' => (int) ($data['quantity'] ?? 1),
                ]);

                $description = "Scanned generic product into return session {$session->return_session_no}";
                $isSet = false;
            } else {
                throw new BusinessLogicException('No valid item provided for scanning.');
            }

            $this->auditLogService->logModelAction(
                auditableType: ReturnSessionItem::class,
                auditableId: $item->id,
                actionType: AuditAction::RETURN_SESSION_ITEM_SCANNED,
                actor: $actor,
                description: $description,
                after: $item->toArray(),
            );

            if ($isSet && ! empty($data['instrument_results'])) {
                foreach ($data['instrument_results'] as $result) {
                    $component = $item->setInstrumentItems()
                        ->where('product_id', $result['product_id'] ?? null)
                        ->first();

                    if ($component) {
                        $component->increment('returned_quantity', (int) $result['returned_quantity']);
                    } else {
                        $item->setInstrumentItems()->create([
                            'product_id' => $result['product_id'] ?? null,
                            'returned_quantity' => $result['returned_quantity'],
                            'remarks' => null,
                        ]);
                    }
                }
            }

            return $item->load([
                'lot.product:id,ref_num,product_name',
                'setInstrumentItems.product:id,ref_num,product_name',
                'instrumentSet',
                'product:id,ref_num,product_name',
            ]);
        });
    }

    /** @param array<string, mixed> $data */
    private function combinedQuantity(?ReturnSessionItem $existingItem, array $data): int
    {
        return (int) ($existingItem?->quantity ?? 0)
            + (int) ($existingItem?->used_quantity ?? 0)
            + (int) ($existingItem?->damaged_quantity ?? 0)
            + (int) ($existingItem?->missing_quantity ?? 0)
            + (int) ($data['quantity'] ?? 1)
            + (int) ($data['used_quantity'] ?? 0)
            + (int) ($data['damaged_quantity'] ?? 0)
            + (int) ($data['missing_quantity'] ?? 0);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $data
     */
    private function createOrIncrementItem(
        ?ReturnSessionItem $existingItem,
        array $attributes,
        array $data
    ): ReturnSessionItem {
        $quantities = [
            'quantity' => (int) ($data['quantity'] ?? 1),
            'used_quantity' => (int) ($data['used_quantity'] ?? 0),
            'damaged_quantity' => (int) ($data['damaged_quantity'] ?? 0),
            'missing_quantity' => (int) ($data['missing_quantity'] ?? 0),
        ];

        if (! $existingItem) {
            return ReturnSessionItem::query()->create([...$attributes, ...$quantities]);
        }

        foreach ($quantities as $field => $quantity) {
            $existingItem->{$field} = (int) $existingItem->{$field} + $quantity;
        }
        $existingItem->save();

        return $existingItem;
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
            auditableId: $item->id,
            actionType: AuditAction::RETURN_SESSION_ITEM_REMOVED,
            actor: $actor,
            description: "Removed item from return session {$session->return_session_no}",
            before: $before,
        );
    }

    /**
     * Update the editable remarks for a scanned item.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateItemRemarks(ReturnSession $session, ReturnSessionItem $item, array $data, User $actor): ReturnSessionItem
    {
        return DB::transaction(function () use ($session, $item, $data, $actor) {
            $session = ReturnSession::query()->lockForUpdate()->findOrFail($session->id);
            $item = ReturnSessionItem::query()->lockForUpdate()->findOrFail($item->id);

            if ($session->status !== 'in_progress') {
                throw new BusinessLogicException('Items can only be updated in an in-progress return session.');
            }

            if ($item->return_session_id !== $session->id) {
                throw new BusinessLogicException('This item does not belong to the specified return session.');
            }

            $before = $item->toArray();
            $quantityFields = ['quantity', 'used_quantity', 'damaged_quantity', 'missing_quantity'];
            $updatesQuantity = collect($quantityFields)->contains(
                fn (string $field) => array_key_exists($field, $data)
            );

            if ($updatesQuantity) {
                $consignedItemQuery = ConsignmentItem::query()
                    ->where('consignment_id', $session->consignment_id);

                if ($item->lot_id) {
                    $consignedItemQuery->where('lot_id', $item->lot_id);
                } elseif ($item->instrument_set_id) {
                    $consignedItemQuery->where('instrument_set_id', $item->instrument_set_id)->whereNull('lot_id');
                } elseif ($item->product_id) {
                    $consignedItemQuery->where('product_id', $item->product_id)->whereNull('lot_id');
                } else {
                    throw new BusinessLogicException('The return item is not linked to a consigned item.');
                }

                $consignedItem = $consignedItemQuery->first();
                if (! $consignedItem) {
                    throw new BusinessLogicException('The return item is not part of the linked consignment.');
                }

                $quantities = collect($quantityFields)->mapWithKeys(
                    fn (string $field) => [$field => (int) ($data[$field] ?? $item->{$field} ?? 0)]
                );
                $total = $quantities->sum();

                if ($total < 1) {
                    throw new BusinessLogicException('At least one returned quantity is required.');
                }
                if ($total > ($consignedItem->quantity ?? 1)) {
                    throw new BusinessLogicException('Sum of quantities cannot exceed the consigned quantity.');
                }

                $item->fill($quantities->all());
            }

            if (array_key_exists('remarks', $data)) {
                $remarks = trim((string) ($data['remarks'] ?? ''));
                $item->remarks = $remarks === '' ? null : $remarks;
            }
            $item->save();

            $this->auditLogService->logModelAction(
                auditableType: ReturnSessionItem::class,
                auditableId: $item->id,
                actionType: AuditAction::RETURN_SESSION_ITEM_UPDATED,
                actor: $actor,
                description: "Updated item {$item->id} in return session {$session->return_session_no}",
                before: $before,
                after: $item->fresh()->toArray(),
            );

            return $item->refresh()->load([
                'lot.product:id,ref_num,product_name',
                'setInstrumentItems.product:id,ref_num,product_name',
                'instrumentSet',
                'product:id,ref_num,product_name',
            ]);
        });
    }

    /**
     * Resolve a Lot from either lot_id or lot_number.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveLot(array $data): Lot
    {
        if (! empty($data['lot_id'])) {
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
