<?php

namespace Tests\Feature\Api\V1;

use App\Models\InstrumentSet;
use App\Models\InstrumentSetItem;
use App\Models\Lot;
use App\Models\LotHolding;
use App\Models\Product;
use App\Models\StockIn;
use App\Models\StockInItem;
use Laravel\Sanctum\Sanctum;

class StockInTest extends FeatureTestCase
{
    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_guest_cannot_list_stock_in_sessions(): void
    {
        $this->getJson('/api/v1/stock-in-sessions')
            ->assertStatus(401);
    }

    public function test_user_without_permission_cannot_list_stock_in_sessions(): void
    {
        Sanctum::actingAs($this->makeUserWithPermissions([]));

        $this->getJson('/api/v1/stock-in-sessions')
            ->assertStatus(403);
    }

    public function test_user_with_permission_can_list_stock_in_sessions(): void
    {
        $user = $this->makeUserWithPermissions(['stock_in.view']);
        Sanctum::actingAs($user);

        $supplier = $this->createSupplier();
        StockIn::query()->create([
            'supplier_id' => $supplier->id,
            'session_no' => 'SI-20250101-0001',
            'do_number' => 'DO-001',
            'stock_in_at' => now(),
            'pic_user_id' => $user->id,
            'status' => 'draft',
        ]);

        $response = $this->getJson('/api/v1/stock-in-sessions');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data', 'pagination' => ['total', 'per_page', 'current_page']]);

        $this->assertGreaterThanOrEqual(1, $response->json('pagination.total'));
    }

    // -------------------------------------------------------------------------
    // Store (create session)
    // -------------------------------------------------------------------------

    public function test_can_create_stock_in_session(): void
    {
        $user = $this->makeUserWithPermissions(['stock_in.create']);
        $supplier = $this->createSupplier();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/stock-in-sessions', [
            'supplier_id' => $supplier->id,
            'do_number' => 'DO-TEST-001',
            'stock_in_at' => now()->toDateString(),
            'pic_user_id' => $user->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseHas('stock_ins', ['do_number' => 'DO-TEST-001']);
    }

