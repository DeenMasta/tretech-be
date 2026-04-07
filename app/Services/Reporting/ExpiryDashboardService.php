<?php

namespace App\Services\Reporting;

use App\Models\Lot;
use Illuminate\Support\Carbon;

class ExpiryDashboardService
{
    private const WINDOWS = [30, 60, 90];

    /**
     * Query lots expiring within 30, 60, and 90 days.
     *
     * Optional filters: supplier_id, product_id, window (int, must be one of 30/60/90)
     */
    public function getReport(array $filters = []): array
    {
        $now    = Carbon::today();
        $maxEnd = $now->copy()->addDays(90);

        $query = Lot::query()
            ->select([
                'lots.id',
                'lots.lot_number',
                'lots.supplier_batch_code',
                'lots.expiry_date',
                'lots.status',
                'lots.supplier_id',
                'lots.product_id',
            ])
            ->with([
                'product:id,ref_num,product_name,uom',
                'supplier:id,supplier_name',
            ])
            ->whereNotNull('lots.expiry_date')
            ->whereDate('lots.expiry_date', '>=', $now)
            ->whereDate('lots.expiry_date', '<=', $maxEnd)
            ->whereIn('lots.status', ['available', 'supplied', 'holding']);

        if (!empty($filters['supplier_id'])) {
            $query->where('lots.supplier_id', $filters['supplier_id']);
        }
        if (!empty($filters['product_id'])) {
            $query->where('lots.product_id', $filters['product_id']);
        }

        $lots = $query->orderBy('lots.expiry_date')->get();

        // Bucket into windows
        $windows = [];
        $windows['already_expired'] = Lot::query()
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<', $now)
            ->whereIn('status', ['available', 'supplied'])
            ->count();

        foreach (self::WINDOWS as $days) {
            $windowEnd = $now->copy()->addDays($days);

            // Each window is cumulative (within N days from today)
            $windowLots = $lots->filter(
                fn (Lot $lot) => $lot->expiry_date !== null && $lot->expiry_date->lte($windowEnd)
            );

            $windows["within_{$days}_days"] = [
                'count'      => $windowLots->count(),
                'by_product' => $windowLots->groupBy('product_id')->map(function ($group) {
                    $product = $group->first()->product;
                    return [
                        'ref_num'      => $product?->ref_num,
                        'product_name' => $product?->product_name,
                        'count'        => $group->count(),
                    ];
                })->values(),
                'lots'       => $windowLots->values(),
            ];
        }

        // Filter by specific window if requested
        $requestedWindow = !empty($filters['window']) ? (int) $filters['window'] : null;

        if ($requestedWindow !== null && in_array($requestedWindow, self::WINDOWS, true)) {
            $windowEnd   = $now->copy()->addDays($requestedWindow);
            $filteredLots = $lots->filter(
                fn (Lot $lot) => $lot->expiry_date !== null && $lot->expiry_date->lte($windowEnd)
            )->values();

            return [
                'window_days'  => $requestedWindow,
                'count'        => $filteredLots->count(),
                'already_expired' => $windows['already_expired'],
                'data'         => $filteredLots,
            ];
        }

        return [
            'summary' => $windows,
            'data'    => $lots,
        ];
    }

    public function getExportRows(array $filters = []): array
    {
        $result = $this->getReport($filters);
        $lots   = $result['data'] ?? collect();

        return $lots->map(function (Lot $lot) {
            return [
                'Lot Number'   => $lot->lot_number,
                'Batch Code'   => $lot->supplier_batch_code,
                'Product Ref'  => $lot->product?->ref_num,
                'Product Name' => $lot->product?->product_name,
                'Supplier'     => $lot->supplier?->supplier_name,
                'Expiry Date'  => $lot->expiry_date?->format('Y-m-d'),
                'Status'       => $lot->status,
                'Days Left'    => Carbon::today()->diffInDays($lot->expiry_date, false),
            ];
        })->toArray();
    }
}
