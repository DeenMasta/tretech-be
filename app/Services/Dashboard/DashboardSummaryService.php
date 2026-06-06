<?php

namespace App\Services\Dashboard;

use App\Models\Lot;
use App\Models\LotMovement;
use App\Models\StockIn;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardSummaryService
{
    /**
     * Build the dashboard payload expected by the web app.
     *
     * A few frontend labels come from a broader ERP vocabulary than the current
     * backend model. For those, the service uses the closest truthful signal in
     * the existing domain: draft stock-ins as open inbound orders and a recent
     * outbound-coverage heuristic for low-stock risk.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getSummary(array $filters = []): array
    {
        [$dateFrom, $dateTo] = $this->normalizeDateRange($filters);

        return [
            'items_in_stock' => $this->countLotsByStatus('available'),
            'movements_today' => LotMovement::query()
                ->whereDate('performed_at', '=', now()->toDateString(), 'and')
                ->count(),
            'low_stock_count' => $this->countLowStockProducts(),
            'open_po_count' => StockIn::query()
                ->where('status', 'draft')
                ->count(),
            'overdue_po_count' => StockIn::query()
                ->where('status', 'draft')
                ->whereDate('stock_in_at', '<', now()->toDateString())
                ->count(),
            'items_received_pending_qc' => $this->countLotsByStatus('holding'),
            'items_under_repair' => 0,
            'items_delivered' => $this->countLotsByStatus('supplied'),
            'items_returned' => $this->countMovementEvents(['returned'], $dateFrom, $dateTo),
            'items_returned_to_supplier' => $this->countLotsByStatus('returned_to_supplier'),
            'stock_in_trend' => $this->buildMovementTrend(['stock_in'], $dateFrom, $dateTo),
            'stock_out_trend' => $this->buildMovementTrend(
                ['consigned', 'disposed', 'returned_to_supplier'],
                $dateFrom,
                $dateTo
            ),
            'top_moved_products' => $this->buildTopMovedProducts($dateFrom, $dateTo),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function normalizeDateRange(array $filters): array
    {
        $dateFrom = !empty($filters['date_from'])
            ? Carbon::parse((string) $filters['date_from'])->startOfDay()
            : null;

        $dateTo = !empty($filters['date_to'])
            ? Carbon::parse((string) $filters['date_to'])->endOfDay()
            : null;

        return [$dateFrom, $dateTo];
    }

    private function countLotsByStatus(string $status): int
    {
        return Lot::query()->where('status', $status)->count();
    }

    private function countMovementEvents(array $movementTypes, ?Carbon $dateFrom, ?Carbon $dateTo): int
    {
        $query = LotMovement::query()->whereIn('movement_type', $movementTypes, 'and', false);

        if ($dateFrom !== null) {
            $query->where('performed_at', '>=', $dateFrom);
        }

        if ($dateTo !== null) {
            $query->where('performed_at', '<=', $dateTo);
        }

        return $query->count();
    }

    /**
     * Products are flagged as low stock when recent outbound demand suggests
     * fewer than ~15 days of cover from currently available lots.
     */
    private function countLowStockProducts(): int
    {
        $availableByProduct = Lot::query()
            ->select('product_id', DB::raw('COUNT(*) as available_qty'))
            ->where('status', 'available')
            ->groupBy('product_id')
            ->pluck('available_qty', 'product_id');

        $outboundByProduct = DB::table('lot_movements')
            ->join('lots', 'lots.id', '=', 'lot_movements.lot_id')
            ->select('lots.product_id', DB::raw('COUNT(*) as outbound_qty'))
            ->whereIn('lot_movements.movement_type', ['consigned', 'disposed', 'returned_to_supplier'])
            ->where('lot_movements.performed_at', '>=', now()->subDays(30)->startOfDay())
            ->groupBy('lots.product_id')
            ->get();

        $lowStockCount = 0;

        foreach ($outboundByProduct as $row) {
            $outboundQty = (int) $row->outbound_qty;
            $availableQty = (int) ($availableByProduct[$row->product_id] ?? 0);
            $threshold = max(1, (int) ceil($outboundQty / 2));

            if ($outboundQty > 0 && $availableQty <= $threshold) {
                $lowStockCount++;
            }
        }

        return $lowStockCount;
    }

    /**
     * @param  array<int, string>  $movementTypes
     * @return array<int, array<string, int|string>>
     */
    private function buildMovementTrend(array $movementTypes, ?Carbon $dateFrom, ?Carbon $dateTo): array
    {
        $query = LotMovement::query()
            ->selectRaw('DATE(performed_at) as date, COUNT(*) as transaction_count, COUNT(*) as total_qty', [])
            ->whereIn('movement_type', $movementTypes, 'and', false);

        if ($dateFrom !== null) {
            $query->where('performed_at', '>=', $dateFrom);
        }

        if ($dateTo !== null) {
            $query->where('performed_at', '<=', $dateTo);
        }

        return $query
            ->groupBy(DB::raw('DATE(performed_at)'))
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => (string) $row->date,
                'transaction_count' => (int) $row->transaction_count,
                'total_qty' => (int) $row->total_qty,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, int|string|null>>
     */
    private function buildTopMovedProducts(?Carbon $dateFrom, ?Carbon $dateTo): array
    {
        $query = DB::table('lot_movements')
            ->join('lots', 'lots.id', '=', 'lot_movements.lot_id')
            ->join('products', 'products.id', '=', 'lots.product_id')
            ->select(
                'products.id as product_id',
                'products.product_name',
                'products.ref_num as product_code',
                DB::raw('COUNT(*) as moved_qty')
            )
            ->whereIn('lot_movements.movement_type', [
                'stock_in',
                'consigned',
                'returned',
                'used',
                'disposed',
                'returned_to_supplier',
            ]);

        if ($dateFrom !== null) {
            $query->where('lot_movements.performed_at', '>=', $dateFrom);
        }

        if ($dateTo !== null) {
            $query->where('lot_movements.performed_at', '<=', $dateTo);
        }

        return $query
            ->groupBy('products.id', 'products.product_name', 'products.ref_num')
            ->orderByDesc('moved_qty')
            ->orderBy('products.product_name')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'product_id' => (int) $row->product_id,
                'product_name' => $row->product_name,
                'product_code' => $row->product_code,
                'moved_qty' => (int) $row->moved_qty,
            ])
            ->all();
    }
}
