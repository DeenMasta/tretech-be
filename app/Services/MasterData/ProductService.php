<?php

namespace App\Services\MasterData;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductService
{
    /**
     * @param array<string, mixed> $filters
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $search = (string) ($filters['search'] ?? '');
        $isActive = $filters['is_active'] ?? null;

        return Product::query()
            ->withCount(['lots as available_lots_count' => function ($query) {
                $query->where('status', 'available');
            }])
            ->withSum(['lots as total_quantity_available' => function ($query) {
                $query->where('status', 'available');
            }], 'quantity_available')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('ref_num', 'like', "%{$search}%")
                        ->orWhere('product_name', 'like', "%{$search}%")
                        ->orWhere('product_type', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%")
                        ->orWhereHas('lots', function ($lotQuery) use ($search) {
                            $lotQuery->where('lot_number', 'like', "%{$search}%");
                        });
                });
            })
            ->when($isActive !== null, fn ($query) => $query->where('is_active', (bool) $isActive))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): Product
    {
        return Product::query()->create($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Product $product, array $data): Product
    {
        $product->fill($data)->save();

        return $product->refresh();
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }
}
