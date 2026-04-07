<?php

namespace App\Services\SupplierReturn;

use App\Exceptions\BusinessLogicException;
use App\Models\Lot;
use App\Models\SupplierReturn;
use App\Models\SupplierReturnItem;

class SupplierReturnItemService
{
    /**
     * Add a lot to a draft supplier return.
     *
     * @param array<string, mixed> $data  Expects: lot_id, return_reason, remarks?
     */
    public function addItem(SupplierReturn $supplierReturn, array $data): SupplierReturnItem
    {
        $this->ensureDraft($supplierReturn);

        $lot = Lot::query()->findOrFail((int) $data['lot_id']);

        // Must belong to the same supplier as the return
        if ($lot->supplier_id !== $supplierReturn->supplier_id) {
            throw new BusinessLogicException(
                "Lot [{$lot->lot_number}] does not belong to the supplier of this return."
            );
        }

        // Block already-terminal statuses
        if (in_array($lot->status, ['disposed', 'returned_to_supplier'], true)) {
            throw new BusinessLogicException(
                "Lot [{$lot->lot_number}] is already {$lot->status} and cannot be returned to supplier."
            );
        }

        // Prevent duplicate within the same supplier return
        $alreadyAdded = SupplierReturnItem::query()
            ->where('supplier_return_id', $supplierReturn->id)
            ->where('lot_id', $lot->id)
            ->exists();

        if ($alreadyAdded) {
            throw new BusinessLogicException(
                "Lot [{$lot->lot_number}] has already been added to this supplier return."
            );
        }

        return SupplierReturnItem::query()->create([
            'supplier_return_id' => $supplierReturn->id,
            'lot_id'             => $lot->id,
            'return_reason'      => $data['return_reason'],
            'remarks'            => $data['remarks'] ?? null,
        ]);
    }

    /**
     * Remove a lot from a draft supplier return.
     */
    public function removeItem(SupplierReturn $supplierReturn, SupplierReturnItem $item): void
    {
        $this->ensureDraft($supplierReturn);

        if ($item->supplier_return_id !== $supplierReturn->id) {
            throw new BusinessLogicException('Item does not belong to this supplier return.');
        }

        $item->delete();
    }

    public function listBySupplierReturn(SupplierReturn $supplierReturn): \Illuminate\Database\Eloquent\Collection
    {
        return $supplierReturn->supplierReturnItems()
            ->with(['lot.product:id,ref_num,product_name', 'lot.supplier:id,supplier_name'])
            ->get();
    }

    private function ensureDraft(SupplierReturn $supplierReturn): void
    {
        if ($supplierReturn->status !== 'draft') {
            throw new BusinessLogicException('Only draft supplier returns can be modified.');
        }
    }
}
