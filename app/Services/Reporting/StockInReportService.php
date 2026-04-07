<?php

namespace App\Services\Reporting;

use App\Models\Lot;
use Illuminate\Support\Facades\DB;

class StockInReportService
{
    /**
     * Stock-in analytics with optional filters.
     *
     * Filters: from_date, to_date, supplier_id, product_id
     *
     * Returns aggregate counts and row-level detail.
     */
    public function getReport(array $filters = []): array
    {
        $query = Lot::query()
            ->select([
                'lots.id',
                'lots.lot_number',
                'lots.supplier_batch_code',
                'lots.expiry_date',
                'lots.status',
                'lots.received_at',
                'lots.supplier_id',
                'lots.product_id',
            ])
            ->with([
                'product:id,ref_num,product_name,product_type,uom',
                'supplier:id,supplier_name',
            ])
            ->whereNotNull('lots.received_at');

        if (!empty($filters['from_date'])) {
            $query->whereDate('lots.received_at', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->whereDate('lots.received_at', '<=', $filters['to_date']);
        }
        if (!empty($filters['supplier_id'])) {
            $query->where('lots.supplier_id', $filters['supplier_id']);
        }
        if (!empty($filters['product_id'])) {
            $query->where('lots.product_id', $filters['product_id']);
        }

        $lots = $query->orderByDesc('lots.received_at')->get();

        // Aggregate summary
        $summary = [
            'total_units'     => $lots->count(),
            'by_status'       => $lots->groupBy('status')->map->count(),
            'by_supplier'     => $lots->groupBy('supplier_id')->map(function ($group) {
                $supplier = $group->first()->supplier;
                return [
                    'supplier_name' => $supplier?->supplier_name,
                    'count'         => $group->count(),
                ];
            })->values(),
            'by_product'      => $lots->groupBy('product_id')->map(function ($group) {
                $product = $group->first()->product;
                return [
                    'ref_num'      => $product?->ref_num,
                    'product_name' => $product?->product_name,
                    'count'        => $group->count(),
                ];
            })->values(),
        ];

        return [
            'summary' => $summary,
            'data'    => $lots,
        ];
    }

    /**
     * Returns a flat array for export drivers (each row is a lot).
     */
    public function getExportRows(array $filters = []): array
    {
        $result = $this->getReport($filters);

        return $result['data']->map(function (Lot $lot) {
            return [
                'Lot Number'    => $lot->lot_number,
                'Batch Code'    => $lot->supplier_batch_code,
                'Product Ref'   => $lot->product?->ref_num,
                'Product Name'  => $lot->product?->product_name,
                'Supplier'      => $lot->supplier?->supplier_name,
                'Expiry Date'   => $lot->expiry_date?->format('Y-m-d'),
                'Status'        => $lot->status,
                'Received At'   => $lot->received_at?->format('Y-m-d H:i'),
            ];
        })->toArray();
    }
}
