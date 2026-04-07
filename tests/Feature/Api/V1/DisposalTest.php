<?php

namespace Tests\Feature\Api\V1;

use App\Models\Disposal;
use App\Models\DisposalItem;
use Laravel\Sanctum\Sanctum;

class DisposalTest extends FeatureTestCase
{
    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_guest_cannot_list_disposals(): void
    {
        $this->getJson('/api/v1/disposals')
            ->assertStatus(401);
    }

    public function test_user_without_permission_cannot_list_disposals(): void
    {
        Sanctum::actingAs($this->makeUserWithPermissions([]));

        $this->getJson('/api/v1/disposals')
            ->assertStatus(403);
    }

    public function test_user_with_permission_can_list_disposals(): void
    {
        $user = $this->makeUserWithPermissions(['disposals.view']);
        Sanctum::actingAs($user);

        Disposal::query()->create([
            'disposal_no' => 'DSP-20250301-0001',
            'disposed_at' => now(),
            'pic_user_id' => $user->id,
            'status'      => 'draft',
        ]);

        $response = $this->getJson('/api/v1/disposals');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data', 'pagination' => ['total']]);

        $this->assertGreaterThanOrEqual(1, $response->json('pagination.total'));
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_can_create_disposal(): void
    {
        $user = $this->makeUserWithPermissions(['disposals.create']);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/disposals', [
            'disposed_at' => now()->toDateString(),
            'pic_user_id' => $user->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseHas('disposals', ['status' => 'draft', 'pic_user_id' => $user->id]);
    }

    public function test_create_disposal_requires_disposed_at(): void
    {
        $user = $this->makeUserWithPermissions(['disposals.create']);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/disposals', [
            'pic_user_id' => $user->id,
        ])->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_can_show_disposal(): void
    {
        $user = $this->makeUserWithPermissions(['disposals.view']);
        Sanctum::actingAs($user);

        $disposal = Disposal::query()->create([
            'disposal_no' => 'DSP-20250301-0010',
            'disposed_at' => now(),
            'pic_user_id' => $user->id,
            'status'      => 'draft',
        ]);

        $this->getJson("/api/v1/disposals/{$disposal->id}")
            ->assertOk()
            ->assertJsonPath('data.disposal_no', 'DSP-20250301-0010');
    }

    // -------------------------------------------------------------------------
    // Add item
    // -------------------------------------------------------------------------

