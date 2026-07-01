<?php

namespace App\Services\Reporting;

use App\Models\Disposal;
use App\Models\DisposalItem;

class DisposalReportService
{
    /**
     * Disposal & loss report with optional filters.
     *
     * Filters: from_date, to_date, supplier_id, product_id, disposal_category
     */
    public function getReport(array $filters = []): array
    {
        $itemQuery = DisposalItem::query()
            ->with([
                'disposal:id,disposal_no,status,disposed_at,completed_at',
                'lot.product:id,ref_num,product_name,uom',
                'lot.supplier:id,supplier_name',
            ]);

        // Filter by disposal date
        if (!empty($filters['from_date'])) {
            $itemQuery->whereHas('disposal', function ($q) use ($filters) {
                $q->whereDate('disposed_at', '>=', $filters['from_date']);
            });
        }
        if (!empty($filters['to_date'])) {
            $itemQuery->whereHas('disposal', function ($q) use ($filters) {
                $q->whereDate('disposed_at', '<=', $filters['to_date']);
            });
        }

        // Only completed disposals
        $itemQuery->whereHas('disposal', function ($q) {
            $q->where('status', 'completed');
        });

        if (!empty($filters['disposal_category'])) {
            $itemQuery->where('disposal_category', $filters['disposal_category']);
        }
        if (!empty($filters['supplier_id'])) {
            $itemQuery->whereHas('lot', function ($q) use ($filters) {
                $q->where('supplier_id', $filters['supplier_id']);
            });
        }
        if (!empty($filters['product_id'])) {
            $itemQuery->whereHas('lot', function ($q) use ($filters) {
                $q->where('product_id', $filters['product_id']);
            });
        }

        $items = $itemQuery->get();

        $summary = [
            'total_disposed_units' => $items->count(),
            'by_category'          => $items->groupBy('disposal_category')->map->count(),
            'by_supplier'          => $items->groupBy(fn ($i) => $i->lot?->supplier_id)->map(function ($group) {
                $supplier = $group->first()->lot?->supplier;
                return [
                    'supplier_name' => $supplier?->supplier_name,
                    'count'         => $group->count(),
                ];
            })->values(),
            'by_product'           => $items->groupBy(fn ($i) => $i->lot?->product_id)->map(function ($group) {
                $product = $group->first()->lot?->product;
                return [
                    'ref_num'      => $product?->ref_num,
                    'product_name' => $product?->product_name,
                    'count'        => $group->count(),
                ];
            })->values(),
        ];

        return [
            'summary' => $summary,
            'data'    => $items,
        ];
    }

    public function getExportRows(array $filters = []): array
    {
        $result = $this->getReport($filters);

        return $result['data']->map(function (DisposalItem $item) {
            return [
                'Disposal No'      => $item->disposal?->disposal_no,
                'Disposed At'      => $item->disposal?->disposed_at?->format('Y-m-d'),
                'Lot Number'       => $item->lot?->lot_number,
                'Batch Code'       => $item->lot?->manufacturing_date,
                'Product Ref'      => $item->lot?->product?->ref_num,
                'Product Name'     => $item->lot?->product?->product_name,
                'Supplier'         => $item->lot?->supplier?->supplier_name,
                'Expiry Date'      => $item->lot?->expiry_date?->format('Y-m-d'),
                'Category'         => $item->disposal_category,
                'Reason'           => $item->reason_text,
                'Remarks'          => $item->remarks,
            ];
        })->toArray();
    }
}
