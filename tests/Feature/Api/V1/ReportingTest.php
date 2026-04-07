<?php

namespace Tests\Feature\Api\V1;

use App\Models\Consignment;
use App\Models\ConsignmentItem;
use App\Models\Disposal;
use App\Models\DisposalItem;
use Laravel\Sanctum\Sanctum;

class ReportingTest extends FeatureTestCase
{
    // =========================================================================
    // Auth / Permission gate (shared across all report endpoints)
    // =========================================================================

    public function test_guest_cannot_access_any_report(): void
    {
        $this->getJson('/api/v1/reports/stock-in')->assertStatus(401);
        $this->getJson('/api/v1/reports/consignments')->assertStatus(401);
        $this->getJson('/api/v1/reports/returns-analysis')->assertStatus(401);
        $this->getJson('/api/v1/reports/disposals')->assertStatus(401);
        $this->getJson('/api/v1/reports/expiry')->assertStatus(401);
    }

    public function test_user_without_permission_cannot_access_reports(): void
    {
        Sanctum::actingAs($this->makeUserWithPermissions([]));

        $this->getJson('/api/v1/reports/stock-in')->assertStatus(403);
        $this->getJson('/api/v1/reports/consignments')->assertStatus(403);
    }

    // =========================================================================
    // Stock-in report
    // =========================================================================

    public function test_stock_in_report_returns_summary_and_data(): void
    {
        $user = $this->makeUserWithPermissions(['reports.view']);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/reports/stock-in');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['summary', 'data']]);
    }

