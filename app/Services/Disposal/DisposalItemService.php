<?php

namespace App\Services\Disposal;

use App\Exceptions\BusinessLogicException;
use App\Models\Disposal;
use App\Models\DisposalItem;
use App\Models\Lot;

class DisposalItemService
{
    /**
     * Add a lot to a draft disposal.
     *
     * @param array<string, mixed> $data  Expects: lot_id, disposal_category, reason_text, remarks?
     */
    public function addItem(Disposal $disposal, array $data): DisposalItem
    {
        $this->ensureDraft($disposal);

        $lot = Lot::query()->findOrFail((int) $data['lot_id']);

        // Block already-terminal statuses
        if (in_array($lot->status, ['disposed', 'returned_to_supplier'], true)) {
            throw new BusinessLogicException(
                "Lot [{$lot->lot_number}] is already {$lot->status} and cannot be added to a disposal."
            );
        }

        // Prevent duplicate within the same disposal
        $alreadyAdded = DisposalItem::query()
            ->where('disposal_id', $disposal->id)
            ->where('lot_id', $lot->id)
            ->exists();

        if ($alreadyAdded) {
            throw new BusinessLogicException(
                "Lot [{$lot->lot_number}] has already been added to this disposal."
            );
        }

        return DisposalItem::query()->create([
            'disposal_id'      => $disposal->id,
            'lot_id'           => $lot->id,
            'disposal_category' => $data['disposal_category'],
            'reason_text'       => $data['reason_text'],
            'remarks'           => $data['remarks'] ?? null,
        ]);
    }

    /**
     * Remove a lot from a draft disposal.
     */
    public function removeItem(Disposal $disposal, DisposalItem $item): void
    {
        $this->ensureDraft($disposal);

        if ($item->disposal_id !== $disposal->id) {
            throw new BusinessLogicException('Item does not belong to this disposal.');
        }

        $item->delete();
    }

    public function listByDisposal(Disposal $disposal): \Illuminate\Database\Eloquent\Collection
    {
        return $disposal->disposalItems()
            ->with(['lot.product:id,ref_num,product_name', 'lot.supplier:id,supplier_name'])
            ->get();
    }

    private function ensureDraft(Disposal $disposal): void
    {
        if ($disposal->status !== 'draft') {
            throw new BusinessLogicException('Only draft disposals can be modified.');
        }
    }
}
