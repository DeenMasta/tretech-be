<?php

namespace Tests\Unit\Services;

use App\Exceptions\BusinessLogicException;
use App\Models\Disposal;
use App\Models\DisposalItem;
use App\Models\Lot;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Disposal\DisposalCompleteService;
use PHPUnit\Framework\Attributes\Test;

class DisposalCompleteServiceTest extends ServiceTestCase
{
    private DisposalCompleteService $service;
    private User $actor;
    private Product $product;
    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(DisposalCompleteService::class);
        $this->actor   = $this->makeActor('disposal@test.test');

        $this->supplier = Supplier::query()->create(['supplier_name' => 'Sup', 'is_active' => true]);
        $this->product  = Product::query()->create([
            'ref_num' => 'REF-DISP', 'product_name' => 'Disposal Product',
            'product_type' => 'consumable', 'category' => 'general',
            'uom' => 'pcs', 'requires_expiry' => true, 'requires_lot' => true, 'is_active' => true,
        ]);
    }

    #[Test]
    public function complete_marks_all_lots_as_disposed(): void
    {
        $disposal = $this->makeDraftDisposal();
        $lot      = $this->makeAvailableLot();
        $this->attachItem($disposal, $lot);

        $result = $this->service->complete($disposal, $this->actor);

        $this->assertSame('completed', $result->status);
        $this->assertSame('disposed', $lot->refresh()->status);
    }

    #[Test]
    public function complete_creates_lot_movement_per_lot(): void
    {
        $disposal = $this->makeDraftDisposal();
        $lot      = $this->makeAvailableLot();
        $this->attachItem($disposal, $lot);

        $result = $this->service->complete($disposal, $this->actor);

        $this->assertDatabaseHas('lot_movements', [
            'lot_id'        => $lot->id,
            'movement_type' => 'disposed',
            'from_status'   => 'available',
            'to_status'     => 'disposed',
        ]);
    }

    #[Test]
    public function complete_sets_completed_at_and_completed_by(): void
    {
        $disposal = $this->makeDraftDisposal();
        $lot      = $this->makeAvailableLot();
        $this->attachItem($disposal, $lot);

        $result = $this->service->complete($disposal, $this->actor);

        $this->assertNotNull($result->completed_at);
        $this->assertSame($this->actor->id, $result->completed_by_user_id);
    }

    #[Test]
    public function complete_handles_multiple_lots(): void
    {
        $disposal = $this->makeDraftDisposal();
        $lot1 = $this->makeAvailableLot('LOT-D1');
        $lot2 = $this->makeAvailableLot('LOT-D2');
        $this->attachItem($disposal, $lot1);
        $this->attachItem($disposal, $lot2);

        $this->service->complete($disposal, $this->actor);

        $this->assertSame('disposed', $lot1->refresh()->status);
        $this->assertSame('disposed', $lot2->refresh()->status);
    }

    #[Test]
    public function complete_empty_disposal_throws(): void
    {
        $disposal = $this->makeDraftDisposal();

        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessageMatches('/no items/i');

        $this->service->complete($disposal, $this->actor);
    }

    #[Test]
    public function complete_non_draft_disposal_throws(): void
    {
        $disposal = $this->makeDraftDisposal();
        $disposal->fill(['status' => 'completed'])->save();

        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessageMatches('/draft/i');

        $this->service->complete($disposal, $this->actor);
    }

    #[Test]
    public function complete_throws_when_lot_already_disposed(): void
    {
        $disposal = $this->makeDraftDisposal();
        $lot      = $this->makeAvailableLot();
        $lot->fill(['status' => 'disposed'])->save();
        $this->attachItem($disposal, $lot);

        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessageMatches('/disposed/i');

        $this->service->complete($disposal, $this->actor);
    }

    #[Test]
    public function complete_rejects_a_quantity_greater_than_the_locked_lot_balance(): void
    {
        $disposal = $this->makeDraftDisposal();
        $lot = $this->makeAvailableLot(quantity: 1);
        $this->attachItem($disposal, $lot, quantity: 2);

        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessageMatches('/only 1 available/i');

        try {
            $this->service->complete($disposal, $this->actor);
        } finally {
            $this->assertSame(1, $lot->refresh()->quantity_available);
            $this->assertSame('draft', $disposal->refresh()->status);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeDraftDisposal(): Disposal
    {
        return Disposal::query()->create([
            'disposal_no'  => 'DISP-' . str()->upper(str()->random(6)),
            'disposed_at'  => now(),
            'pic_user_id'  => $this->actor->id,
            'status'       => 'draft',
        ]);
    }

    private function makeAvailableLot(?string $lotNumber = null, int $quantity = 1): Lot
    {
        return Lot::query()->create([
            'product_id'            => $this->product->id,
            'supplier_id'           => $this->supplier->id,
            'lot_number'            => $lotNumber ?? 'LOT-' . str()->upper(str()->random(6)),
            'manufacturing_date'   => '2026-01-01',
            'status'                => 'available',
            'current_location_type' => 'warehouse',
            'received_at'           => now(),
            'quantity'              => $quantity,
            'quantity_available'    => $quantity,
        ]);
    }

    private function attachItem(Disposal $disposal, Lot $lot, int $quantity = 1): DisposalItem
    {
        return DisposalItem::query()->create([
            'disposal_id'      => $disposal->id,
            'lot_id'           => $lot->id,
            'disposal_category'=> 'expired',
            'reason_text'      => 'Past expiry date',
            'quantity'         => $quantity,
        ]);
    }
}