    public function test_create_session_requires_supplier_id(): void
    {
        $user = $this->makeUserWithPermissions(['stock_in.create']);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/stock-in-sessions', [
            'do_number' => 'DO-TEST-002',
            'stock_in_at' => now()->toDateString(),
            'pic_user_id' => $user->id,
        ])->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Add item
    // -------------------------------------------------------------------------

    public function test_can_add_item_to_draft_session(): void
    {
        $user = $this->makeUserWithPermissions(['stock_in.edit_draft']);
        $supplier = $this->createSupplier();
        $product = $this->createProduct();
        Sanctum::actingAs($user);

        $session = StockIn::query()->create([
            'supplier_id' => $supplier->id,
            'session_no' => 'SI-20250101-0002',
            'do_number' => 'DO-002',
            'stock_in_at' => now(),
            'pic_user_id' => $user->id,
            'status' => 'draft',
        ]);

        $response = $this->postJson("/api/v1/stock-in-sessions/{$session->id}/items", [
            'product_id' => $product->id,
            'scanned_lot_number' => 'LOTABC001',
            'manufacturing_date' => '2026-01-01',
            'expiry_date' => '2027-01-01',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('stock_in_items', [
            'stock_in_id' => $session->id,
            'scanned_lot_number' => 'LOTABC001',
        ]);
    }

    public function test_adding_item_with_missing_lot_flag_requires_override_reason(): void
    {
        $user = $this->makeUserWithPermissions(['stock_in.edit_draft']);
        $supplier = $this->createSupplier();
        $product = $this->createProduct();
        Sanctum::actingAs($user);

        $session = StockIn::query()->create([
            'supplier_id' => $supplier->id,
            'session_no' => 'SI-20250101-0003',
            'do_number' => 'DO-003',
            'stock_in_at' => now(),
            'pic_user_id' => $user->id,
            'status' => 'draft',
        ]);

        // missing_lot_flag=true without entry_override_reason should be 422
        $this->postJson("/api/v1/stock-in-sessions/{$session->id}/items", [
            'product_id' => $product->id,
            'manufacturing_date' => '2026-01-01',
            'expiry_date' => '2027-01-01',
            'missing_lot_flag' => true,
        ])->assertStatus(422);
    }

    public function test_can_add_item_without_lot_number_when_product_does_not_require_lot(): void
    {
        $user = $this->makeUserWithPermissions(['stock_in.edit_draft']);
        $supplier = $this->createSupplier();
        $product = $this->createProduct();
        $product->update(['requires_lot' => false]);
        Sanctum::actingAs($user);

        $session = StockIn::query()->create([
            'supplier_id' => $supplier->id,
            'session_no' => 'SI-20250101-0004',
            'do_number' => 'DO-004',
            'stock_in_at' => now(),
            'pic_user_id' => $user->id,
            'status' => 'draft',
        ]);

        $response = $this->postJson("/api/v1/stock-in-sessions/{$session->id}/items", [
            'product_id' => $product->id,
            'manufacturing_date' => '2026-01-01',
            'expiry_date' => '2027-01-01',
            'missing_lot_flag' => false,
        ]);

        $response->assertCreated()->assertJsonPath('success', true);

        $this->assertDatabaseHas('stock_in_items', [
            'stock_in_id' => $session->id,
            'product_id' => $product->id,
            'scanned_lot_number' => null,
            'missing_lot_flag' => false,
        ]);
    }

    // -------------------------------------------------------------------------
    // Finalize — normal path (lot gets 'available')
    // -------------------------------------------------------------------------

    public function test_finalize_creates_available_lot_for_normal_item(): void
    {
        $user = $this->makeUserWithPermissions(['stock_in.confirm']);
        $supplier = $this->createSupplier();
        $product = $this->createProduct();
        Sanctum::actingAs($user);

        $session = StockIn::query()->create([
            'supplier_id' => $supplier->id,
            'session_no' => 'SI-20250101-0010',
            'do_number' => 'DO-010',
            'stock_in_at' => now(),
            'pic_user_id' => $user->id,
            'status' => 'draft',
        ]);

        StockInItem::query()->create([
            'stock_in_id' => $session->id,
            'product_id' => $product->id,
            'scanned_lot_number' => 'LOT-NORMAL-001',
            'manufacturing_date' => '2026-01-01',
            'expiry_date' => '2027-01-01',
            'lot_entry_mode' => 'scan',
            'missing_lot_flag' => false,
        ]);

        $response = $this->postJson("/api/v1/stock-in-sessions/{$session->id}/finalize");

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('lots', [
            'lot_number' => 'LOT-NORMAL-001',
            'status' => 'available',
        ]);
    }

    // -------------------------------------------------------------------------
    // Finalize — holding path (lot gets 'holding' + LotHolding record)
    // -------------------------------------------------------------------------

    public function test_finalize_creates_holding_lot_when_lot_number_is_missing(): void
    {
        $user = $this->makeUserWithPermissions(['stock_in.confirm']);
        $supplier = $this->createSupplier();
        $product = $this->createProduct();
        Sanctum::actingAs($user);

        $session = StockIn::query()->create([
            'supplier_id' => $supplier->id,
            'session_no' => 'SI-20250101-0020',
            'do_number' => 'DO-020',
            'stock_in_at' => now(),
            'pic_user_id' => $user->id,
            'status' => 'draft',
        ]);

        StockInItem::query()->create([
            'stock_in_id' => $session->id,
            'product_id' => $product->id,
            'manufacturing_date' => '2026-01-01',
            'expiry_date' => '2027-01-01',
            'lot_entry_mode' => 'manual',
            'missing_lot_flag' => true,
            'entry_override_reason' => 'Lot label missing on delivery',
        ]);

        $response = $this->postJson("/api/v1/stock-in-sessions/{$session->id}/finalize");

        $response->assertOk()
            ->assertJsonPath('success', true);

        // A holding lot should have been created
        $lot = Lot::query()
            ->where('product_id', $product->id)
            ->where('status', 'holding')
            ->first();

        $this->assertNotNull($lot);
        $this->assertDatabaseHas('lot_holdings', ['lot_id' => $lot->id]);
    }

    public function test_finalize_generates_available_lot_for_instrument_product(): void
    {
        $user = $this->makeUserWithPermissions(['stock_in.edit_draft', 'stock_in.confirm']);
        $supplier = $this->createSupplier();
        $product = $this->createProduct();
        $product->update([
            'product_type' => 'Instrument',
            'requires_lot' => true,
        ]);
        Sanctum::actingAs($user);

        $session = StockIn::query()->create([
            'supplier_id' => $supplier->id,
            'session_no' => 'SI-20250101-0025',
            'do_number' => 'DO-0025',
            'stock_in_at' => now(),
            'pic_user_id' => $user->id,
            'status' => 'draft',
        ]);

        $this->postJson("/api/v1/stock-in-sessions/{$session->id}/items", [
            'product_id' => $product->id,
            'manufacturing_date' => '2026-01-01',
            'expiry_date' => '2027-01-01',
            'generate_lot_number' => true,
        ])->assertCreated()
            ->assertJsonPath('data.generate_lot_number', true);

        $this->postJson("/api/v1/stock-in-sessions/{$session->id}/finalize")
            ->assertOk()
            ->assertJsonPath('success', true);

        $lot = Lot::query()->where('product_id', $product->id)->latest('id')->first();
        $this->assertNotNull($lot);
        $this->assertSame('available', $lot->status);
        $this->assertTrue($lot->is_system_generated_lot);
        $this->assertDatabaseMissing('lot_holdings', ['lot_id' => $lot->id]);
    }

    public function test_finalize_creates_available_lot_for_non_lot_product_without_lot_number(): void
    {
        $user = $this->makeUserWithPermissions(['stock_in.confirm']);
        $supplier = $this->createSupplier();
        $product = $this->createProduct();
        $product->update(['requires_lot' => false]);
        Sanctum::actingAs($user);

        $session = StockIn::query()->create([
            'supplier_id' => $supplier->id,
            'session_no' => 'SI-20250101-0021',
            'do_number' => 'DO-021',
            'stock_in_at' => now(),
            'pic_user_id' => $user->id,
            'status' => 'draft',
        ]);

        StockInItem::query()->create([
            'stock_in_id' => $session->id,
            'product_id' => $product->id,
            'manufacturing_date' => '2026-01-01',
            'expiry_date' => '2027-01-01',
            'lot_entry_mode' => 'scan',
            'missing_lot_flag' => false,
            'entry_override_reason' => null,
        ]);

        $response = $this->postJson("/api/v1/stock-in-sessions/{$session->id}/finalize");

        $response->assertOk()->assertJsonPath('success', true);

        $lot = Lot::query()
            ->where('product_id', $product->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($lot);
        $this->assertSame('available', $lot->status);
        $this->assertStringStartsWith('AUTO-', $lot->lot_number);
        $this->assertDatabaseMissing('lot_holdings', ['lot_id' => $lot->id]);
    }

    public function test_finalize_set_entry_creates_set_instance_lot(): void
    {
        $user = $this->makeUserWithPermissions(['stock_in.confirm']);
        $supplier = $this->createSupplier();
        Sanctum::actingAs($user);

        $set = InstrumentSet::query()->create([
            'set_code' => 'SET-GEN-01',
            'set_name' => 'General Surgery Starter Set',
            'description' => 'Tracked instance test',
            'is_active' => true,
        ]);

        $instrumentA = Product::query()->create([
            'product_name' => 'Scalpel Handle',
            'ref_num' => 'REF-001',
            'is_active' => true,
        ]);

        InstrumentSetItem::query()->create([
            'instrument_set_id' => $set->id,
            'product_id' => $instrumentA->id,
            'quantity' => 1,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $session = StockIn::query()->create([
            'supplier_id' => $supplier->id,
            'session_no' => 'SI-20250101-9001',
            'do_number' => 'DO-9001',
            'stock_in_at' => now()->startOfDay(),
            'pic_user_id' => $user->id,
            'status' => 'draft',
        ]);

        StockInItem::query()->create([
            'stock_in_id' => $session->id,
            'entry_kind' => StockInItem::ENTRY_KIND_SET,
            'instrument_set_id' => $set->id,
            'lot_entry_mode' => 'scan',
            'expiry_entry_mode' => 'manual',
            'missing_lot_flag' => false,
        ]);

        $response = $this->postJson("/api/v1/stock-in-sessions/{$session->id}/finalize");

        $response->assertOk()->assertJsonPath('success', true);

        $lot = Lot::query()
            ->where('instrument_set_id', $set->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($lot);
        $this->assertStringStartsWith('COMP-SET-GEN-01-', $lot->lot_number);
        $this->assertSame('available', $lot->status);
    }

    public function test_finalize_set_entry_uses_supplied_component_lots_and_generates_only_selected_ones(): void
    {
        $user = $this->makeUserWithPermissions(['stock_in.edit_draft', 'stock_in.confirm']);
        $supplier = $this->createSupplier();
        Sanctum::actingAs($user);

        $set = InstrumentSet::query()->create([
            'set_code' => 'ORTHO-SET',
            'set_name' => 'Orthopaedic Set',
            'is_active' => true,
        ]);
        $manualProduct = $this->createProduct();
        $generatedProduct = $this->createProduct();
        $manualComponent = InstrumentSetItem::query()->create([
            'instrument_set_id' => $set->id,
            'product_id' => $manualProduct->id,
            'quantity' => 1,
        ]);
        $generatedComponent = InstrumentSetItem::query()->create([
            'instrument_set_id' => $set->id,
            'product_id' => $generatedProduct->id,
            'quantity' => 2,
        ]);
        $session = StockIn::query()->create([
            'supplier_id' => $supplier->id,
            'session_no' => 'SI-20250101-9002',
            'do_number' => 'DO-9002',
            'stock_in_at' => now()->startOfDay(),
            'pic_user_id' => $user->id,
            'status' => 'draft',
        ]);

        $this->postJson("/api/v1/stock-in-sessions/{$session->id}/items", [
            'entry_kind' => 'set',
            'instrument_set_id' => $set->id,
            'component_lots' => [
                [
                    'instrument_set_item_id' => $manualComponent->id,
                    'lot_number' => 'SUPPLIER-LOT-001',
                    'generate_lot_number' => false,
                ],
                [
                    'instrument_set_item_id' => $generatedComponent->id,
                    'lot_number' => null,
                    'generate_lot_number' => true,
                ],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.component_lots.0.lot_number', 'SUPPLIER-LOT-001');

        $this->postJson("/api/v1/stock-in-sessions/{$session->id}/finalize")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('lots', [
            'product_id' => $manualProduct->id,
            'lot_number' => 'SUPPLIER-LOT-001',
            'status' => 'available',
            'is_system_generated_lot' => false,
        ]);
        $generatedLot = Lot::query()->where('product_id', $generatedProduct->id)->latest('id')->first();
        $this->assertNotNull($generatedLot);
        $this->assertStringStartsWith('COMP-ORTHO-SET-', $generatedLot->lot_number);
        $this->assertSame('available', $generatedLot->status);
        $this->assertTrue($generatedLot->is_system_generated_lot);
        $this->assertDatabaseMissing('lot_holdings', ['lot_id' => $generatedLot->id]);
    }

    // -------------------------------------------------------------------------
    // Finalize — empty session
    // -------------------------------------------------------------------------

    public function test_finalize_empty_session_returns_422(): void
    {
        $user = $this->makeUserWithPermissions(['stock_in.confirm']);
        $supplier = $this->createSupplier();
        Sanctum::actingAs($user);

        $session = StockIn::query()->create([
            'supplier_id' => $supplier->id,
            'session_no' => 'SI-20250101-0030',
            'do_number' => 'DO-030',
            'stock_in_at' => now(),
            'pic_user_id' => $user->id,
            'status' => 'draft',
        ]);

        $this->postJson("/api/v1/stock-in-sessions/{$session->id}/finalize")
            ->assertStatus(400);
    }

    // -------------------------------------------------------------------------
    // Show & review
    // -------------------------------------------------------------------------

    public function test_can_show_a_stock_in_session(): void
    {
        $user = $this->makeUserWithPermissions(['stock_in.view']);
        $supplier = $this->createSupplier();
        Sanctum::actingAs($user);

        $session = StockIn::query()->create([
            'supplier_id' => $supplier->id,
            'session_no' => 'SI-20250101-0040',
            'do_number' => 'DO-040',
            'stock_in_at' => now(),
            'pic_user_id' => $user->id,
            'status' => 'draft',
        ]);

        $this->getJson("/api/v1/stock-in-sessions/{$session->id}")
            ->assertOk()
            ->assertJsonPath('data.session_no', 'SI-20250101-0040');
    }

    public function test_can_get_review_for_draft_session(): void
    {
        $user = $this->makeUserWithPermissions(['stock_in.view']);
        $supplier = $this->createSupplier();
        $product = $this->createProduct();
        Sanctum::actingAs($user);

        $session = StockIn::query()->create([
            'supplier_id' => $supplier->id,
            'session_no' => 'SI-20250101-0050',
            'do_number' => 'DO-050',
            'stock_in_at' => now(),
            'pic_user_id' => $user->id,
            'status' => 'draft',
        ]);

        StockInItem::query()->create([
            'stock_in_id' => $session->id,
            'product_id' => $product->id,
            'scanned_lot_number' => 'LOT-REVIEW-001',
            'manufacturing_date' => '2026-01-01',
            'expiry_date' => '2027-01-01',
            'missing_lot_flag' => false,
        ]);

        $this->getJson("/api/v1/stock-in-sessions/{$session->id}/review")
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}
