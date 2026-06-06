<?php

namespace App\Services\MasterData;

use App\Exceptions\BusinessLogicException;
use App\Models\InstrumentSet;
use App\Models\InstrumentSetItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

class InstrumentSetItemService
{
    /**
     * @return Collection<int, InstrumentSetItem>
     */
    public function listBySet(InstrumentSet $instrumentSet): Collection
    {
        return $instrumentSet->instrumentSetItems()
            ->with(['product:id,ref_num,product_name,is_active'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(InstrumentSet $instrumentSet, array $data): InstrumentSetItem
    {
        $this->ensureProductCanBeAdded((int) $data['product_id']);

        $exists = InstrumentSetItem::query()
            ->where('instrument_set_id', $instrumentSet->id)
            ->where('product_id', (int) $data['product_id'])
            ->exists();

        if ($exists) {
            throw new BusinessLogicException('This product is already registered in the instrument set.');
        }

        return InstrumentSetItem::query()->create([
            'instrument_set_id' => $instrumentSet->id,
            'product_id' => (int) $data['product_id'],
            'quantity' => (int) $data['quantity'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'remarks' => $data['remarks'] ?? null,
        ])->load(['product:id,ref_num,product_name,is_active']);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(InstrumentSet $instrumentSet, InstrumentSetItem $item, array $data): InstrumentSetItem
    {
        $this->ensureBelongsToSet($instrumentSet, $item);

        $item->fill($data)->save();

        return $item->refresh()->load(['product:id,ref_num,product_name,is_active']);
    }

    public function delete(InstrumentSet $instrumentSet, InstrumentSetItem $item): void
    {
        $this->ensureBelongsToSet($instrumentSet, $item);
        $item->delete();
    }

    private function ensureBelongsToSet(InstrumentSet $instrumentSet, InstrumentSetItem $item): void
    {
        if ($item->instrument_set_id !== $instrumentSet->id) {
            throw new BusinessLogicException('Instrument set item does not belong to the provided set.');
        }
    }

    private function ensureProductCanBeAdded(int $productId): void
    {
        $product = Product::query()->select(['id', 'is_active'])->find($productId);

        if (!$product) {
            throw new BusinessLogicException('Selected product is not found.');
        }

        if (!$product->is_active) {
            throw new BusinessLogicException('Inactive products cannot be added to an instrument set.');
        }
    }
}
