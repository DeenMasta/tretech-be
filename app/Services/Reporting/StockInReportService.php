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
     * Returns aggregate counts and session-level detail.
     */
    public function getReport(array $filters = []): array
    {
        $query = \App\Models\StockIn::query()
            ->with([
                'supplier:id,supplier_name',
                'picUser:id,full_name',
                'stockInItems.product:id,ref_num,product_name,uom',
                'stockInItems.instrumentSet:id,set_code,set_name',
            ])
            ->withCount('stockInItems as items_count');

        if (!empty($filters['from_date'])) {
            $query->whereDate('stock_in_at', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->whereDate('stock_in_at', '<=', $filters['to_date']);
        }
        if (!empty($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }
        if (!empty($filters['product_id'])) {
            $query->whereHas('stockInItems', function ($q) use ($filters) {
                $q->where('product_id', $filters['product_id']);
            });
        }

        $sessions = $query->orderByDesc('stock_in_at')->get();

        // Aggregate summary
        $summary = [
            'total_sessions'  => $sessions->count(),
            'total_items'     => $sessions->sum('items_count'),
            'by_status'       => $sessions->groupBy('status')->map->count(),
            'by_supplier'     => $sessions->groupBy('supplier_id')->map(function ($group) {
                $supplier = $group->first()->supplier;
                return [
                    'supplier_name' => $supplier?->supplier_name,
                    'session_count' => $group->count(),
                    'items_count'   => $group->sum('items_count'),
                ];
            })->values(),
        ];

        return [
            'summary' => $summary,
            'data'    => $sessions,
        ];
    }

    /**
     * Returns a flat array for export drivers (each row is a stock-in item).
     */
    public function getExportRows(array $filters = []): array
    {
        $result = $this->getReport($filters);
        $rows   = [];

        foreach ($result['data'] as $session) {
            foreach ($session->stockInItems as $item) {
                $product = $item->product;
                $set     = $item->instrumentSet;

                $rows[] = [
                    'Session No'     => $session->session_no,
                    'DO No'          => $session->do_number,
                    'Supplier'       => $session->supplier?->supplier_name,
                    'Stock-In Date'  => $session->stock_in_at?->format('Y-m-d H:i'),
                    'Status'         => $session->status,
                    'PIC'            => $session->picUser?->full_name,
                    'Entry Kind'     => $item->entry_kind,
                    'Item Ref'       => $product ? $product->ref_num : ($set ? $set->set_code : ''),
                    'Item Name'      => $product ? $product->product_name : ($set ? $set->set_name : ''),
                    'Lot Number'     => $item->scanned_lot_number,
                    'Batch Code'     => $item->manufacturing_date?->format('Y-m-d'),
                    'Expiry Date'    => $item->expiry_date?->format('Y-m-d'),
                    'Quantity'       => $item->quantity,
                    'Remarks'        => $item->remarks,
                ];
            }
        }

        return $rows;
    }
}
