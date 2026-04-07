<?php

namespace Tests\Feature\Api\V1;

use App\Models\LotHolding;
use Laravel\Sanctum\Sanctum;

class HoldingAreaTest extends FeatureTestCase
{
    // -------------------------------------------------------------------------
    // Index — only returns holding lots
    // -------------------------------------------------------------------------

    public function test_guest_cannot_list_holding_area(): void
    {
        $this->getJson('/api/v1/holding-area')
            ->assertStatus(401);
    }

    public function test_user_without_permission_cannot_list_holding_area(): void
    {
        Sanctum::actingAs($this->makeUserWithPermissions([]));

        $this->getJson('/api/v1/holding-area')
            ->assertStatus(403);
    }

    public function test_index_returns_only_holding_status_lots(): void
    {
        $user     = $this->makeUserWithPermissions(['holding_area.view']);
        $product  = $this->createProduct();
        $supplier = $this->createSupplier();
        Sanctum::actingAs($user);

        $holdingLot   = $this->createLot($product, $supplier, 'holding', 'HOLD-00001');
        $availableLot = $this->createLot($product, $supplier, 'available', 'LOT-AVAIL');

        $response = $this->getJson('/api/v1/holding-area');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $lotNumbers = collect($response->json('data'))->pluck('lot_number')->all();
        $this->assertContains($holdingLot->lot_number, $lotNumbers);
        $this->assertNotContains($availableLot->lot_number, $lotNumbers);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_can_show_holding_lot(): void
    {
        $user     = $this->makeUserWithPermissions(['holding_area.view']);
        $product  = $this->createProduct();
        $supplier = $this->createSupplier();
        Sanctum::actingAs($user);

        $lot = $this->createLot($product, $supplier, 'holding', 'HOLD-SHOW-001');

        $this->getJson("/api/v1/holding-area/{$lot->id}")
            ->assertOk()
            ->assertJsonPath('data.lot_number', 'HOLD-SHOW-001');
    }

    public function test_show_returns_422_for_non_holding_lot(): void
    {
        $user     = $this->makeUserWithPermissions(['holding_area.view']);
        $product  = $this->createProduct();
        $supplier = $this->createSupplier();
        Sanctum::actingAs($user);

        $lot = $this->createLot($product, $supplier, 'available', 'LOT-NOT-HOLDING');

        $this->getJson("/api/v1/holding-area/{$lot->id}")
            ->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Assign lot — releases holding lot to available
    // -------------------------------------------------------------------------

    public function test_assign_lot_changes_status_to_available(): void
    {
        $user     = $this->makeUserWithPermissions(['holding_area.assign_lot']);
        $product  = $this->createProduct();
        $supplier = $this->createSupplier();
        Sanctum::actingAs($user);

        $lot = $this->createLot($product, $supplier, 'holding', 'HOLD-ASSIGN-001');

        LotHolding::query()->create([
            'lot_id'               => $lot->id,
            'holding_reason'       => 'Missing lot during stock-in',
            'assigned_at'          => now(),
            'assigned_by_user_id'  => $user->id,
        ]);

        $response = $this->postJson("/api/v1/holding-area/{$lot->id}/assign-lot", [
            'lot_number'        => 'REAL-LOT-001',
            'resolution_reason' => 'Received correct lot number from supplier',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'available')
            ->assertJsonPath('data.lot_number', 'REAL-LOT-001');

        $this->assertDatabaseHas('lots', ['id' => $lot->id, 'status' => 'available', 'lot_number' => 'REAL-LOT-001']);
        $this->assertDatabaseHas('lot_holdings', ['lot_id' => $lot->id, 'corrected_lot_number' => 'REAL-LOT-001']);
    }

    public function test_assign_lot_closes_lot_holding_record(): void
    {
        $user     = $this->makeUserWithPermissions(['holding_area.assign_lot']);
        $product  = $this->createProduct();
        $supplier = $this->createSupplier();
        Sanctum::actingAs($user);

        $lot = $this->createLot($product, $supplier, 'holding', 'HOLD-CLOSE-001');

        LotHolding::query()->create([
            'lot_id'              => $lot->id,
            'holding_reason'      => 'No lot label',
            'assigned_at'         => now(),
            'assigned_by_user_id' => $user->id,
        ]);

        $this->postJson("/api/v1/holding-area/{$lot->id}/assign-lot", [
            'lot_number'        => 'REAL-LOT-002',
            'resolution_reason' => 'Label received',
        ])->assertOk();

        // The lot holding record should now have a released_at timestamp
        $holding = LotHolding::query()->where('lot_id', $lot->id)->first();
        $this->assertNotNull($holding->released_at);
        $this->assertEquals('REAL-LOT-002', $holding->corrected_lot_number);
    }

    public function test_assign_lot_rejects_duplicate_lot_number(): void
    {
        $user     = $this->makeUserWithPermissions(['holding_area.assign_lot']);
        $product  = $this->createProduct();
        $supplier = $this->createSupplier();
        Sanctum::actingAs($user);

        // Another lot already owns the desired number
        $this->createLot($product, $supplier, 'available', 'EXISTING-LOT');

        $holdingLot = $this->createLot($product, $supplier, 'holding', 'HOLD-DUP-001');

        LotHolding::query()->create([
            'lot_id'              => $holdingLot->id,
            'holding_reason'      => 'Number missing',
            'assigned_at'         => now(),
            'assigned_by_user_id' => $user->id,
        ]);

        $this->postJson("/api/v1/holding-area/{$holdingLot->id}/assign-lot", [
            'lot_number'        => 'EXISTING-LOT',  // conflict
            'resolution_reason' => 'Using existing number',
        ])->assertStatus(422);
    }

    public function test_assign_lot_returns_422_for_non_holding_lot(): void
    {
        $user     = $this->makeUserWithPermissions(['holding_area.assign_lot']);
        $product  = $this->createProduct();
        $supplier = $this->createSupplier();
        Sanctum::actingAs($user);

        $availableLot = $this->createLot($product, $supplier, 'available', 'AVAIL-001');

        $this->postJson("/api/v1/holding-area/{$availableLot->id}/assign-lot", [
            'lot_number'        => 'NEW-LOT-001',
            'resolution_reason' => 'Some reason',
        ])->assertStatus(422);
    }

    public function test_assign_lot_requires_lot_number_and_resolution_reason(): void
    {
        $user     = $this->makeUserWithPermissions(['holding_area.assign_lot']);
        $product  = $this->createProduct();
        $supplier = $this->createSupplier();
        Sanctum::actingAs($user);

        $lot = $this->createLot($product, $supplier, 'holding', 'HOLD-REQ-001');

        $this->postJson("/api/v1/holding-area/{$lot->id}/assign-lot", [])
            ->assertStatus(422);
    }
}
