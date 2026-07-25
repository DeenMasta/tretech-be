<?php

namespace Tests\Unit\Services;

use App\Exceptions\BusinessLogicException;
use App\Models\Lot;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierReturn;
use App\Models\SupplierReturnItem;
use App\Models\User;
use App\Services\SupplierReturn\SupplierReturnCompleteService;
use PHPUnit\Framework\Attributes\Test;

class SupplierReturnCompleteServiceTest extends ServiceTestCase
{
    private SupplierReturnCompleteService $service;
    private User $actor;
    private Product $product;
    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SupplierReturnCompleteService::class);
        $this->actor = $this->makeActor('supplier-return@test.test');
        $this->supplier = Supplier::query()->create(['supplier_name' => 'Supplier', 'is_active' => true]);
        $this->product = Product::query()->create([
            'ref_num' => 'REF-SR', 'product_name' => 'Supplier Return Product',
            'product_type' => 'consumable', 'category' => 'general',
            'uom' => 'pcs', 'requires_expiry' => true, 'requires_lot' => true, 'is_active' => true,
        ]);
    }

    #[Test]
    public function complete_rejects_a_quantity_greater_than_the_locked_lot_balance(): void
    {
        $return = SupplierReturn::query()->create([
            'supplier_id' => $this->supplier->id,
            'supplier_return_no' => 'SR-' . str()->upper(str()->random(6)),
            'returned_at' => now(), 'pic_user_id' => $this->actor->id, 'status' => 'draft',
        ]);
        $lot = Lot::query()->create([
            'product_id' => $this->product->id, 'supplier_id' => $this->supplier->id,
            'lot_number' => 'LOT-SR-' . str()->upper(str()->random(6)),
            'manufacturing_date' => '2026-01-01', 'status' => 'available',
            'current_location_type' => 'warehouse', 'received_at' => now(),
            'quantity' => 1, 'quantity_available' => 1,
        ]);
        SupplierReturnItem::query()->create([
            'supplier_return_id' => $return->id, 'lot_id' => $lot->id,
            'return_reason' => 'Defective', 'quantity' => 2,
        ]);

        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessageMatches('/only 1 available/i');

        try {
            $this->service->complete($return, $this->actor);
        } finally {
            $this->assertSame(1, $lot->refresh()->quantity_available);
            $this->assertSame('draft', $return->refresh()->status);
        }
    }
}
