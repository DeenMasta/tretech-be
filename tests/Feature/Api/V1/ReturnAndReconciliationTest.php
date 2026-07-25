<?php

namespace Tests\Feature\Api\V1;

use App\Models\Consignment;
use App\Models\ConsignmentItem;
use App\Models\InstrumentSet;
use App\Models\Lot;
use App\Models\LotMovement;
use App\Models\Reconciliation;
use App\Models\ReturnSession;
use App\Models\ReturnSessionItem;
use App\Models\StockIn;
use App\Models\StockInItem;
use App\Models\InstrumentSetItem;
use Laravel\Sanctum\Sanctum;

class ReturnAndReconciliationTest extends FeatureTestCase
{
    // =========================================================================
    // Return Sessions
    // =========================================================================

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_guest_cannot_list_return_sessions(): void
    {
        $this->getJson('/api/v1/return-sessions')
            ->assertStatus(401);
    }

    public function test_user_without_permission_cannot_list_return_sessions(): void
    {
        Sanctum::actingAs($this->makeUserWithPermissions([]));

        $this->getJson('/api/v1/return-sessions')
            ->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Store (create return session)
    // -------------------------------------------------------------------------

    public function test_can_create_return_session_for_confirmed_consignment(): void
    {
        $user     = $this->makeUserWithPermissions(['returns.create']);
        $client   = $this->createClient();
        $product  = $this->createProduct();
        $supplier = $this->createSupplier();
        Sanctum::actingAs($user);

        $consignment = Consignment::query()->create([
            'client_id'      => $client->id,
            'consignment_no' => 'CN-20250201-0001',
            'consignment_at' => now(),
            'pic_user_id'    => $user->id,
            'status'         => 'confirmed',
        ]);

        $response = $this->postJson('/api/v1/return-sessions', [
            'consignment_id' => $consignment->id,
            'pic_user_id'    => $user->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'in_progress');

        $this->assertDatabaseHas('return_sessions', ['consignment_id' => $consignment->id]);
    }

    public function test_cannot_create_return_session_for_draft_consignment(): void
    {
        $user   = $this->makeUserWithPermissions(['returns.create']);
        $client = $this->createClient();
        Sanctum::actingAs($user);

        $consignment = Consignment::query()->create([
            'client_id'      => $client->id,
            'consignment_no' => 'CN-20250201-0002',
            'consignment_at' => now(),
            'pic_user_id'    => $user->id,
            'status'         => 'draft',
        ]);

        $this->postJson('/api/v1/return-sessions', [
            'consignment_id' => $consignment->id,
            'pic_user_id'    => $user->id,
        ])->assertStatus(400);
    }

    public function test_cannot_create_duplicate_return_session_for_same_consignment(): void
    {
        $user   = $this->makeUserWithPermissions(['returns.create']);
        $client = $this->createClient();
        Sanctum::actingAs($user);

        $consignment = Consignment::query()->create([
            'client_id'      => $client->id,
            'consignment_no' => 'CN-20250201-0003',
            'consignment_at' => now(),
            'pic_user_id'    => $user->id,
            'status'         => 'confirmed',
        ]);

        ReturnSession::query()->create([
            'consignment_id'    => $consignment->id,
            'return_session_no' => 'RS-20250201-0001',
            'pic_user_id'       => $user->id,
            'status'            => 'in_progress',
            'started_at'        => now(),
        ]);

        $this->postJson('/api/v1/return-sessions', [
            'consignment_id' => $consignment->id,
            'pic_user_id'    => $user->id,
        ])->assertStatus(400);
    }

    // -------------------------------------------------------------------------
    // Scan item
    // -------------------------------------------------------------------------

    public function test_can_scan_lot_into_return_session(): void
    {
        $user     = $this->makeUserWithPermissions(['returns.create']);
        $client   = $this->createClient();
        $product  = $this->createProduct();
        $supplier = $this->createSupplier();
        Sanctum::actingAs($user);

        $consignment = Consignment::query()->create([
            'client_id'      => $client->id,
            'consignment_no' => 'CN-20250201-0010',
            'consignment_at' => now(),
            'pic_user_id'    => $user->id,
            'status'         => 'confirmed',
        ]);

        $returnSession = ReturnSession::query()->create([
            'consignment_id'    => $consignment->id,
            'return_session_no' => 'RS-20250201-0010',
            'pic_user_id'       => $user->id,
            'status'            => 'in_progress',
            'started_at'        => now(),
        ]);

        $lot = $this->createLot($product, $supplier, 'supplied');

        // Attach lot to consignment
        ConsignmentItem::query()->create([
            'consignment_id'    => $consignment->id,
            'lot_id'            => $lot->id,
            'issued_at'         => now(),
            'issued_by_user_id' => $user->id,
        ]);

        $response = $this->postJson("/api/v1/return-sessions/{$returnSession->id}/scan", [
            'lot_id' => $lot->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('return_session_items', [
            'return_session_id' => $returnSession->id,
            'lot_id'            => $lot->id,
        ]);
    }

    public function test_can_update_return_session_item_remarks(): void
    {
        $user = $this->makeUserWithPermissions(['returns.create']);
        $client = $this->createClient();
        Sanctum::actingAs($user);

        $consignment = Consignment::query()->create([
            'client_id' => $client->id,
            'consignment_no' => 'CN-20250201-REMARKS',
            'consignment_at' => now(),
            'pic_user_id' => $user->id,
            'status' => 'confirmed',
        ]);
        $returnSession = ReturnSession::query()->create([
            'consignment_id' => $consignment->id,
            'return_session_no' => 'RS-20250201-REMARKS',
            'pic_user_id' => $user->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);
        $item = ReturnSessionItem::query()->create([
            'return_session_id' => $returnSession->id,
            'returned_at' => now(),
            'returned_by_user_id' => $user->id,
            'remarks' => 'Initial remark',
        ]);

        $this->patchJson("/api/v1/return-sessions/{$returnSession->id}/items/{$item->id}", [
            'remarks' => 'Returned with sealed packaging',
        ])->assertOk()
            ->assertJsonPath('data.remarks', 'Returned with sealed packaging');

        $this->assertDatabaseHas('return_session_items', [
            'id' => $item->id,
            'remarks' => 'Returned with sealed packaging',
        ]);

        $this->patchJson("/api/v1/return-sessions/{$returnSession->id}/items/{$item->id}", [
            'remarks' => '   ',
        ])->assertOk()
            ->assertJsonPath('data.remarks', null);

        $this->assertDatabaseHas('return_session_items', [
            'id' => $item->id,
            'remarks' => null,
        ]);
    }

    public function test_cannot_update_return_session_item_remarks_after_completion(): void
    {
        $user = $this->makeUserWithPermissions(['returns.create']);
        $client = $this->createClient();
        Sanctum::actingAs($user);

        $consignment = Consignment::query()->create([
            'client_id' => $client->id,
            'consignment_no' => 'CN-20250201-READONLY',
            'consignment_at' => now(),
            'pic_user_id' => $user->id,
            'status' => 'confirmed',
        ]);
        $returnSession = ReturnSession::query()->create([
            'consignment_id' => $consignment->id,
            'return_session_no' => 'RS-20250201-READONLY',
            'pic_user_id' => $user->id,
            'status' => 'completed',
            'started_at' => now(),
        ]);
        $item = ReturnSessionItem::query()->create([
            'return_session_id' => $returnSession->id,
            'returned_at' => now(),
            'returned_by_user_id' => $user->id,
        ]);

        $this->patchJson("/api/v1/return-sessions/{$returnSession->id}/items/{$item->id}", [
            'remarks' => 'Should not save',
        ])->assertStatus(400);
    }

    public function test_can_update_used_reconciliation_item_remarks(): void
    {
        $user = $this->makeUserWithPermissions(['returns.finalize']);
        $client = $this->createClient();
        $product = $this->createProduct();
        $supplier = $this->createSupplier();
        Sanctum::actingAs($user);

        $consignment = Consignment::query()->create([
            'client_id' => $client->id,
            'consignment_no' => 'CN-20250201-USAGE-REMARKS',
            'consignment_at' => now(),
            'pic_user_id' => $user->id,
            'status' => 'confirmed',
        ]);
        $returnSession = ReturnSession::query()->create([
            'consignment_id' => $consignment->id,
            'return_session_no' => 'RS-20250201-USAGE-REMARKS',
            'pic_user_id' => $user->id,
            'status' => 'completed',
            'started_at' => now(),
        ]);
        $reconciliation = Reconciliation::query()->create([
            'consignment_id' => $consignment->id,
            'return_session_id' => $returnSession->id,
            'reconciliation_no' => 'TRC25-USAGE-REMARKS',
            'pic_user_id' => $user->id,
            'status' => 'finalized',
        ]);
        $lot = $this->createLot($product, $supplier, 'used');
        $item = \App\Models\ReconciliationItem::query()->create([
            'reconciliation_id' => $reconciliation->id,
            'lot_id' => $lot->id,
            'result' => 'used',
        ]);

        $this->patchJson("/api/v1/reconciliations/{$reconciliation->id}/items/{$item->id}", [
            'remarks' => 'Opened and used during surgery',
        ])->assertOk()
            ->assertJsonPath('data.remarks', 'Opened and used during surgery');

        $this->assertDatabaseHas('reconciliation_items', [
            'id' => $item->id,
            'remarks' => 'Opened and used during surgery',
        ]);

        $component = \App\Models\ReconciliationSetInstrumentResult::query()->create([
            'reconciliation_item_id' => $item->id,
            'product_id' => $product->id,
            'expected_quantity' => 1,
            'returned_quantity' => 0,
            'used_quantity' => 1,
            'missing_quantity' => 0,
            'damaged_quantity' => 0,
            'result' => 'used',
        ]);

        $this->patchJson("/api/v1/reconciliations/{$reconciliation->id}/items/{$item->id}/components/{$component->id}", [
            'remarks' => 'Used component: reamer head',
        ])->assertOk()
            ->assertJsonPath('data.remarks', 'Used component: reamer head');

        $this->assertDatabaseHas('reconciliation_set_instrument_results', [
            'id' => $component->id,
            'remarks' => 'Used component: reamer head',
        ]);
    }

    // -------------------------------------------------------------------------
    // Complete return session
    // -------------------------------------------------------------------------

    public function test_can_complete_return_session_with_items(): void
    {
        $user     = $this->makeUserWithPermissions(['returns.finalize']);
        $client   = $this->createClient();
        $product  = $this->createProduct();
        $supplier = $this->createSupplier();
        Sanctum::actingAs($user);

        $consignment = Consignment::query()->create([
            'client_id'      => $client->id,
            'consignment_no' => 'CN-20250201-0020',
            'consignment_at' => now(),
            'pic_user_id'    => $user->id,
            'status'         => 'confirmed',
        ]);

        $returnSession = ReturnSession::query()->create([
            'consignment_id'    => $consignment->id,
            'return_session_no' => 'RS-20250201-0020',
            'pic_user_id'       => $user->id,
            'status'            => 'in_progress',
            'started_at'        => now(),
        ]);

        $lot = $this->createLot($product, $supplier, 'supplied');
        ConsignmentItem::query()->create([
            'consignment_id'    => $consignment->id,
            'lot_id'            => $lot->id,
            'issued_at'         => now(),
            'issued_by_user_id' => $user->id,
        ]);
        ReturnSessionItem::query()->create([
            'return_session_id'    => $returnSession->id,
            'lot_id'               => $lot->id,
            'returned_at'          => now(),
            'returned_by_user_id'  => $user->id,
            'remarks'              => 'Returned sealed',
        ]);

        $response = $this->postJson("/api/v1/return-sessions/{$returnSession->id}/complete");

        $response->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('reconciliation_items', [
            'lot_id' => $lot->id,
            'remarks' => 'Returned sealed',
        ]);
    }

    // =========================================================================
    // Reconciliations
    // =========================================================================

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_can_create_reconciliation_from_return_session(): void
    {
        $user   = $this->makeUserWithPermissions(['returns.finalize']);
        $client = $this->createClient();
        Sanctum::actingAs($user);

        $consignment = Consignment::query()->create([
            'client_id'      => $client->id,
            'consignment_no' => 'CN-20250201-0030',
            'consignment_at' => now(),
            'pic_user_id'    => $user->id,
            'status'         => 'confirmed',
        ]);

        $returnSession = ReturnSession::query()->create([
            'consignment_id'    => $consignment->id,
            'return_session_no' => 'RS-20250201-0030',
            'pic_user_id'       => $user->id,
            'status'            => 'completed',
            'started_at'        => now(),
            'completed_at'      => now(),
        ]);

        $response = $this->postJson('/api/v1/reconciliations', [
            'return_session_id' => $returnSession->id,
            'pic_user_id'       => $user->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('reconciliations', ['return_session_id' => $returnSession->id]);
    }

    // -------------------------------------------------------------------------
    // Finalize
    // -------------------------------------------------------------------------

    public function test_reconciliation_finalize_marks_lots_used(): void
    {
        $user     = $this->makeUserWithPermissions(['returns.finalize']);
        $client   = $this->createClient();
        $product  = $this->createProduct();
        $supplier = $this->createSupplier();
        Sanctum::actingAs($user);

        $consignment = Consignment::query()->create([
            'client_id'      => $client->id,
            'consignment_no' => 'CN-20250201-0040',
            'consignment_at' => now(),
            'pic_user_id'    => $user->id,
            'status'         => 'confirmed',
        ]);

        $returnSession = ReturnSession::query()->create([
            'consignment_id'    => $consignment->id,
            'return_session_no' => 'RS-20250201-0040',
            'pic_user_id'       => $user->id,
            'status'            => 'completed',
            'started_at'        => now(),
            'completed_at'      => now(),
        ]);

        $reconciliation = Reconciliation::query()->create([
            'consignment_id'    => $consignment->id,
            'return_session_id' => $returnSession->id,
            'reconciliation_no' => 'REC-20250201-0040',
            'pic_user_id'       => $user->id,
            'status'            => 'pending',
        ]);

        // Consigned lot (not returned → will be marked 'used')
        $lotUsed = $this->createLot($product, $supplier, 'supplied');
        ConsignmentItem::query()->create([
            'consignment_id'    => $consignment->id,
            'lot_id'            => $lotUsed->id,
            'issued_at'         => now(),
            'issued_by_user_id' => $user->id,
        ]);

        // Returned lot (returned → will be marked 'available')
        $lotReturned = $this->createLot($product, $supplier, 'supplied');
        ConsignmentItem::query()->create([
            'consignment_id'    => $consignment->id,
            'lot_id'            => $lotReturned->id,
            'issued_at'         => now(),
            'issued_by_user_id' => $user->id,
        ]);
        ReturnSessionItem::query()->create([
            'return_session_id'   => $returnSession->id,
            'lot_id'              => $lotReturned->id,
            'returned_at'         => now(),
            'returned_by_user_id' => $user->id,
        ]);

        $response = $this->postJson("/api/v1/reconciliations/{$reconciliation->id}/finalize");

        $response->assertOk()
            ->assertJsonPath('data.status', 'finalized');

        $this->assertDatabaseHas('lots', ['id' => $lotUsed->id, 'status' => 'used']);
        $this->assertDatabaseHas('lots', ['id' => $lotReturned->id, 'status' => 'available']);
    }

    public function test_reconciliation_finalize_records_product_component_results_for_set_lot(): void
    {
        $user = $this->makeUserWithPermissions(['returns.finalize']);
        $client = $this->createClient();
        $supplier = $this->createSupplier();
        $product = $this->createProduct();
        Sanctum::actingAs($user);

        $set = InstrumentSet::query()->create([
            'set_code' => 'SET-RET-01',
            'set_name' => 'Returned Set',
            'description' => 'Set tracking test',
            'is_active' => true,
        ]);

        InstrumentSetItem::query()->create([
            'instrument_set_id' => $set->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'sort_order' => 1,
            'remarks' => null,
        ]);

        $lot = Lot::query()->create([
            'product_id' => null,
            'instrument_set_id' => $set->id,
            'supplier_id' => $supplier->id,
            'lot_number' => 'SET-RET-LOT-001',
            'is_system_generated_lot' => true,
            'manufacturing_date' => '',
            'status' => 'supplied',
            'current_location_type' => 'client',
            'current_location_id' => $client->id,
            'received_at' => now(),
        ]);

        $stockIn = StockIn::query()->create([
            'supplier_id' => $supplier->id,
            'session_no' => 'SI-20250201-SET1',
            'do_number' => 'DO-SET1',
            'stock_in_at' => now(),
            'pic_user_id' => $user->id,
            'status' => 'finalized',
        ]);

        StockInItem::query()->create([
            'stock_in_id' => $stockIn->id,
            'entry_kind' => StockInItem::ENTRY_KIND_SET,
            'instrument_set_id' => $set->id,
            'lot_id' => $lot->id,
            'lot_entry_mode' => 'scan',
            'expiry_entry_mode' => 'manual',
            'missing_lot_flag' => false,
        ]);

        $consignment = Consignment::query()->create([
            'client_id' => $client->id,
            'consignment_no' => 'CN-20250201-SET1',
            'consignment_at' => now(),
            'pic_user_id' => $user->id,
            'status' => 'confirmed',
        ]);

        ConsignmentItem::query()->create([
            'consignment_id' => $consignment->id,
            'lot_id' => $lot->id,
            'issued_at' => now(),
            'issued_by_user_id' => $user->id,
        ]);

        $returnSession = ReturnSession::query()->create([
            'consignment_id' => $consignment->id,
            'return_session_no' => 'RS-20250201-SET1',
            'pic_user_id' => $user->id,
            'status' => 'completed',
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $returnItem = ReturnSessionItem::query()->create([
            'return_session_id' => $returnSession->id,
            'lot_id' => $lot->id,
            'returned_at' => now(),
            'returned_by_user_id' => $user->id,
        ]);

        $returnItem->setInstrumentItems()->create([
            'product_id' => $product->id,
            'returned_quantity' => 1,
            'remarks' => null,
        ]);

        $reconciliation = Reconciliation::query()->create([
            'consignment_id' => $consignment->id,
            'return_session_id' => $returnSession->id,
            'reconciliation_no' => 'REC-20250201-SET1',
            'pic_user_id' => $user->id,
            'status' => 'pending',
        ]);

        $response = $this->postJson("/api/v1/reconciliations/{$reconciliation->id}/finalize");

        $response->assertOk()->assertJsonPath('data.status', 'finalized');
        $this->assertDatabaseHas('reconciliation_set_instrument_results', [
            'reconciliation_item_id' => Reconciliation::query()->findOrFail($reconciliation->id)
                ->reconciliationItems()
                ->firstOrFail()
                ->id,
            'product_id' => $product->id,
            'expected_quantity' => 2,
            'returned_quantity' => 1,
            'used_quantity' => 1,
        ]);
    }

    public function test_reconciliation_finalize_restores_returned_generic_set_components_to_inventory(): void
    {
        $user = $this->makeUserWithPermissions(['returns.finalize']);
        $client = $this->createClient();
        $supplier = $this->createSupplier();
        $product = $this->createProduct();
        Sanctum::actingAs($user);

        $set = InstrumentSet::query()->create([
            'set_code' => 'SET-RESTOCK-01',
            'set_name' => 'Reusable Surgical Set',
            'is_active' => true,
        ]);
        InstrumentSetItem::query()->create([
            'instrument_set_id' => $set->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'sort_order' => 1,
        ]);

        $consignment = Consignment::query()->create([
            'client_id' => $client->id,
            'consignment_no' => 'CN-SET-RESTOCK-01',
            'consignment_at' => now(),
            'pic_user_id' => $user->id,
            'status' => 'confirmed',
        ]);
        ConsignmentItem::query()->create([
            'consignment_id' => $consignment->id,
            'instrument_set_id' => $set->id,
            'entry_kind' => 'set',
            'quantity' => 1,
            'issued_at' => now(),
            'issued_by_user_id' => $user->id,
        ]);

        $componentLot = Lot::query()->create([
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'lot_number' => 'COMP-RESTOCK-01',
            'status' => 'depleted',
            'current_location_type' => 'client',
            'current_location_id' => $client->id,
            'received_at' => now(),
            'quantity' => 2,
            'quantity_available' => 0,
            'quantity_consigned' => 2,
        ]);
        LotMovement::query()->create([
            'lot_id' => $componentLot->id,
            'movement_type' => 'consigned',
            'reference_type' => Consignment::class,
            'reference_id' => $consignment->id,
            'from_status' => 'available',
            'to_status' => 'depleted',
            'to_location_type' => 'client',
            'to_location_id' => $client->id,
            'performed_at' => now(),
            'performed_by_user_id' => $user->id,
            'remarks' => "Set component consigned via {$consignment->consignment_no} (set: {$set->set_name})",
            'quantity' => 2,
        ]);

        $returnSession = ReturnSession::query()->create([
            'consignment_id' => $consignment->id,
            'return_session_no' => 'RS-SET-RESTOCK-01',
            'pic_user_id' => $user->id,
            'status' => 'completed',
            'started_at' => now(),
            'completed_at' => now(),
        ]);
        $returnItem = ReturnSessionItem::query()->create([
            'return_session_id' => $returnSession->id,
            'instrument_set_id' => $set->id,
            'quantity' => 1,
            'returned_at' => now(),
            'returned_by_user_id' => $user->id,
        ]);
        $returnItem->setInstrumentItems()->create([
            'product_id' => $product->id,
            'returned_quantity' => 2,
        ]);

        $reconciliation = Reconciliation::query()->create([
            'consignment_id' => $consignment->id,
            'return_session_id' => $returnSession->id,
            'reconciliation_no' => 'REC-SET-RESTOCK-01',
            'pic_user_id' => $user->id,
            'status' => 'pending',
        ]);

        $this->postJson("/api/v1/reconciliations/{$reconciliation->id}/finalize")
            ->assertOk()
            ->assertJsonPath('data.status', 'finalized');

        $this->assertDatabaseHas('lots', [
            'id' => $componentLot->id,
            'status' => 'available',
            'quantity_available' => 2,
            'quantity_consigned' => 0,
        ]);
        $this->assertDatabaseHas('lot_movements', [
            'lot_id' => $componentLot->id,
            'movement_type' => 'returned',
            'reference_type' => Reconciliation::class,
            'reference_id' => $reconciliation->id,
            'quantity' => 2,
        ]);
    }

    public function test_cannot_finalize_reconciliation_with_no_consigned_lots(): void
    {
        $user   = $this->makeUserWithPermissions(['returns.finalize']);
        $client = $this->createClient();
        Sanctum::actingAs($user);

        $consignment = Consignment::query()->create([
            'client_id'      => $client->id,
            'consignment_no' => 'CN-20250201-0041',
            'consignment_at' => now(),
            'pic_user_id'    => $user->id,
            'status'         => 'confirmed',
        ]);

        $returnSession = ReturnSession::query()->create([
            'consignment_id'    => $consignment->id,
            'return_session_no' => 'RS-20250201-0041',
            'pic_user_id'       => $user->id,
            'status'            => 'completed',
            'started_at'        => now(),
            'completed_at'      => now(),
        ]);

        $reconciliation = Reconciliation::query()->create([
            'consignment_id'    => $consignment->id,
            'return_session_id' => $returnSession->id,
            'reconciliation_no' => 'REC-20250201-0041',
            'pic_user_id'       => $user->id,
            'status'            => 'pending',
        ]);

        $this->postJson("/api/v1/reconciliations/{$reconciliation->id}/finalize")
            ->assertStatus(400);
    }

    // -------------------------------------------------------------------------
    // Reopen
    // -------------------------------------------------------------------------

    public function test_can_reopen_finalized_reconciliation(): void
    {
        $user   = $this->makeUserWithPermissions(['returns.reopen_reconciliation']);
        $client = $this->createClient();
        Sanctum::actingAs($user);

        $consignment = Consignment::query()->create([
            'client_id'      => $client->id,
            'consignment_no' => 'CN-20250201-0050',
            'consignment_at' => now(),
            'pic_user_id'    => $user->id,
            'status'         => 'confirmed',
        ]);

        $returnSession = ReturnSession::query()->create([
            'consignment_id'    => $consignment->id,
            'return_session_no' => 'RS-20250201-0050',
            'pic_user_id'       => $user->id,
            'status'            => 'completed',
            'started_at'        => now(),
            'completed_at'      => now(),
        ]);

        $reconciliation = Reconciliation::query()->create([
            'consignment_id'    => $consignment->id,
            'return_session_id' => $returnSession->id,
            'reconciliation_no' => 'REC-20250201-0050',
            'pic_user_id'       => $user->id,
            'status'            => 'finalized',
        ]);

        $response = $this->postJson("/api/v1/reconciliations/{$reconciliation->id}/reopen", [
            'reopen_reason' => 'Correction required',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'reopened');
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_can_show_reconciliation(): void
    {
        $user   = $this->makeUserWithPermissions(['returns.view']);
        $client = $this->createClient();
        Sanctum::actingAs($user);

        $consignment = Consignment::query()->create([
            'client_id'      => $client->id,
            'consignment_no' => 'CN-20250201-0060',
            'consignment_at' => now(),
            'pic_user_id'    => $user->id,
            'status'         => 'confirmed',
        ]);

        $returnSession = ReturnSession::query()->create([
            'consignment_id'    => $consignment->id,
            'return_session_no' => 'RS-20250201-0060',
            'pic_user_id'       => $user->id,
            'status'            => 'completed',
            'started_at'        => now(),
            'completed_at'      => now(),
        ]);

        $reconciliation = Reconciliation::query()->create([
            'consignment_id'    => $consignment->id,
            'return_session_id' => $returnSession->id,
            'reconciliation_no' => 'REC-20250201-0060',
            'pic_user_id'       => $user->id,
            'status'            => 'pending',
        ]);

        $this->getJson("/api/v1/reconciliations/{$reconciliation->id}")
            ->assertOk()
            ->assertJsonPath('data.reconciliation_no', 'REC-20250201-0060');
    }
}
