<?php

namespace Tests\Feature\Console;

use Tests\Feature\Api\V1\FeatureTestCase;

class RepairInventoryStatusesCommandTest extends FeatureTestCase
{
    public function test_it_previews_inconsistent_lots_without_changing_them(): void
    {
        $supplier = $this->createSupplier();
        $product = $this->createProduct();
        $lot = $this->createLot($product, $supplier, status: 'used', lotNumber: 'LOT-PREVIEW');
        $lot->update(['quantity' => 3, 'quantity_available' => 2]);

        $this->artisan('tretech:repair-inventory-statuses')
            ->expectsOutputToContain('Affected lots: 1; hidden available quantity: 2.')
            ->expectsOutputToContain('Preview only: no records were changed.')
            ->assertSuccessful();

        $this->assertDatabaseHas('lots', [
            'id' => $lot->id,
            'status' => 'used',
            'quantity_available' => 2,
        ]);
    }

    public function test_it_repairs_all_inconsistent_lots_but_preserves_holding_lots(): void
    {
        $supplier = $this->createSupplier();
        $product = $this->createProduct();

        $depleted = $this->createLot($product, $supplier, status: 'depleted', lotNumber: 'LOT-DEPLETED');
        $depleted->update(['quantity' => 5, 'quantity_available' => 3]);

        $used = $this->createLot($product, $supplier, status: 'used', lotNumber: 'LOT-USED');
        $used->update(['quantity' => 4, 'quantity_available' => 2]);

        $holding = $this->createLot($product, $supplier, status: 'holding', lotNumber: 'LOT-HOLDING');
        $holding->update(['quantity' => 2, 'quantity_available' => 2]);

        $this->artisan('tretech:repair-inventory-statuses --fix')
            ->expectsOutputToContain('Repair complete: 2 lot(s) changed to available.')
            ->assertSuccessful();

        $this->assertDatabaseHas('lots', ['id' => $depleted->id, 'status' => 'available']);
        $this->assertDatabaseHas('lots', ['id' => $used->id, 'status' => 'available']);
        $this->assertDatabaseHas('lots', ['id' => $holding->id, 'status' => 'holding']);
    }

    public function test_it_can_limit_repairs_to_one_product(): void
    {
        $supplier = $this->createSupplier();
        $targetProduct = $this->createProduct();
        $otherProduct = $this->createProduct();

        $targetLot = $this->createLot($targetProduct, $supplier, status: 'used', lotNumber: 'LOT-TARGET');
        $targetLot->update(['quantity_available' => 1]);

        $otherLot = $this->createLot($otherProduct, $supplier, status: 'depleted', lotNumber: 'LOT-OTHER');
        $otherLot->update(['quantity_available' => 1]);

        $this->artisan("tretech:repair-inventory-statuses --fix --product-id={$targetProduct->id}")
            ->expectsOutputToContain('Repair complete: 1 lot(s) changed to available.')
            ->assertSuccessful();

        $this->assertDatabaseHas('lots', ['id' => $targetLot->id, 'status' => 'available']);
        $this->assertDatabaseHas('lots', ['id' => $otherLot->id, 'status' => 'depleted']);
    }
}
