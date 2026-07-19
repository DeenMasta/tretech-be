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
     *
     * Returns aggregate counts and session-level detail.
     */
    public function getReport(array $filters = []): array
    {
        $query = Disposal::query()
            ->with([
                'picUser:id,full_name',
                'completedByUser:id,full_name',
                'disposalItems.lot.product:id,ref_num,product_name,uom',
                'disposalItems.lot.supplier:id,supplier_name',
            ])
            ->withCount('disposalItems as items_count');

        // Filter by disposal date
        if (!empty($filters['from_date'])) {
            $query->whereDate('disposed_at', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->whereDate('disposed_at', '<=', $filters['to_date']);
        }

        // Only completed disposals
        $query->where('status', 'completed');

        // Item-level filters using whereHas
        if (!empty($filters['disposal_category'])) {
            $query->whereHas('disposalItems', function ($q) use ($filters) {
                $q->where('disposal_category', $filters['disposal_category']);
            });
        }
        if (!empty($filters['supplier_id'])) {
            $query->whereHas('disposalItems.lot', function ($q) use ($filters) {
                $q->where('supplier_id', $filters['supplier_id']);
            });
        }
        if (!empty($filters['product_id'])) {
            $query->whereHas('disposalItems.lot', function ($q) use ($filters) {
                $q->where('product_id', $filters['product_id']);
            });
        }

        $sessions = $query->orderByDesc('disposed_at')->get();

        // Extract all items from the loaded sessions for summary aggregation
        $allItems = $sessions->pluck('disposalItems')->flatten();

        $summary = [
            'total_sessions'       => $sessions->count(),
            'total_disposed_items' => $sessions->sum('items_count'),
            'by_status'            => $sessions->groupBy('status')->map->count(),
            'by_category'          => $allItems->groupBy('disposal_category')->map->count(),
            'by_supplier'          => $allItems->groupBy(fn ($i) => $i->lot?->supplier_id)->map(function ($group) {
                $supplier = $group->first()->lot?->supplier;
                return [
                    'supplier_name' => $supplier?->supplier_name,
                    'count'         => $group->count(),
                ];
            })->values(),
            'by_product'           => $allItems->groupBy(fn ($i) => $i->lot?->product_id)->map(function ($group) {
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
            'data'    => $sessions,
        ];
    }

    /**
     * Returns a flat array for export drivers (each row is a disposal item).
     */
    public function getExportRows(array $filters = []): array
    {
        $result = $this->getReport($filters);
        $rows   = [];

        foreach ($result['data'] as $session) {
            foreach ($session->disposalItems as $item) {
                $rows[] = [
                    'Disposal No'      => $session->disposal_no,
                    'Disposed At'      => $session->disposed_at?->format('Y-m-d'),
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
            }
        }

        return $rows;
    }
}
