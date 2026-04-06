<?php

namespace App\Services\Inventory;

use App\Models\Lot;
use App\Models\LotMovement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class InventoryService
{
    /**
     * @param array<string, mixed> $filters
     */
    public function paginateLots(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $status = (string) ($filters['status'] ?? '');
        $supplierId = $filters['supplier_id'] ?? null;
        $productId = $filters['product_id'] ?? null;
        $search = (string) ($filters['search'] ?? '');

        return Lot::query()
            ->with([
                'product:id,ref_num,product_name',
                'supplier:id,supplier_name',
            ])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($supplierId !== null, fn ($query) => $query->where('supplier_id', (int) $supplierId))
            ->when($productId !== null, fn ($query) => $query->where('product_id', (int) $productId))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('lot_number', 'like', "%{$search}%")
                        ->orWhere('supplier_batch_code', 'like', "%{$search}%")
                        ->orWhereHas('product', function ($productQuery) use ($search) {
                            $productQuery->where('ref_num', 'like', "%{$search}%")
                                ->orWhere('product_name', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findLot(int $id): ?Lot
    {
        return Lot::query()
            ->with([
                'product:id,ref_num,product_name',
                'supplier:id,supplier_name',
            ])
            ->find($id);
    }

    public function lookupByLotNumber(string $lotNumber): ?Lot
    {
        return Lot::query()
            ->with([
                'product:id,ref_num,product_name',
                'supplier:id,supplier_name',
            ])
            ->where('lot_number', $lotNumber)
            ->first();
    }

    /**
     * @return array<int, Lot>
     */
    public function lookupByRefNum(string $refNum): array
    {
        return Lot::query()
            ->with([
                'product:id,ref_num,product_name',
                'supplier:id,supplier_name',
            ])
            ->whereHas('product', fn ($query) => $query->where('ref_num', $refNum))
            ->orderByDesc('id')
            ->get()
            ->all();
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function paginateLedger(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $lotId = $filters['lot_id'] ?? null;
        $lotNumber = (string) ($filters['lot_number'] ?? '');
        $movementType = (string) ($filters['movement_type'] ?? '');
        $fromDate = $filters['from_date'] ?? null;
        $toDate = $filters['to_date'] ?? null;

        return LotMovement::query()
            ->with([
                'lot:id,lot_number,product_id,status',
                'lot.product:id,ref_num,product_name',
                'recordedByUser:id,full_name,email',
            ])
            ->when($lotId !== null, fn ($query) => $query->where('lot_id', (int) $lotId))
            ->when($lotNumber !== '', function ($query) use ($lotNumber) {
                $query->whereHas('lot', fn ($lotQuery) => $lotQuery->where('lot_number', $lotNumber));
            })
            ->when($movementType !== '', fn ($query) => $query->where('movement_type', $movementType))
            ->when($fromDate !== null, fn ($query) => $query->whereDate('performed_at', '>=', $fromDate))
            ->when($toDate !== null, fn ($query) => $query->whereDate('performed_at', '<=', $toDate))
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
