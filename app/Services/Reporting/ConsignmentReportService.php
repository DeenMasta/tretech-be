<?php

namespace App\Services\Reporting;

use App\Models\Consignment;

class ConsignmentReportService
{
    /**
     * Consignment report with optional filters.
     *
     * Filters: from_date, to_date, client_id, product_id, status
     */
    public function getReport(array $filters = []): array
    {
        $query = Consignment::query()
            ->with([
                'client:id,client_name',
                'picUser:id,full_name',
                'consignmentItems.lot.product:id,ref_num,product_name,uom',
            ])
            ->withCount('consignmentItems');

        if (!empty($filters['from_date'])) {
            $query->whereDate('consignment_at', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->whereDate('consignment_at', '<=', $filters['to_date']);
        }
        if (!empty($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['product_id'])) {
            $query->whereHas('consignmentItems.lot', function ($q) use ($filters) {
                $q->where('product_id', $filters['product_id']);
            });
        }

        $consignments = $query->orderByDesc('consignment_at')->get();

        $summary = [
            'total_consignments'   => $consignments->count(),
            'total_units_issued'   => $consignments->sum('consignment_items_count'),
            'by_status'            => $consignments->groupBy('status')->map->count(),
            'by_client'            => $consignments->groupBy('client_id')->map(function ($group) {
                $client = $group->first()->client;
                return [
                    'client_name'        => $client?->client_name,
                    'consignment_count'  => $group->count(),
                    'units_issued'       => $group->sum('consignment_items_count'),
                ];
            })->values(),
        ];

        return [
            'summary' => $summary,
            'data'    => $consignments,
        ];
    }

    /**
     * Returns flat rows for export (one row per consignment item/lot).
     */
    public function getExportRows(array $filters = []): array
    {
        $result  = $this->getReport($filters);
        $rows    = [];

        foreach ($result['data'] as $consignment) {
            foreach ($consignment->consignmentItems as $item) {
                $lot     = $item->lot;
                $product = $lot?->product;

                $rows[] = [
                    'Consignment No'  => $consignment->consignment_no,
                    'Status'          => $consignment->status,
                    'Client'          => $consignment->client?->client_name,
                    'Consignment Date' => $consignment->consignment_at?->format('Y-m-d'),
                    'Lot Number'      => $lot?->lot_number,
                    'Batch Code'      => $lot?->manufacturing_date,
                    'Product Ref'     => $product?->ref_num,
                    'Product Name'    => $product?->product_name,
                    'UOM'             => $product?->uom,
                    'Expiry Date'     => $lot?->expiry_date?->format('Y-m-d'),
                    'PIC'             => $consignment->picUser?->full_name,
                ];
            }
        }

        return $rows;
    }
}
