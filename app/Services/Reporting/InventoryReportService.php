<?php

namespace App\Services\Reporting;

use App\Models\Lot;
use App\Models\LotMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class InventoryReportService
{
    private const EXPORT_COLUMNS = [
        'lot_number' => 'Lot number / Item',
        'supplier' => 'Supplier',
        'received' => 'Received',
        'available' => 'Available',
        'consigned' => 'Consigned',
        'returned' => 'Returned',
        'used' => 'Used',
        'disposed' => 'Disposed',
    ];

    /**
     * Current inventory balances grouped by master product, with the matching
     * lot balances included beneath each product summary.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getReport(array $filters = []): array
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 25), 100));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $paginator = $this->productGroups($filters)
            ->orderBy('report_products.product_name')
            ->paginate($perPage, ['*'], 'page', $page);

        $productIds = $paginator->getCollection()->pluck('product_id')->all();
        $lotsByProduct = $productIds === []
            ? collect()
            : $this->withMovementQuantities($this->filteredLots($filters))
                ->whereIn('lots.product_id', $productIds)
                ->orderBy('lots.lot_number')
                ->get()
                ->groupBy('product_id');

        return [
            'summary' => $this->summary($filters),
            'data' => $paginator->getCollection()
                ->map(fn (Lot $product) => $this->formatProductGroup($product, $lotsByProduct->get($product->product_id, collect())))
                ->all(),
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    /** @param array<string, mixed> $filters */
    public function getExportRows(array $filters = []): array
    {
        $columns = $filters['columns'] ?? array_keys(self::EXPORT_COLUMNS);
        $products = $this->productGroups($filters)
            ->orderBy('report_products.product_name')
            ->get();
        $productIds = $products->pluck('product_id')->all();
        $lotsByProduct = $productIds === []
            ? collect()
            : $this->withMovementQuantities($this->filteredLots($filters))
                ->whereIn('lots.product_id', $productIds)
                ->orderBy('lots.lot_number')
                ->get()
                ->groupBy('product_id');

        return $products
            ->flatMap(function (Lot $product) use ($lotsByProduct): array {
                $productGroup = $this->formatProductGroup($product, $lotsByProduct->get($product->product_id, collect()));
                $productName = trim(implode(' - ', array_filter([$productGroup['item_code'], $productGroup['item_name']])));
                $rows = [$this->exportRow($productGroup, $productName, 'All suppliers', true)];

                foreach ($productGroup['lots'] as $lot) {
                    $rows[] = $this->exportRow($lot, $lot['lot_number'], $lot['supplier_name']);
                }

                return $rows;
            })
            ->map(fn (array $row): array => $this->selectedExportColumns($row, $columns))
            ->all();
    }

    /** @param array<string, mixed> $filters */
    public function getSummary(array $filters = []): array
    {
        return $this->summary($filters);
    }

    /** @param array<string, mixed> $filters */
    private function filteredLots(array $filters): Builder
    {
        $productTypes = $filters['product_types'] ?? ['consumable', 'implant'];

        return Lot::query()
            ->with([
                'product:id,ref_num,product_name,product_type',
                'supplier:id,supplier_name',
            ])
            ->where('lots.status', '<>', 'holding')
            ->whereHas('product', fn (Builder $product) => $product->whereIn(DB::raw('LOWER(product_type)'), $productTypes))
            ->when(! empty($filters['supplier_id']), fn (Builder $query) => $query->where('lots.supplier_id', (int) $filters['supplier_id']))
            ->when(! empty($filters['product_id']), fn (Builder $query) => $query->where('lots.product_id', (int) $filters['product_id']))
            ->when(! empty($filters['statuses']), function (Builder $query) use ($filters): void {
                $statuses = $filters['statuses'];
                $query->where(function (Builder $statusQuery) use ($statuses): void {
                    foreach ($statuses as $status) {
                        $statusQuery->orWhere(function (Builder $selectedStatus) use ($status): void {
                            match ($status) {
                                'received' => $selectedStatus->where('lots.quantity', '>', 0),
                                'available' => $selectedStatus->where('lots.quantity_available', '>', 0),
                                'consigned' => $selectedStatus->where('lots.quantity_consigned', '>', 0),
                                'returned', 'used', 'disposed' => $selectedStatus->whereHas('lotMovements', fn (Builder $movement) => $movement
                                    ->where('movement_type', $status)
                                    ->where('quantity', '>', 0)),
                            };
                        });
                    }
                });
            })
            ->when(! empty($filters['expiry_from']), fn (Builder $query) => $query->whereDate('lots.expiry_date', '>=', $filters['expiry_from']))
            ->when(! empty($filters['expiry_to']), fn (Builder $query) => $query->whereDate('lots.expiry_date', '<=', $filters['expiry_to']))
            ->when(! empty($filters['search']), function (Builder $query) use ($filters): void {
                $term = trim((string) $filters['search']);

                $query->where(function (Builder $searchQuery) use ($term): void {
                    $searchQuery
                        ->where('lots.lot_number', 'like', "%{$term}%")
                        ->orWhereHas('product', fn (Builder $product) => $product
                            ->where('ref_num', 'like', "%{$term}%")
                            ->orWhere('product_name', 'like', "%{$term}%"));
                });
            });
    }

    private function withMovementQuantities(Builder $query): Builder
    {
        return $query
            ->withSum(['lotMovements as quantity_returned' => fn (Builder $movement) => $movement->where('movement_type', 'returned')], 'quantity')
            ->withSum(['lotMovements as quantity_used' => fn (Builder $movement) => $movement->where('movement_type', 'used')], 'quantity')
            ->withSum(['lotMovements as quantity_disposed' => fn (Builder $movement) => $movement->where('movement_type', 'disposed')], 'quantity');
    }

    /** @param array<string, mixed> $filters */
    private function productGroups(array $filters): Builder
    {
        return $this->filteredLots($filters)
            ->join('products as report_products', 'lots.product_id', '=', 'report_products.id')
            ->select([
                'lots.product_id',
                'report_products.ref_num as item_code',
                'report_products.product_name as item_name',
            ])
            ->groupBy('lots.product_id', 'report_products.ref_num', 'report_products.product_name');
    }

    /** @param array<string, mixed> $filters */
    private function summary(array $filters): array
    {
        $totals = (clone $this->filteredLots($filters))
            ->selectRaw('COUNT(*) as total_lots')
            ->selectRaw('COALESCE(SUM(quantity), 0) as received_quantity')
            ->selectRaw('COALESCE(SUM(quantity_available), 0) as available_quantity')
            ->selectRaw('COALESCE(SUM(quantity_consigned), 0) as consigned_quantity')
            ->first();

        $filteredLotIds = (clone $this->filteredLots($filters))->select('lots.id');
        $movementTotals = LotMovement::query()
            ->whereIn('lot_id', $filteredLotIds)
            ->selectRaw("COALESCE(SUM(CASE WHEN movement_type = 'returned' THEN quantity ELSE 0 END), 0) as returned_quantity")
            ->selectRaw("COALESCE(SUM(CASE WHEN movement_type = 'used' THEN quantity ELSE 0 END), 0) as used_quantity")
            ->selectRaw("COALESCE(SUM(CASE WHEN movement_type = 'disposed' THEN quantity ELSE 0 END), 0) as disposed_quantity")
            ->first();

        return [
            'as_of' => now()->toIso8601String(),
            'total_lots' => (int) $totals->total_lots,
            'received' => (int) $totals->received_quantity,
            'available' => (int) $totals->available_quantity,
            'consigned' => (int) $totals->consigned_quantity,
            'returned' => (int) $movementTotals->returned_quantity,
            'used' => (int) $movementTotals->used_quantity,
            'disposed' => (int) $movementTotals->disposed_quantity,
        ];
    }

    /** @param iterable<Lot> $lots */
    private function formatRows(iterable $lots): array
    {
        $rows = [];
        foreach ($lots as $lot) {
            $rows[] = $this->formatRow($lot);
        }

        return $rows;
    }

    /** @return array<string, int|string|null> */
    private function formatRow(Lot $lot): array
    {
        $available = (int) $lot->quantity_available;
        $consigned = (int) $lot->quantity_consigned;

        return [
            'id' => $lot->id,
            'lot_number' => $lot->lot_number,
            'item_code' => $lot->product?->ref_num,
            'item_name' => $lot->product?->product_name,
            'supplier_name' => $lot->supplier?->supplier_name,
            'quantity_received' => (int) $lot->quantity,
            'quantity_available' => $available,
            'quantity_consigned' => $consigned,
            'quantity_returned' => (int) ($lot->quantity_returned ?? 0),
            'quantity_used' => (int) ($lot->quantity_used ?? 0),
            'quantity_disposed' => (int) ($lot->quantity_disposed ?? 0),
        ];
    }

    /** @param iterable<Lot> $lots */
    private function formatProductGroup(Lot $product, iterable $lots): array
    {
        $lotRows = $this->formatRows($lots);

        return [
            'id' => 'product-'.$product->product_id,
            'product_id' => (int) $product->product_id,
            'item_code' => $product->item_code,
            'item_name' => $product->item_name,
            'quantity_received' => array_sum(array_column($lotRows, 'quantity_received')),
            'quantity_available' => array_sum(array_column($lotRows, 'quantity_available')),
            'quantity_consigned' => array_sum(array_column($lotRows, 'quantity_consigned')),
            'quantity_returned' => array_sum(array_column($lotRows, 'quantity_returned')),
            'quantity_used' => array_sum(array_column($lotRows, 'quantity_used')),
            'quantity_disposed' => array_sum(array_column($lotRows, 'quantity_disposed')),
            'lots' => $lotRows,
            'is_product_summary' => true,
        ];
    }

    /** @param array<string, int|string|null> $row */
    private function exportRow(array $row, string $lotNumber, ?string $supplier, bool $isProductTotal = false): array
    {
        return [
            'lot_number' => $lotNumber,
            'supplier' => $supplier,
            'received' => $row['quantity_received'],
            'available' => $row['quantity_available'],
            'consigned' => $row['quantity_consigned'],
            'returned' => $row['quantity_returned'],
            'used' => $row['quantity_used'],
            'disposed' => $row['quantity_disposed'],
            '__product_total' => $isProductTotal,
        ];
    }

    /** @param array<string, int|string|null> $row
     * @param  array<int, string>  $columns
     */
    private function selectedExportColumns(array $row, array $columns): array
    {
        $selected = [];
        foreach ($columns as $column) {
            $selected[self::EXPORT_COLUMNS[$column]] = $row[$column];
        }
        $selected['__product_total'] = $row['__product_total'];

        return $selected;
    }
}
