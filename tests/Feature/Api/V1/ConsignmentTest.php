<?php

namespace Tests\Feature\Api\V1;

use App\Models\Consignment;
use App\Models\ConsignmentItem;
use App\Models\Lot;
use Laravel\Sanctum\Sanctum;

class ConsignmentTest extends FeatureTestCase
{
    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_guest_cannot_list_consignments(): void
    {
        $this->getJson('/api/v1/consignments')
            ->assertStatus(401);
    }

    public function test_user_without_permission_cannot_list_consignments(): void
    {
        Sanctum::actingAs($this->makeUserWithPermissions([]));

        $this->getJson('/api/v1/consignments')
            ->assertStatus(403);
    }

    public function test_user_with_permission_can_list_consignments(): void
    {
        $user   = $this->makeUserWithPermissions(['consignments.view']);
        $client = $this->createClient();
        Sanctum::actingAs($user);

        Consignment::query()->create([
            'client_id'       => $client->id,
            'consignment_no'  => 'CN-20250101-0001',
            'consignment_at'  => now(),
            'pic_user_id'     => $user->id,
            'status'          => 'draft',
        ]);

        $response = $this->getJson('/api/v1/consignments');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data', 'pagination' => ['total']]);

        $this->assertGreaterThanOrEqual(1, $response->json('pagination.total'));
    }

    public function test_consignment_list_includes_lot_numbers(): void
    {
        $user     = $this->makeUserWithPermissions(['consignments.view']);
        $client   = $this->createClient();
        $product  = $this->createProduct();
        $supplier = $this->createSupplier();
        Sanctum::actingAs($user);

        $consignment = Consignment::query()->create([
            'client_id'      => $client->id,
            'consignment_no' => 'CN-20250101-0003',
            'consignment_at' => now(),
            'pic_user_id'    => $user->id,
            'status'         => 'draft',
        ]);
        $lot = $this->createLot($product, $supplier, 'available', 'LOT-LIST-001');

        ConsignmentItem::query()->create([
            'consignment_id'    => $consignment->id,
            'lot_id'            => $lot->id,
            'issued_at'         => now(),
            'issued_by_user_id' => $user->id,
        ]);

        $this->getJson('/api/v1/consignments')
            ->assertOk()
            ->assertJsonPath('data.0.lot_numbers.0', 'LOT-LIST-001');
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_can_create_consignment(): void
    {
        $user   = $this->makeUserWithPermissions(['consignments.create']);
        $client = $this->createClient();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/consignments', [
            'client_id'      => $client->id,
            'consignment_at' => now()->toDateString(),
            'pic_user_id'    => $user->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseHas('consignments', ['client_id' => $client->id, 'status' => 'draft']);
    }

    public function test_create_consignment_requires_client_id(): void
    {
        $user = $this->makeUserWithPermissions(['consignments.create']);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/consignments', [
            'consignment_at' => now()->toDateString(),
            'pic_user_id'    => $user->id,
        ])->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_can_show_a_consignment(): void
    {
        $user   = $this->makeUserWithPermissions(['consignments.view']);
        $client = $this->createClient();
        Sanctum::actingAs($user);

        $consignment = Consignment::query()->create([
            'client_id'      => $client->id,
            'consignment_no' => 'CN-20250101-0002',
            'consignment_at' => now(),
            'pic_user_id'    => $user->id,
            'status'         => 'draft',
        ]);

        $this->getJson("/api/v1/consignments/{$consignment->id}")
            ->assertOk()
            ->assertJsonPath('data.consignment_no', 'CN-20250101-0002');
    }

    // -------------------------------------------------------------------------
    // Add item
    // -------------------------------------------------------------------------

