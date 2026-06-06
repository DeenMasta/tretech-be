<?php

namespace Tests\Feature\Api\V1;

use App\Models\LotMovement;
use App\Models\StockIn;
use Laravel\Sanctum\Sanctum;

class DashboardTest extends FeatureTestCase
{
    public function test_guest_cannot_access_dashboard_summary(): void
    {
        $this->getJson('/api/v1/dashboard/summary')->assertStatus(401);
    }

    public function test_user_without_permission_cannot_access_dashboard_summary(): void
    {
        Sanctum::actingAs($this->makeUserWithPermissions([]));

        $this->getJson('/api/v1/dashboard/summary')->assertStatus(403);
    }

    public function test_dashboard_summary_returns_expected_shape(): void
    {
        Sanctum::actingAs($this->makeUserWithPermissions(['dashboard.view']));

        $this->getJson('/api/v1/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'lot_counts' => [
                        'available',
                        'holding',
                        'supplied',
                        'used',
                        'disposed',
                        'returned_to_supplier',
                        'total',
                    ],
                    'operations_pipeline' => [
                        'stock_in_draft',
                        'stock_in_finalized_today',
                        'consignment_draft',
                        'consignment_confirmed_today',
                        'return_sessions_in_progress',
                        'reconciliation_pending',
                        'disposal_draft',
                        'supplier_return_draft',
                    ],
                    'today_activity' => [
                        'movements_total',
                        'stock_in_count',
                        'consigned_count',
                        'returned_count',
                        'used_count',
                        'disposed_count',
                        'returned_to_supplier_count',
                        'holding_released_count',
                    ],
                    'alerts' => [
                        'holding_lots_pending',
                        'expiring_soon_30_days',
                        'overdue_stock_in_drafts',
                        'reconciliation_pending',
                    ],
                    'low_stock_risk_count',
                    'stock_in_trend',
                    'consignment_trend',
                    'top_moved_products',
                ],
            ]);
    }

    public function test_dashboard_summary_aggregates_lot_counts_and_pipeline(): void
    {
        $user = $this->makeUserWithPermissions(['dashboard.view']);
        $supplier = $this->createSupplier();
        $productA = $this->createProduct('PROD-DASH-A');
        $productB = $this->createProduct('PROD-DASH-B');

        Sanctum::actingAs($user);

        // Create lots with different statuses
        $this->createLot($productA, $supplier, 'available', 'LOT-DASH-AVL');
        $this->createLot($productA, $supplier, 'supplied', 'LOT-DASH-SUP');
        $this->createLot($productB, $supplier, 'holding', 'LOT-DASH-HLD');
        $this->createLot($productB, $supplier, 'returned_to_supplier', 'LOT-DASH-RTS');

        // Create a draft stock-in
        StockIn::query()->create([
            'supplier_id' => $supplier->id,
            'session_no' => 'STI-DASH-001',
            'do_number' => 'DO-DASH-001',
            'stock_in_at' => now()->subDay(),
            'pic_user_id' => $user->id,
            'status' => 'draft',
        ]);

        // Create a movement today
        $today = now()->subHour();
        LotMovement::query()->create([
            'lot_id' => 1,
            'movement_type' => 'stock_in',
            'performed_at' => $today,
            'performed_by_user_id' => $user->id,
        ]);

        $response = $this->getJson('/api/v1/dashboard/summary');

        $response->assertOk()
            // Lot counts
            ->assertJsonPath('data.lot_counts.available', 1)
            ->assertJsonPath('data.lot_counts.supplied', 1)
            ->assertJsonPath('data.lot_counts.holding', 1)
            ->assertJsonPath('data.lot_counts.returned_to_supplier', 1)
            ->assertJsonPath('data.lot_counts.total', 4)
            // Operations pipeline
            ->assertJsonPath('data.operations_pipeline.stock_in_draft', 1)
            // Today activity
            ->assertJsonPath('data.today_activity.stock_in_count', 1)
            ->assertJsonPath('data.today_activity.movements_total', 1)
            // Alerts
            ->assertJsonPath('data.alerts.holding_lots_pending', 1);
    }

    public function test_dashboard_trend_respects_date_filters(): void
    {
        $user = $this->makeUserWithPermissions(['dashboard.view']);
        $supplier = $this->createSupplier();
        $product = $this->createProduct('PROD-DASH-TREND');
        $lot = $this->createLot($product, $supplier, 'available', 'LOT-DASH-TREND');

        Sanctum::actingAs($user);

        $withinRange = now()->subDays(2);
        $outsideRange = now()->subDays(45);

        LotMovement::query()->create([
            'lot_id' => $lot->id,
            'movement_type' => 'stock_in',
            'performed_at' => $withinRange,
            'performed_by_user_id' => $user->id,
        ]);

        LotMovement::query()->create([
            'lot_id' => $lot->id,
            'movement_type' => 'stock_in',
            'performed_at' => $outsideRange,
            'performed_by_user_id' => $user->id,
        ]);

        $response = $this->getJson(
            '/api/v1/dashboard/summary?date_from=' . now()->subDays(7)->toDateString() . '&date_to=' . now()->toDateString()
        );

        $response->assertOk();

        $stockInTrendDates = collect($response->json('data.stock_in_trend'))->pluck('date')->all();
        $this->assertContains($withinRange->toDateString(), $stockInTrendDates);
        $this->assertNotContains($outsideRange->toDateString(), $stockInTrendDates);
    }

    public function test_dashboard_top_moved_products(): void
    {
        $user = $this->makeUserWithPermissions(['dashboard.view']);
        $supplier = $this->createSupplier();
        $productA = $this->createProduct('PROD-DASH-A');
        $productB = $this->createProduct('PROD-DASH-B');
        $lotA = $this->createLot($productA, $supplier, 'supplied', 'LOT-DASH-A');
        $lotB = $this->createLot($productB, $supplier, 'available', 'LOT-DASH-B');

        Sanctum::actingAs($user);

        $withinRange = now()->subDays(2);

        // 3 movements for product A
        LotMovement::query()->create([
            'lot_id' => $lotA->id,
            'movement_type' => 'stock_in',
            'performed_at' => $withinRange,
            'performed_by_user_id' => $user->id,
        ]);
        LotMovement::query()->create([
            'lot_id' => $lotA->id,
            'movement_type' => 'consigned',
            'performed_at' => $withinRange,
            'performed_by_user_id' => $user->id,
        ]);
        LotMovement::query()->create([
            'lot_id' => $lotA->id,
            'movement_type' => 'returned',
            'performed_at' => $withinRange,
            'performed_by_user_id' => $user->id,
        ]);

        // 1 movement for product B
        LotMovement::query()->create([
            'lot_id' => $lotB->id,
            'movement_type' => 'stock_in',
            'performed_at' => $withinRange,
            'performed_by_user_id' => $user->id,
        ]);

        $response = $this->getJson(
            '/api/v1/dashboard/summary?date_from=' . now()->subDays(7)->toDateString() . '&date_to=' . now()->toDateString()
        );

        $response->assertOk();

        $topMovedProducts = collect($response->json('data.top_moved_products'));
        $this->assertSame('PROD-DASH-A', $topMovedProducts->first()['product_code'] ?? null);
        $this->assertSame(3, $topMovedProducts->first()['moved_qty'] ?? null);
    }
}
