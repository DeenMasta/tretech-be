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
                'lot.product:id,ref_num,product_name',
                'instrumentSet:id,set_code,set_name',
                'instrumentSet.instrumentSetItems.product:id,product_name,ref_num',
                'instrumentSet.setInstruments:id,instrument_set_id,name,quantity',
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

        if ($lot->status !== 'available') {
            throw new BusinessLogicException(
                "Lot {$lot->lot_number} is not available for consignment (current status: {$lot->status})."
            );
        }

        $alreadyAdded = ConsignmentItem::query()
            ->where('consignment_id', $consignment->id)
            ->where('lot_id', $lot->id)
            ->exists();

        if ($alreadyAdded) {
            throw new BusinessLogicException("Lot {$lot->lot_number} is already in this consignment.");
        }

        $item = ConsignmentItem::query()->create([
            'consignment_id'    => $consignment->id,
            'entry_kind'        => 'lot',
            'lot_id'            => $lot->id,
            'instrument_set_id' => null,
            'issued_at'         => now(),
            'issued_by_user_id' => $actorId,
            'remarks'           => $data['remarks'] ?? null,
        ]);

        return $item->load([
            'lot.product:id,ref_num,product_name',
            'instrumentSet:id,set_code,set_name',
            'instrumentSet.instrumentSetItems.product:id,product_name,ref_num',
            'instrumentSet.setInstruments:id,instrument_set_id,name,quantity',
        ]);
    }

    /**
     * Instrument-set consignment item: references a set directly (no lot selected).
     * The set will be tracked as consigned without a lot movement until a lot is assigned.
     *
     * @param array<string, mixed> $data
     */
    private function addSetItem(Consignment $consignment, array $data, int $actorId): ConsignmentItem
    {
        $instrumentSetId = (int) ($data['instrument_set_id'] ?? 0);

        $set = InstrumentSet::query()->select(['id', 'is_active'])->find($instrumentSetId);

        if (!$set) {
            throw new BusinessLogicException('Selected instrument set is not found.');
        }

        if (!$set->is_active) {
            throw new BusinessLogicException('Inactive instrument sets cannot be consigned.');
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
        ]);

        return $item->load([
            'lot.product:id,ref_num,product_name',
            'instrumentSet:id,set_code,set_name',
            'instrumentSet.instrumentSetItems.product:id,product_name,ref_num',
            'instrumentSet.setInstruments:id,instrument_set_id,name,quantity',
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
