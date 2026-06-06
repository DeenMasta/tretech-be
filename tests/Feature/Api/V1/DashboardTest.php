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
                    'items_in_stock',
                    'movements_today',
                    'low_stock_count',
                    'open_po_count',
                    'overdue_po_count',
                    'items_received_pending_qc',
                    'items_under_repair',
                    'items_delivered',
                    'items_returned',
                    'items_returned_to_supplier',
                    'stock_in_trend',
                    'stock_out_trend',
                    'top_moved_products',
                ],
            ]);
    }

    public function test_dashboard_summary_aggregates_live_metrics_and_respects_date_filters(): void
    {
        $user = $this->makeUserWithPermissions(['dashboard.view']);
        $supplier = $this->createSupplier();
        $productA = $this->createProduct('PROD-DASH-A');
        $productB = $this->createProduct('PROD-DASH-B');

        Sanctum::actingAs($user);

        $availableLot = $this->createLot($productA, $supplier, 'available', 'LOT-DASH-AVL');
        $suppliedLot = $this->createLot($productA, $supplier, 'supplied', 'LOT-DASH-SUP');
        $holdingLot = $this->createLot($productB, $supplier, 'holding', 'LOT-DASH-HLD');
        $returnedSupplierLot = $this->createLot($productB, $supplier, 'returned_to_supplier', 'LOT-DASH-RTS');

        StockIn::query()->create([
            'supplier_id' => $supplier->id,
            'session_no' => 'STI-DASH-001',
            'do_number' => 'DO-DASH-001',
            'stock_in_at' => now()->subDay(),
            'pic_user_id' => $user->id,
            'status' => 'draft',
        ]);

        $today = now()->subHour();
        $withinRange = now()->subDays(2);
        $outsideRange = now()->subDays(45);

        LotMovement::query()->create([
            'lot_id' => $availableLot->id,
            'movement_type' => 'stock_in',
            'performed_at' => $today,
            'performed_by_user_id' => $user->id,
        ]);

        LotMovement::query()->create([
            'lot_id' => $suppliedLot->id,
            'movement_type' => 'consigned',
            'performed_at' => $withinRange,
            'performed_by_user_id' => $user->id,
        ]);

        LotMovement::query()->create([
            'lot_id' => $availableLot->id,
            'movement_type' => 'returned',
            'performed_at' => $withinRange,
            'performed_by_user_id' => $user->id,
        ]);

        LotMovement::query()->create([
            'lot_id' => $returnedSupplierLot->id,
            'movement_type' => 'returned_to_supplier',
            'performed_at' => $withinRange,
            'performed_by_user_id' => $user->id,
        ]);

        LotMovement::query()->create([
            'lot_id' => $holdingLot->id,
            'movement_type' => 'stock_in',
            'performed_at' => $outsideRange,
            'performed_by_user_id' => $user->id,
        ]);

        $response = $this->getJson(
            '/api/v1/dashboard/summary?date_from=' . now()->subDays(7)->toDateString() . '&date_to=' . now()->toDateString()
        );

        $response->assertOk()
            ->assertJsonPath('data.items_in_stock', 1)
            ->assertJsonPath('data.movements_today', 1)
            ->assertJsonPath('data.low_stock_count', 2)
            ->assertJsonPath('data.open_po_count', 1)
            ->assertJsonPath('data.overdue_po_count', 1)
            ->assertJsonPath('data.items_received_pending_qc', 1)
            ->assertJsonPath('data.items_under_repair', 0)
            ->assertJsonPath('data.items_delivered', 1)
            ->assertJsonPath('data.items_returned', 1)
            ->assertJsonPath('data.items_returned_to_supplier', 1);

        $stockInTrendDates = collect($response->json('data.stock_in_trend'))->pluck('date')->all();
        $this->assertContains($today->toDateString(), $stockInTrendDates);
        $this->assertNotContains($outsideRange->toDateString(), $stockInTrendDates);

        $topMovedProducts = collect($response->json('data.top_moved_products'));
        $this->assertSame('PROD-DASH-A', $topMovedProducts->first()['product_code'] ?? null);
        $this->assertSame(3, $topMovedProducts->first()['moved_qty'] ?? null);
    }
}