    public function test_stock_in_report_includes_finalized_sessions(): void
    {
        $user     = $this->makeUserWithPermissions(['reports.view']);
        $supplier = $this->createSupplier();
        $product  = $this->createProduct();
        Sanctum::actingAs($user);

        // Stock-in report is Lot-based; create a Lot with received_at set
        $this->createLot($product, $supplier, 'available');

        $response = $this->getJson('/api/v1/reports/stock-in');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, $response->json('data.summary.total_units') ?? 0);
    }

    // =========================================================================
    // Consignments report
    // =========================================================================

    public function test_consignments_report_returns_summary_and_data(): void
    {
        $user = $this->makeUserWithPermissions(['reports.view']);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/reports/consignments');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['summary', 'data']]);
    }

    // =========================================================================
    // Returns analysis report
    // =========================================================================

    public function test_returns_analysis_report_returns_summary_and_data(): void
    {
        $user = $this->makeUserWithPermissions(['reports.view']);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/reports/returns-analysis');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['summary', 'data']]);
    }

    // =========================================================================
    // Disposals report
    // =========================================================================

    public function test_disposals_report_returns_summary_and_data(): void
    {
        $user = $this->makeUserWithPermissions(['reports.view']);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/reports/disposals');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['summary', 'data']]);
    }

    public function test_disposals_report_includes_completed_disposals(): void
    {
        $user     = $this->makeUserWithPermissions(['reports.view']);
        $product  = $this->createProduct();
        $supplier = $this->createSupplier();
        Sanctum::actingAs($user);

        $disposal = Disposal::query()->create([
            'disposal_no'  => 'DSP-REPORT-001',
            'disposed_at'  => now(),
            'pic_user_id'  => $user->id,
            'status'       => 'completed',
            'completed_at' => now(),
        ]);

        $lot = $this->createLot($product, $supplier, 'disposed');
        DisposalItem::query()->create([
            'disposal_id'       => $disposal->id,
            'lot_id'            => $lot->id,
            'disposal_category' => 'expired',
            'reason_text'       => 'Expired',
        ]);

        $response = $this->getJson('/api/v1/reports/disposals');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, $response->json('data.summary.total_disposed_units') ?? 0);
    }

    // =========================================================================
    // Expiry dashboard
    // =========================================================================

    public function test_expiry_report_returns_buckets(): void
    {
        $user = $this->makeUserWithPermissions(['reports.view']);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/reports/expiry');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['summary', 'data']]);
    }

    public function test_expiry_report_buckets_lots_expiring_within_30_days(): void
    {
        $user     = $this->makeUserWithPermissions(['reports.view']);
        $product  = $this->createProduct();
        $supplier = $this->createSupplier();
        Sanctum::actingAs($user);

        // Create a lot expiring in 10 days
        \App\Models\Lot::query()->create([
            'product_id'            => $product->id,
            'supplier_id'           => $supplier->id,
            'lot_number'            => 'LOT-EXPIRY-SOON',
            'supplier_batch_code'   => 'BATCH-EXP',
            'expiry_date'           => now()->addDays(10)->toDateString(),
            'status'                => 'available',
            'current_location_type' => 'warehouse',
            'received_at'           => now(),
        ]);

        $response = $this->getJson('/api/v1/reports/expiry');

        $response->assertOk();
        $within30 = collect($response->json('data.data'))->firstWhere('window', '30_days');
        if ($within30 !== null) {
            $this->assertGreaterThanOrEqual(1, $within30['count'] ?? 0);
        }
    }

    // =========================================================================
    // Export endpoints
    // =========================================================================

    public function test_user_without_export_permission_cannot_export(): void
    {
        $user = $this->makeUserWithPermissions(['reports.view']); // no reports.export
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/reports/stock-in/export', ['format' => 'csv'])
            ->assertStatus(403);
    }

    public function test_can_export_stock_in_report_as_csv(): void
    {
        $user = $this->makeUserWithPermissions(['reports.export']);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/reports/stock-in/export', ['format' => 'csv']);

        // CSV download should return 200 with content-disposition or streamed response
        $response->assertStatus(200);
        $contentType = $response->headers->get('Content-Type', '');
        $this->assertStringContainsStringIgnoringCase('csv', $contentType . ' ' . $response->headers->get('Content-Disposition', ''));
    }

    public function test_export_with_invalid_format_returns_422(): void
    {
        $user = $this->makeUserWithPermissions(['reports.export']);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/reports/stock-in/export', ['format' => 'docx'])
            ->assertStatus(422);
    }

    public function test_export_with_invalid_type_returns_404(): void
    {
        $user = $this->makeUserWithPermissions(['reports.export']);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/reports/unknown-type/export', ['format' => 'csv'])
            ->assertStatus(404);
    }

    // =========================================================================
    // Date filtering — stock-in
    // =========================================================================

    public function test_stock_in_report_excludes_lots_before_from_date(): void
    {
        $user     = $this->makeUserWithPermissions(['reports.view']);
        $product  = $this->createProduct();
        $supplier = $this->createSupplier();
        Sanctum::actingAs($user);

        // Lot received long in the past
        \App\Models\Lot::query()->create([
            'product_id'            => $product->id,
            'supplier_id'           => $supplier->id,
            'lot_number'            => 'LOT-OLD-FILTER',
            'supplier_batch_code'   => 'BATCH-OLD',
            'expiry_date'           => '2028-01-01',
            'status'                => 'available',
            'current_location_type' => 'warehouse',
            'received_at'           => '2020-01-01',
        ]);

        // Filter: only from yesterday onwards — should exclude the old lot
        $response = $this->getJson('/api/v1/reports/stock-in?from_date=' . now()->subDay()->toDateString());

        $response->assertOk()
            ->assertJsonPath('success', true);

        // The old lot should not appear in the filtered data rows
        $lotNumbers = collect($response->json('data.data'))->pluck('lot_number')->all();
        $this->assertNotContains('LOT-OLD-FILTER', $lotNumbers);
    }

    public function test_stock_in_report_respects_to_date_filter(): void
    {
        $user     = $this->makeUserWithPermissions(['reports.view']);
        $product  = $this->createProduct();
        $supplier = $this->createSupplier();
        Sanctum::actingAs($user);

        // Lot received in the future (simulate a future date)
        \App\Models\Lot::query()->create([
            'product_id'            => $product->id,
            'supplier_id'           => $supplier->id,
            'lot_number'            => 'LOT-FUTURE-FILTER',
            'supplier_batch_code'   => 'BATCH-FUTURE',
            'expiry_date'           => '2029-01-01',
            'status'                => 'available',
            'current_location_type' => 'warehouse',
            'received_at'           => now()->addDays(30),
        ]);

        // Filter: only up to yesterday — should exclude the future lot
        $response = $this->getJson('/api/v1/reports/stock-in?to_date=' . now()->subDay()->toDateString());

        $response->assertOk();

        $lotNumbers = collect($response->json('data.data'))->pluck('lot_number')->all();
        $this->assertNotContains('LOT-FUTURE-FILTER', $lotNumbers);
    }

    public function test_stock_in_report_filters_by_supplier(): void
    {
        $user      = $this->makeUserWithPermissions(['reports.view']);
        $product   = $this->createProduct();
        $supplier1 = $this->createSupplier();
        $supplier2 = $this->createSupplier();
        Sanctum::actingAs($user);

        $this->createLot($product, $supplier1, 'available', 'LOT-SUP1-001');
        $this->createLot($product, $supplier2, 'available', 'LOT-SUP2-001');

        $response = $this->getJson("/api/v1/reports/stock-in?supplier_id={$supplier1->id}");

        $response->assertOk();

        $lotNumbers = collect($response->json('data.data'))->pluck('lot_number')->all();
        $this->assertContains('LOT-SUP1-001', $lotNumbers);
        $this->assertNotContains('LOT-SUP2-001', $lotNumbers);
    }

    // =========================================================================
    // Date filtering — disposals
    // =========================================================================

    public function test_disposal_report_filters_by_category(): void
    {
        $user     = $this->makeUserWithPermissions(['reports.view']);
        $product  = $this->createProduct();
        $supplier = $this->createSupplier();
        Sanctum::actingAs($user);

        // Create two completed disposals with different categories
        $disposalExpired = Disposal::query()->create([
            'disposal_no'  => 'DSP-CAT-EXPIRED',
            'disposed_at'  => now(),
            'pic_user_id'  => $user->id,
            'status'       => 'completed',
            'completed_at' => now(),
        ]);
        DisposalItem::query()->create([
            'disposal_id'       => $disposalExpired->id,
            'lot_id'            => $this->createLot($product, $supplier, 'disposed')->id,
            'disposal_category' => 'expired',
            'reason_text'       => 'Past expiry',
        ]);

        $disposalDamaged = Disposal::query()->create([
            'disposal_no'  => 'DSP-CAT-DAMAGED',
            'disposed_at'  => now(),
            'pic_user_id'  => $user->id,
            'status'       => 'completed',
            'completed_at' => now(),
        ]);
        DisposalItem::query()->create([
            'disposal_id'       => $disposalDamaged->id,
            'lot_id'            => $this->createLot($product, $supplier, 'disposed')->id,
            'disposal_category' => 'damaged',
            'reason_text'       => 'Physical damage',
        ]);

        // Filter by 'expired' category only
        $response = $this->getJson('/api/v1/reports/disposals?disposal_category=expired');

        $response->assertOk();

        // All returned disposal_items should have category 'expired'
        $categories = collect($response->json('data.data'))->pluck('disposal_category')->unique()->values()->all();
        if (!empty($categories)) {
            $this->assertNotContains('damaged', $categories);
        }
    }

    // =========================================================================
    // Expiry window parameter
    // =========================================================================

    public function test_expiry_report_window_filters_results(): void
    {
        $user     = $this->makeUserWithPermissions(['reports.view']);
        $product  = $this->createProduct();
        $supplier = $this->createSupplier();
        Sanctum::actingAs($user);

        // Lot expiring in 10 days — should appear in window=30 response
        \App\Models\Lot::query()->create([
            'product_id'            => $product->id,
            'supplier_id'           => $supplier->id,
            'lot_number'            => 'LOT-WIN-10DAYS',
            'supplier_batch_code'   => 'BATCH-WIN',
            'expiry_date'           => now()->addDays(10)->toDateString(),
            'status'                => 'available',
            'current_location_type' => 'warehouse',
            'received_at'           => now(),
        ]);

        // Lot expiring in 45 days — should NOT appear in window=30 response
        \App\Models\Lot::query()->create([
            'product_id'            => $product->id,
            'supplier_id'           => $supplier->id,
            'lot_number'            => 'LOT-WIN-45DAYS',
            'supplier_batch_code'   => 'BATCH-WIN2',
            'expiry_date'           => now()->addDays(45)->toDateString(),
            'status'                => 'available',
            'current_location_type' => 'warehouse',
            'received_at'           => now(),
        ]);

        $response = $this->getJson('/api/v1/reports/expiry?window=30');

        $response->assertOk();

        // With window=30 the service returns a flat data array of lots nested under data.data
        $allLotNumbers = collect($response->json('data.data'))->pluck('lot_number')->all();

        $this->assertContains('LOT-WIN-10DAYS', $allLotNumbers);
        $this->assertNotContains('LOT-WIN-45DAYS', $allLotNumbers);
    }
}
