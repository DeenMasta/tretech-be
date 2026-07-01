<?php

namespace Tests\Unit\Services;

use App\Exceptions\BusinessLogicException;
use App\Models\Lot;
use App\Models\LotHolding;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\HoldingArea\HoldingAreaService;
use PHPUnit\Framework\Attributes\Test;

class HoldingAreaServiceTest extends ServiceTestCase
{
    private HoldingAreaService $service;
    private User $actor;
    private Supplier $supplier;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(HoldingAreaService::class);
        $this->actor   = $this->makeActor('holding@test.test');

        $this->supplier = Supplier::query()->create(['supplier_name' => 'Sup', 'is_active' => true]);
        $this->product  = Product::query()->create([
            'ref_num' => 'REF-HOLD', 'product_name' => 'Holding Product',
            'product_type' => 'consumable', 'category' => 'general',
            'uom' => 'pcs', 'requires_expiry' => true, 'requires_lot' => true, 'is_active' => true,
        ]);
    }

    #[Test]
    public function assign_lot_changes_status_from_holding_to_available(): void
    {
        $lot = $this->makeHoldingLot();

        $result = $this->service->assignLot($lot, [
            'lot_number'        => 'LOT-REAL-001',
            'resolution_reason' => 'Lot number received from supplier',
        ], $this->actor);

        $this->assertSame('available', $result->status);
        $this->assertSame('LOT-REAL-001', $result->lot_number);
        $this->assertFalse($result->is_system_generated_lot);
    }

    #[Test]
    public function assign_lot_closes_lot_holding_record(): void
    {
        $lot = $this->makeHoldingLot();
        $holding = LotHolding::query()->create([
            'lot_id'               => $lot->id,
            'holding_reason'       => 'Missing lot number',
            'assigned_at'          => now(),
            'assigned_by_user_id'  => $this->actor->id,
        ]);

        $this->service->assignLot($lot, [
            'lot_number'        => 'LOT-REAL-002',
            'resolution_reason' => 'Received from supplier',
        ], $this->actor);

        $holding->refresh();
        $this->assertNotNull($holding->released_at);
        $this->assertSame('LOT-REAL-002', $holding->corrected_lot_number);
        $this->assertSame($this->actor->id, $holding->released_by_user_id);
    }

    #[Test]
    public function assign_lot_creates_holding_released_movement(): void
    {
        $lot = $this->makeHoldingLot();

        $this->service->assignLot($lot, [
            'lot_number'        => 'LOT-REAL-003',
            'resolution_reason' => 'Test reason',
        ], $this->actor);

        $this->assertDatabaseHas('lot_movements', [
            'lot_id'        => $lot->id,
            'movement_type' => 'holding_released',
            'from_status'   => 'holding',
            'to_status'     => 'available',
        ]);
    }

    #[Test]
    public function assign_lot_creates_print_job_for_updated_lot(): void
    {
        $lot = $this->makeHoldingLot();

        $result = $this->service->assignLot($lot, [
            'lot_number'        => 'LOT-REAL-004',
            'resolution_reason' => 'Test reason',
        ], $this->actor);

        $this->assertDatabaseHas('qr_print_jobs', ['lot_id' => $result->id]);
    }

    #[Test]
    public function assign_lot_throws_when_lot_number_already_exists(): void
    {
        $existing = $this->makeHoldingLot();
        $existing->fill(['lot_number' => 'LOT-EXISTING', 'status' => 'available'])->save();

        $toAssign = $this->makeHoldingLot();

        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessageMatches('/already exists/i');

        $this->service->assignLot($toAssign, [
            'lot_number'        => 'LOT-EXISTING',
            'resolution_reason' => 'Should fail',
        ], $this->actor);
    }

    #[Test]
    public function assign_lot_throws_when_lot_is_not_in_holding_status(): void
    {
        $lot = $this->makeHoldingLot();
        $lot->fill(['status' => 'available'])->save();

        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessageMatches('/holding status/i');

        $this->service->assignLot($lot, [
            'lot_number'        => 'LOT-REAL-FAIL',
            'resolution_reason' => 'Should fail',
        ], $this->actor);
    }

    #[Test]
    public function paginate_returns_only_holding_status_lots(): void
    {
        $this->makeHoldingLot();
        $this->makeHoldingLot();

        // Available lot should not appear
        Lot::query()->create([
            'product_id'            => $this->product->id,
            'supplier_id'           => $this->supplier->id,
            'lot_number'            => 'LOT-AVAIL-' . str()->random(4),
            'manufacturing_date'   => '2026-01-01',
            'status'                => 'available',
            'current_location_type' => 'warehouse',
            'received_at'           => now(),
        ]);

        $result = $this->service->paginate();

        $this->assertSame(2, $result->total());
        collect($result->items())->each(fn ($lot) => $this->assertSame('holding', $lot->status));
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeHoldingLot(): Lot
    {
        return Lot::query()->create([
            'product_id'              => $this->product->id,
            'supplier_id'             => $this->supplier->id,
            'lot_number'              => 'HOLD-' . str()->upper(str()->random(8)),
            'manufacturing_date'     => '2026-01-01',
            'is_system_generated_lot' => true,
            'status'                  => 'holding',
            'current_location_type'   => 'warehouse',
            'received_at'             => now(),
        ]);
    }
}
