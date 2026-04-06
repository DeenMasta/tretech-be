<?php

namespace App\Services\Consignment;

use App\Exceptions\BusinessLogicException;
use App\Models\Consignment;
use App\Models\ConsignmentItem;
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
            ->with(['lot.product:id,ref_num,product_name'])
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
            'lot_id'            => $lot->id,
            'issued_at'         => now(),
            'issued_by_user_id' => $actorId,
            'remarks'           => $data['remarks'] ?? null,
        ]);

        return $item->load(['lot.product:id,ref_num,product_name']);
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
