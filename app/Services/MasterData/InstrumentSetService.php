<?php

namespace App\Services\MasterData;

use App\Models\InstrumentSet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class InstrumentSetService
{
    /**
     * @param array<string, mixed> $filters
     */
    public function paginate(array $filters = [], int $perPage = 15, bool $includeAvailability = false): LengthAwarePaginator
    {
        $search = (string) ($filters['search'] ?? '');
        $isActive = $filters['is_active'] ?? null;

        $paginator = InstrumentSet::query()
            ->withCount(['instrumentSetItems'])
            ->when($includeAvailability, fn ($q) => $q->with('instrumentSetItems:id,instrument_set_id,product_id,quantity'))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('set_code', 'like', "%{$search}%")
                        ->orWhere('set_name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($isActive !== null, fn ($query) => $query->where('is_active', (bool) $isActive))
            ->when(($filters['sort'] ?? null) === 'available_qty_desc', function ($query) {
                $subquery = \Illuminate\Support\Facades\DB::table('instrument_set_items')
                    ->selectRaw('MIN(FLOOR(COALESCE((SELECT SUM(quantity_available) FROM lots WHERE lots.product_id = instrument_set_items.product_id AND status = ?), 0) / NULLIF(instrument_set_items.quantity, 0)))', ['available'])
                    ->whereColumn('instrument_set_items.instrument_set_id', 'instrument_sets.id');
                    
                $query->selectSub($subquery, 'computed_available_sets_count')
                      ->orderByDesc(\Illuminate\Support\Facades\DB::raw('COALESCE(computed_available_sets_count, 0)'));
            })
            ->when(($filters['sort'] ?? null) !== 'available_qty_desc', fn ($query) => $query->orderByDesc('id'))
            ->paginate($perPage);

        if ($includeAvailability && $paginator->isNotEmpty()) {
            $productIds = $paginator->getCollection()->pluck('instrumentSetItems.*.product_id')->flatten()->unique()->filter();
            
            $availableStocks = [];
            if ($productIds->isNotEmpty()) {
                $availableStocks = \App\Models\Lot::query()
                    ->whereIn('product_id', $productIds)
                    ->where('status', 'available')
                    ->where('quantity_available', '>', 0)
                    ->selectRaw('product_id, SUM(quantity_available) as total')
                    ->groupBy('product_id')
                    ->pluck('total', 'product_id');
            }

            $paginator->getCollection()->transform(function ($set) use ($availableStocks) {
                if ($set->instrumentSetItems->isEmpty()) {
                    $set->available_sets_count = 0;
                    return $set;
                }
                
                $minSets = null;
                foreach ($set->instrumentSetItems as $item) {
                    if ($item->quantity <= 0) continue;
                    $avail = $availableStocks[$item->product_id] ?? 0;
                    $possible = (int) floor($avail / $item->quantity);
                    if ($minSets === null || $possible < $minSets) {
                        $minSets = $possible;
                    }
                }
                $set->available_sets_count = $minSets ?? 0;
                return $set;
            });
        }

        return $paginator;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): InstrumentSet
    {
        return InstrumentSet::query()->create($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(InstrumentSet $instrumentSet, array $data): InstrumentSet
    {
        $instrumentSet->fill($data)->save();

        return $instrumentSet->refresh();
    }

    public function delete(InstrumentSet $instrumentSet): void
    {
        $instrumentSet->delete();
    }
}