    public function test_can_add_available_lot_to_disposal(): void
    {
        $user     = $this->makeUserWithPermissions(['disposals.create']);
        $product  = $this->createProduct();
        $supplier = $this->createSupplier();
        Sanctum::actingAs($user);

        $disposal = Disposal::query()->create([
            'disposal_no' => 'DSP-20250301-0020',
            'disposed_at' => now(),
            'pic_user_id' => $user->id,
            'status'      => 'draft',
        ]);

        $lot = $this->createLot($product, $supplier, 'available');

        $response = $this->postJson("/api/v1/disposals/{$disposal->id}/items", [
            'lot_id'            => $lot->id,
            'disposal_category' => 'expired',
            'reason_text'       => 'Expired stock past expiry date',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('disposal_items', [
            'disposal_id'       => $disposal->id,
            'lot_id'            => $lot->id,
            'disposal_category' => 'expired',
        ]);
    }

    public function test_adding_disposal_item_requires_reason_text(): void
    {
        $user     = $this->makeUserWithPermissions(['disposals.create']);
        $product  = $this->createProduct();
        $supplier = $this->createSupplier();
        Sanctum::actingAs($user);

        $disposal = Disposal::query()->create([
            'disposal_no' => 'DSP-20250301-0021',
            'disposed_at' => now(),
            'pic_user_id' => $user->id,
            'status'      => 'draft',
        ]);

        $lot = $this->createLot($product, $supplier, 'available');

        $this->postJson("/api/v1/disposals/{$disposal->id}/items", [
            'lot_id'            => $lot->id,
            'disposal_category' => 'damaged',
            // missing reason_text
        ])->assertStatus(422);
    }

    public function test_disposal_category_must_be_valid(): void
    {
        $user     = $this->makeUserWithPermissions(['disposals.create']);
        $product  = $this->createProduct();
        $supplier = $this->createSupplier();
        Sanctum::actingAs($user);

        $disposal = Disposal::query()->create([
            'disposal_no' => 'DSP-20250301-0022',
            'disposed_at' => now(),
            'pic_user_id' => $user->id,
            'status'      => 'draft',
        ]);

        $lot = $this->createLot($product, $supplier, 'available');

        $this->postJson("/api/v1/disposals/{$disposal->id}/items", [
            'lot_id'            => $lot->id,
            'disposal_category' => 'unknown_category',
            'reason_text'       => 'Some reason',
        ])->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Delete item
    // -------------------------------------------------------------------------

    public function test_can_delete_item_from_draft_disposal(): void
    {
        $user     = $this->makeUserWithPermissions(['disposals.create']);
        $product  = $this->createProduct();
        $supplier = $this->createSupplier();
        Sanctum::actingAs($user);

        $disposal = Disposal::query()->create([
            'disposal_no' => 'DSP-20250301-0030',
            'disposed_at' => now(),
            'pic_user_id' => $user->id,
            'status'      => 'draft',
        ]);

        $lot  = $this->createLot($product, $supplier, 'available');
        $item = DisposalItem::query()->create([
            'disposal_id'       => $disposal->id,
            'lot_id'            => $lot->id,
            'disposal_category' => 'lost',
            'reason_text'       => 'Item lost during transport',
        ]);

        $this->deleteJson("/api/v1/disposals/{$disposal->id}/items/{$item->id}")
            ->assertOk();

        $this->assertDatabaseMissing('disposal_items', ['id' => $item->id]);
    }

    // -------------------------------------------------------------------------
    // Complete
    // -------------------------------------------------------------------------

    public function test_complete_disposal_marks_lots_disposed(): void
    {
        $user     = $this->makeUserWithPermissions(['disposals.create']);
        $product  = $this->createProduct();
        $supplier = $this->createSupplier();
        Sanctum::actingAs($user);

        $disposal = Disposal::query()->create([
            'disposal_no' => 'DSP-20250301-0040',
            'disposed_at' => now(),
            'pic_user_id' => $user->id,
            'status'      => 'draft',
        ]);

        $lot = $this->createLot($product, $supplier, 'available');
        DisposalItem::query()->create([
            'disposal_id'       => $disposal->id,
            'lot_id'            => $lot->id,
            'disposal_category' => 'expired',
            'reason_text'       => 'Past expiry',
        ]);

        $response = $this->postJson("/api/v1/disposals/{$disposal->id}/complete");

        $response->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('lots', ['id' => $lot->id, 'status' => 'disposed']);
    }

    public function test_cannot_complete_empty_disposal(): void
    {
        $user = $this->makeUserWithPermissions(['disposals.create']);
        Sanctum::actingAs($user);

        $disposal = Disposal::query()->create([
            'disposal_no' => 'DSP-20250301-0041',
            'disposed_at' => now(),
            'pic_user_id' => $user->id,
            'status'      => 'draft',
        ]);

        $this->postJson("/api/v1/disposals/{$disposal->id}/complete")
            ->assertStatus(400);
    }

    public function test_cannot_add_already_disposed_lot_to_disposal(): void
    {
        $user     = $this->makeUserWithPermissions(['disposals.create']);
        $product  = $this->createProduct();
        $supplier = $this->createSupplier();
        Sanctum::actingAs($user);

        $disposal = Disposal::query()->create([
            'disposal_no' => 'DSP-20250301-0050',
            'disposed_at' => now(),
            'pic_user_id' => $user->id,
            'status'      => 'draft',
        ]);

        // Lot is already disposed
        $disposedLot = $this->createLot($product, $supplier, 'disposed');

        $this->postJson("/api/v1/disposals/{$disposal->id}/items", [
            'lot_id'            => $disposedLot->id,
            'disposal_category' => 'other',
            'reason_text'       => 'Re-disposal attempt',
        ])->assertStatus(400);
    }
}
