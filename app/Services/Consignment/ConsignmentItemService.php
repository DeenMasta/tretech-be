<?php

namespace App\Services\Consignment;

use App\Exceptions\BusinessLogicException;
use App\Models\Consignment;
use App\Models\ConsignmentItem;
use App\Models\InstrumentSet;
use App\Models\Lot;
use Illuminate\Database\Eloquent\Collection;

class ConsignmentItemService
{
    /**
     * @return Collection<int, ConsignmentItem>
     */
    public function listByConsignment(Consignment $consignment): Collection
    {
        return ConsignmentItem::query()
            ->where('consignment_id', $consignment->id)
            ->with([
                'lot:id,product_id,lot_number,status,quantity_available,expiry_date',
                'lot.product:id,ref_num,product_name,product_type',
                'instrumentSet:id,set_code,set_name',
                'instrumentSet.instrumentSetItems.product:id,product_name,ref_num',
            ])
            ->get();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function addItem(Consignment $consignment, array $data, int $actorId): ConsignmentItem
    {
        if ($consignment->status !== 'draft') {
            throw new BusinessLogicException('Items can only be added to draft consignments.');
        }

        $entryKind = ($data['entry_kind'] ?? 'lot') === 'set' ? 'set' : 'lot';

        if ($entryKind === 'set') {
            return $this->addSetItem($consignment, $data, $actorId);
        }

        return $this->addLotItem($consignment, $data, $actorId);
    }

    /**
     * Lot-based consignment item: selects an available lot from inventory.
     *
     * @param array<string, mixed> $data
     */
    private function addLotItem(Consignment $consignment, array $data, int $actorId): ConsignmentItem
    {
        $lot = Lot::query()->findOrFail((int) $data['lot_id']);
        
        $proposedQty = $data['proposed_quantity'] ?? 1;
        $qty = $data['quantity'] ?? 1;

        if ($lot->status !== 'available') {
            throw new BusinessLogicException('Only available lots can be added to a consignment.');
        }

        if (!$lot->hasAvailableStock($qty)) {
            throw new BusinessLogicException(
                "Lot {$lot->lot_number} does not have enough available stock (requested: {$qty}, available: {$lot->quantity_available})."
            );
        }

        $existingItem = ConsignmentItem::query()
            ->where('consignment_id', $consignment->id)
            ->where('lot_id', $lot->id)
            ->first();

        if ($existingItem) {
            $existingItem->proposed_quantity += $proposedQty;
            $existingItem->quantity += $qty;
            $existingItem->save();
            $item = $existingItem;
        } else {
            $item = ConsignmentItem::query()->create([
                'consignment_id'    => $consignment->id,
                'entry_kind'        => 'lot',
                'lot_id'            => $lot->id,
                'instrument_set_id' => null,
                'issued_at'         => now(),
                'issued_by_user_id' => $actorId,
                'remarks'           => $data['remarks'] ?? null,
                'proposed_quantity' => $proposedQty,
                'quantity'          => $qty,
            ]);
        }

        return $item->load([
            'lot:id,product_id,lot_number,status,quantity_available,expiry_date',
            'lot.product:id,ref_num,product_name,product_type',
            'instrumentSet:id,set_code,set_name',
            'instrumentSet.instrumentSetItems.product:id,product_name,ref_num',
            'instrumentSet.setInstruments:id,instrument_set_id,name,quantity',
        ]);
    }

    /**
     * Instrument-set consignment item: references a set directly.
     * The set's component products are FIFO-deducted from stock when the
     * consignment is confirmed (not at this stage).
     *
     * @param array<string, mixed> $data
     */
    private function addSetItem(Consignment $consignment, array $data, int $actorId): ConsignmentItem
    {
        $instrumentSetId = (int) ($data['instrument_set_id'] ?? 0);

        $set = InstrumentSet::query()
            ->with(['instrumentSetItems'])
            ->find($instrumentSetId);

        if (!$set) {
            throw new BusinessLogicException('Selected instrument set is not found.');
        }

        if (!$set->is_active) {
            throw new BusinessLogicException('Inactive instrument sets cannot be consigned.');
        }

        if ($set->instrumentSetItems->isEmpty()) {
            throw new BusinessLogicException(
                "Instrument set '{$set->set_name}' has no component products defined and cannot be consigned."
            );
        }

        $proposedSetQty = $data['proposed_quantity'] ?? 1;
        $setQty = $data['quantity'] ?? 1;

        // Pre-check: each component product must have enough available stock
        foreach ($set->instrumentSetItems as $setItem) {
            $required       = $setItem->quantity * $setQty;
            $totalAvailable = \App\Models\Lot::query()
                ->where('product_id', $setItem->product_id)
                ->where('quantity_available', '>', 0)
                ->sum('quantity_available');

            if ($totalAvailable < $required) {
                throw new BusinessLogicException(
                    "Insufficient stock for a component product in set '{$set->set_name}': "
                    . "need {$required}, available {$totalAvailable}."
                );
            }
        }

        $alreadyAdded = ConsignmentItem::query()
            ->where('consignment_id', $consignment->id)
            ->where('instrument_set_id', $set->id)
            ->exists();

        if ($alreadyAdded) {
            throw new BusinessLogicException('This instrument set is already in this consignment.');
        }

        $item = ConsignmentItem::query()->create([
            'consignment_id'    => $consignment->id,
            'entry_kind'        => 'set',
            'lot_id'            => null,
            'instrument_set_id' => $set->id,
            'issued_at'         => now(),
            'issued_by_user_id' => $actorId,
            'remarks'           => $data['remarks'] ?? null,
            'proposed_quantity' => $proposedSetQty,
            'quantity'          => $setQty,
        ]);

        return $item->load([
            'lot:id,product_id,lot_number,status,quantity_available,expiry_date',
            'lot.product:id,ref_num,product_name,product_type',
            'instrumentSet:id,set_code,set_name',
            'instrumentSet.instrumentSetItems.product:id,product_name,ref_num',
        ]);
    }

    public function updateItem(Consignment $consignment, ConsignmentItem $item, array $data): ConsignmentItem
    {
        if ($consignment->status !== 'draft') {
            throw new BusinessLogicException('Items can only be edited in draft consignments.');
        }

        if ($item->consignment_id !== $consignment->id) {
            throw new BusinessLogicException('This item does not belong to the specified consignment.');
        }

        if (array_key_exists('proposed_quantity', $data)) {
            $item->proposed_quantity = $data['proposed_quantity'];
        }

        if (array_key_exists('quantity', $data)) {
            $item->quantity = $data['quantity'];
        }

        if (array_key_exists('remarks', $data)) {
            $item->remarks = $data['remarks'];
        }

        $item->save();

        return $item->refresh()->load([
            'lot:id,product_id,lot_number,status,quantity_available,expiry_date',
            'lot.product:id,ref_num,product_name,product_type',
            'instrumentSet:id,set_code,set_name',
            'instrumentSet.instrumentSetItems.product:id,product_name,ref_num',
        ]);
    }

    public function removeItem(Consignment $consignment, ConsignmentItem $item): void
    {
        if ($consignment->status !== 'draft') {
            throw new BusinessLogicException('Items can only be removed from draft consignments.');
        }

        if ($item->consignment_id !== $consignment->id) {
            throw new BusinessLogicException('This item does not belong to the specified consignment.');
        }

        $item->delete();
    }
}
