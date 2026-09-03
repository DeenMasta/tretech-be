<?php

namespace Tests\Feature\Api\V1;

use App\Models\SupplierReturn;
use App\Models\SupplierReturnItem;
use Laravel\Sanctum\Sanctum;

class SupplierReturnTest extends FeatureTestCase
{
    public function test_authorized_user_can_reopen_completed_supplier_return_and_restore_lot_inventory(): void
    {
        $user = $this->makeUserWithPermissions([
            'supplier_returns.create',
            'supplier_returns.reopen_completed',
        ]);
        $product = $this->createProduct();
        $supplier = $this->createSupplier();
        Sanctum::actingAs($user);

        $lot = $this->createLot($product, $supplier, 'available');
        $lot->update(['quantity' => 4, 'quantity_available' => 4]);

        $supplierReturn = SupplierReturn::query()->create([
            'supplier_id' => $supplier->id,
            'supplier_return_no' => 'SRT-20250301-REOPEN',
            'returned_at' => now(),
            'pic_user_id' => $user->id,
            'status' => 'draft',
        ]);
        SupplierReturnItem::query()->create([
            'supplier_return_id' => $supplierReturn->id,
            'lot_id' => $lot->id,
            'quantity' => 4,
            'return_reason' => 'Incorrect item received from supplier',
        ]);

        $this->postJson("/api/v1/supplier-returns/{$supplierReturn->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->postJson("/api/v1/supplier-returns/{$supplierReturn->id}/reopen", [
            'reopen_reason' => 'The returned quantity was entered incorrectly.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseHas('lots', [
            'id' => $lot->id,
            'quantity_available' => 4,
            'status' => 'available',
            'current_location_type' => 'warehouse',
        ]);
        $this->assertDatabaseMissing('lot_movements', [
            'reference_type' => SupplierReturn::class,
            'reference_id' => $supplierReturn->id,
            'movement_type' => 'returned_to_supplier',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => SupplierReturn::class,
            'auditable_id' => $supplierReturn->id,
            'action_type' => 'supplier_return.reopened',
            'user_id' => $user->id,
        ]);
    }
}