    public function test_can_add_available_lot_to_consignment(): void
    {
        $user     = $this->makeUserWithPermissions(['consignments.edit_draft']);
        $client   = $this->createClient();
        $product  = $this->createProduct();
        $supplier = $this->createSupplier();
        Sanctum::actingAs($user);

        $consignment = Consignment::query()->create([
            'client_id'      => $client->id,
            'consignment_no' => 'CN-20250101-0010',
            'consignment_at' => now(),
            'pic_user_id'    => $user->id,
            'status'         => 'draft',
        ]);

        $lot = $this->createLot($product, $supplier, 'available');

        $response = $this->postJson("/api/v1/consignments/{$consignment->id}/items", [
            'lot_id' => $lot->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('consignment_items', [
            'consignment_id' => $consignment->id,
            'lot_id'         => $lot->id,
        ]);
    }

    public function test_cannot_add_holding_lot_to_consignment(): void
    {
        $user     = $this->makeUserWithPermissions(['consignments.edit_draft']);
        $client   = $this->createClient();
        $product  = $this->createProduct();
        $supplier = $this->createSupplier();
        Sanctum::actingAs($user);

        $consignment = Consignment::query()->create([
            'client_id'      => $client->id,
            'consignment_no' => 'CN-20250101-0011',
            'consignment_at' => now(),
            'pic_user_id'    => $user->id,
            'status'         => 'draft',
        ]);

        $holdingLot = $this->createLot($product, $supplier, 'holding');

        $this->postJson("/api/v1/consignments/{$consignment->id}/items", [
            'lot_id' => $holdingLot->id,
        ])->assertStatus(400);
    }

    // -------------------------------------------------------------------------
    // Confirm
    // -------------------------------------------------------------------------

    public function test_confirm_consignment_changes_lots_to_supplied(): void
    {
        $user     = $this->makeUserWithPermissions(['consignments.create', 'consignments.confirm']);
        $client   = $this->createClient();
        $product  = $this->createProduct();
        $supplier = $this->createSupplier();
        Sanctum::actingAs($user);

        $consignment = Consignment::query()->create([
            'client_id'      => $client->id,
            'consignment_no' => 'CN-20250101-0020',
            'consignment_at' => now(),
            'pic_user_id'    => $user->id,
            'status'         => 'draft',
        ]);

        $lot = $this->createLot($product, $supplier, 'available');

        ConsignmentItem::query()->create([
            'consignment_id'    => $consignment->id,
            'lot_id'            => $lot->id,
            'issued_at'         => now(),
            'issued_by_user_id' => $user->id,
        ]);

        $response = $this->postJson("/api/v1/consignments/{$consignment->id}/confirm");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertDatabaseHas('lots', ['id' => $lot->id, 'status' => 'depleted']);
    }

    public function test_cannot_confirm_empty_consignment(): void
    {
        $user   = $this->makeUserWithPermissions(['consignments.create', 'consignments.confirm']);
        $client = $this->createClient();
        Sanctum::actingAs($user);

        $consignment = Consignment::query()->create([
            'client_id'      => $client->id,
            'consignment_no' => 'CN-20250101-0021',
            'consignment_at' => now(),
            'pic_user_id'    => $user->id,
            'status'         => 'draft',
        ]);

        $this->postJson("/api/v1/consignments/{$consignment->id}/confirm")
            ->assertStatus(400);
    }

    public function test_cannot_confirm_already_confirmed_consignment(): void
    {
        $user   = $this->makeUserWithPermissions(['consignments.confirm']);
        $client = $this->createClient();
        Sanctum::actingAs($user);

        $consignment = Consignment::query()->create([
            'client_id'      => $client->id,
            'consignment_no' => 'CN-20250101-0022',
            'consignment_at' => now(),
            'pic_user_id'    => $user->id,
            'status'         => 'confirmed',
        ]);

        $this->postJson("/api/v1/consignments/{$consignment->id}/confirm")
            ->assertStatus(400);
    }

    // -------------------------------------------------------------------------
    // Delete item
    // -------------------------------------------------------------------------

    public function test_can_remove_item_from_draft_consignment(): void
    {
        $user     = $this->makeUserWithPermissions(['consignments.edit_draft']);
        $client   = $this->createClient();
        $product  = $this->createProduct();
        $supplier = $this->createSupplier();
        Sanctum::actingAs($user);

        $consignment = Consignment::query()->create([
            'client_id'      => $client->id,
            'consignment_no' => 'CN-20250101-0030',
            'consignment_at' => now(),
            'pic_user_id'    => $user->id,
            'status'         => 'draft',
        ]);

        $lot  = $this->createLot($product, $supplier, 'available');
        $item = ConsignmentItem::query()->create([
            'consignment_id'    => $consignment->id,
            'lot_id'            => $lot->id,
            'issued_at'         => now(),
            'issued_by_user_id' => $user->id,
        ]);

        $this->deleteJson("/api/v1/consignments/{$consignment->id}/items/{$item->id}")
            ->assertOk();

        $this->assertDatabaseMissing('consignment_items', ['id' => $item->id]);
    }

    // -------------------------------------------------------------------------
    // Review (GET)
    // -------------------------------------------------------------------------

    public function test_can_get_consignment_review(): void
    {
        $user   = $this->makeUserWithPermissions(['consignments.view']);
        $client = $this->createClient();
        Sanctum::actingAs($user);

        $consignment = Consignment::query()->create([
            'client_id'      => $client->id,
            'consignment_no' => 'CN-20250101-0040',
            'consignment_at' => now(),
            'pic_user_id'    => $user->id,
            'status'         => 'draft',
        ]);

        $this->getJson("/api/v1/consignments/{$consignment->id}/review")
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}
