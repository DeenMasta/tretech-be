<?php

namespace App\Services\Dashboard;

use App\Models\Consignment;
use App\Models\Disposal;
use App\Models\Lot;
use App\Models\LotMovement;
use App\Models\Reconciliation;
use App\Models\ReturnSession;
use App\Models\StockIn;
use App\Models\SupplierReturn;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardSummaryService
{
    /**
     * Build the dashboard payload from real lot lifecycle data
     * and actual operations sub-module states.
     *
     * Every field maps 1-to-1 to a real status, model, or movement type.
     * No fabricated ERP vocabulary.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getSummary(array $filters = []): array
    {
        [$dateFrom, $dateTo] = $this->normalizeDateRange($filters);

        return [
            'lot_counts'          => $this->buildLotCounts(),
            'operations_pipeline' => $this->buildOperationsPipeline(),
            'today_activity'      => $this->buildTodayActivity(),
            'alerts'              => $this->buildAlerts(),
            'low_stock_risk_count' => $this->countLowStockProducts(),
            'stock_in_trend'      => $this->buildMovementTrend(['stock_in'], $dateFrom, $dateTo),
            'consignment_trend'   => $this->buildMovementTrend(['consigned'], $dateFrom, $dateTo),
            'top_moved_products'  => $this->buildTopMovedProducts($dateFrom, $dateTo),
        ];
    }

    // -------------------------------------------------------------------------
    // Lot Counts — one counter per real LotStatus enum value
    // -------------------------------------------------------------------------

    /**
     * @return array<string, int>
     */
    private function buildLotCounts(): array
    {
        $counts = Lot::query()
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        $statuses = ['available', 'holding', 'supplied', 'used', 'disposed', 'returned_to_supplier'];
        $result = [];
        $total = 0;

        foreach ($statuses as $status) {
            $value = (int) ($counts[$status] ?? 0);
            $result[$status] = $value;
            $total += $value;
        }

        $result['total'] = $total;

        return $result;
    }

    // -------------------------------------------------------------------------
    // Operations Pipeline — pending work across every sub-module
    // -------------------------------------------------------------------------

    /**
     * @return array<string, int>
     */
    private function buildOperationsPipeline(): array
    {
        $today = now()->toDateString();

        return [
            'stock_in_draft'              => StockIn::query()->where('status', 'draft')->count(),
            'stock_in_finalized_today'    => StockIn::query()
                ->where('status', 'finalized')
                ->whereDate('confirmed_at', $today)
                ->count(),
            'consignment_draft'           => Consignment::query()->where('status', 'draft')->count(),
            'consignment_confirmed_today' => Consignment::query()
                ->where('status', 'confirmed')
                ->whereDate('confirmed_at', $today)
                ->count(),
            'return_sessions_in_progress' => ReturnSession::query()->where('status', 'in_progress')->count(),
            'reconciliation_pending'      => Reconciliation::query()
                ->whereIn('status', ['pending', 'reopened'])
                ->count(),
            'disposal_draft'              => Disposal::query()->where('status', 'draft')->count(),
            'supplier_return_draft'       => SupplierReturn::query()->where('status', 'draft')->count(),
        ];
    }

    // -------------------------------------------------------------------------
    // Today's Activity — movement breakdown for today
    // -------------------------------------------------------------------------

    /**
     * @return array<string, int>
     */
    private function buildTodayActivity(): array
    {
        $today = now()->toDateString();

        $counts = LotMovement::query()
            ->select('movement_type', DB::raw('COUNT(*) as count'))
            ->whereDate('performed_at', $today)
            ->groupBy('movement_type')
            ->pluck('count', 'movement_type')
            ->all();

        $types = [
            'stock_in', 'consigned', 'returned', 'used',
            'disposed', 'returned_to_supplier', 'holding_released',
        ];

        $result = [];
        $total = 0;

        foreach ($types as $type) {
            $value = (int) ($counts[$type] ?? 0);
            $result[$type . '_count'] = $value;
            $total += $value;
        }

        $result['movements_total'] = $total;

        return $result;
    }

    // -------------------------------------------------------------------------
    // Alerts — real actionable items that need attention
    // -------------------------------------------------------------------------

    /**
     * @return array<string, int>
     */
    private function buildAlerts(): array
    {
        return [
            // Lots stuck in holding — need admin to assign lot numbers
            'holding_lots_pending' => Lot::query()
                ->where('status', 'holding')
                ->count(),

            // Lots expiring within 30 days (only available ones matter)
            'expiring_soon_30_days' => Lot::query()
                ->where('status', 'available')
                ->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '<=', now()->addDays(30)->toDateString())
                ->whereDate('expiry_date', '>=', now()->toDateString())
                ->count(),

            // Draft stock-in sessions older than 7 days
            'overdue_stock_in_drafts' => StockIn::query()
                ->where('status', 'draft')
                ->whereDate('stock_in_at', '<', now()->subDays(7)->toDateString())
                ->count(),

            // Reconciliations waiting for finalization
            'reconciliation_pending' => Reconciliation::query()
                ->whereIn('status', ['pending', 'reopened'])
                ->count(),
        ];
    }

    // -------------------------------------------------------------------------
    // Low Stock Heuristic
    // -------------------------------------------------------------------------

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

    // -------------------------------------------------------------------------
    // Trend Builders
    // -------------------------------------------------------------------------

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
            ->limit(10)
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
