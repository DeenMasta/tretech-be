<?php

namespace Tests\Unit\Services;

use App\Exceptions\BusinessLogicException;
use App\Models\Lot;
use App\Models\LotMovement;
use App\Models\Product;
use App\Models\StockIn;
use App\Models\StockInItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\StockIn\StockInFinalizeService;
use PHPUnit\Framework\Attributes\Test;

class StockInFinalizeServiceTest extends ServiceTestCase
{
    private StockInFinalizeService $service;
    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(StockInFinalizeService::class);
        $this->actor   = $this->makeActor('actor@stockin.test');
    }

    #[Test]
    public function finalize_creates_available_lot_and_movement_for_normal_item(): void
    {
        $session = $this->makeDraftSession();
        $product = $this->makeProduct();
        $this->makeItem($session, $product, 'LOT-001', 'BATCH-A', '2027-12-31', missingLot: false);

        $result = $this->service->finalize($session, $this->actor);

        $this->assertSame('finalized', $result['stock_in']->status);
        $this->assertCount(1, $result['lots']);

        /** @var Lot $lot */
        $lot = $result['lots']->first();
        $this->assertSame('available', $lot->status);
        $this->assertSame('LOT-001', $lot->lot_number);
        $this->assertFalse($lot->is_system_generated_lot);

        $this->assertDatabaseHas('lot_movements', [
            'lot_id'        => $lot->id,
            'movement_type' => 'stock_in',
            'to_status'     => 'available',
        ]);
    }

    #[Test]
    public function finalize_creates_holding_lot_when_missing_lot_flag_is_true(): void
    {
        $session = $this->makeDraftSession();
        $product = $this->makeProduct();
        $this->makeItem($session, $product, null, 'BATCH-B', '2027-12-31', missingLot: true);

        $result = $this->service->finalize($session, $this->actor);

        /** @var Lot $lot */
        $lot = $result['lots']->first();
        $this->assertSame('holding', $lot->status);
        $this->assertTrue($lot->is_system_generated_lot);

        $this->assertDatabaseHas('lot_holdings', ['lot_id' => $lot->id]);
        $this->assertDatabaseHas('lot_movements', [
            'lot_id'        => $lot->id,
            'movement_type' => 'stock_in',
            'to_status'     => 'holding',
        ]);
    }

    #[Test]
    public function finalize_creates_multiple_lots_for_multiple_items(): void
    {
        $session = $this->makeDraftSession();
        $product = $this->makeProduct();
        $this->makeItem($session, $product, 'LOT-A1', 'BATCH-1', '2027-01-01', missingLot: false);
        $this->makeItem($session, $product, 'LOT-A2', 'BATCH-2', '2027-06-01', missingLot: false);

        $result = $this->service->finalize($session, $this->actor);

        $this->assertCount(2, $result['lots']);
        $this->assertSame('finalized', $result['stock_in']->status);
        $this->assertNotNull($result['stock_in']->confirmed_at);
    }

    #[Test]
    public function finalize_empty_session_throws_business_logic_exception(): void
    {
        $session = $this->makeDraftSession();

        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessageMatches('/empty/i');

        $this->service->finalize($session, $this->actor);
    }

    #[Test]
    public function finalize_already_finalized_session_throws(): void
    {
        $session = $this->makeDraftSession();
        $session->fill(['status' => 'finalized'])->save();

        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessageMatches('/draft/i');

        $this->service->finalize($session, $this->actor);
    }

    #[Test]
    public function finalize_sets_confirmed_by_user_id_on_session(): void
    {
        $session = $this->makeDraftSession();
        $product = $this->makeProduct();
        $this->makeItem($session, $product, 'LOT-X1', 'BATCH-X1', null, missingLot: false);

        $result = $this->service->finalize($session, $this->actor);

        $this->assertSame($this->actor->id, $result['stock_in']->confirmed_by_user_id);
    }

    #[Test]
    public function finalize_creates_print_job_for_each_lot(): void
    {
        $session = $this->makeDraftSession();
        $product = $this->makeProduct();
        $this->makeItem($session, $product, 'LOT-PJ1', 'BATCH-PJ', '2027-12-31', missingLot: false);

        $result = $this->service->finalize($session, $this->actor);

        foreach ($result['lots'] as $lot) {
            $this->assertDatabaseHas('qr_print_jobs', ['lot_id' => $lot->id]);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeDraftSession(): StockIn
    {
        $supplier = Supplier::query()->create([
            'supplier_name' => 'Supplier ' . str()->random(4),
            'is_active'     => true,
        ]);

        return StockIn::query()->create([
            'supplier_id'   => $supplier->id,
            'session_no'    => 'SI-' . str()->upper(str()->random(6)),
            'do_number'     => 'DO-' . str()->upper(str()->random(6)),
            'stock_in_at'   => now(),
            'pic_user_id'   => $this->actor->id,
            'status'        => 'draft',
        ]);
    }

    private function makeProduct(): Product
    {
        return Product::query()->create([
            'ref_num'         => 'REF-' . str()->upper(str()->random(6)),
            'product_name'    => 'Product ' . str()->random(4),
            'product_type'    => 'consumable',
            'category'        => 'general',
            'uom'             => 'pcs',
            'requires_expiry' => true,
            'requires_lot'    => true,
            'is_active'       => true,
        ]);
    }

    private function makeItem(
        StockIn $session,
        Product $product,
        ?string $lotNumber,
        ?string $batch,
        ?string $expiry,
        bool $missingLot
    ): StockInItem {
        return StockInItem::query()->create([
            'stock_in_id'        => $session->id,
            'product_id'         => $product->id,
            'scanned_lot_number' => $lotNumber,
            'manufacturing_date'=> $batch,
            'expiry_date'        => $expiry,
            'lot_entry_mode'     => $lotNumber ? 'scan' : 'manual',
            'expiry_entry_mode'  => $expiry ? 'scan' : 'none',
            'missing_lot_flag'   => $missingLot,
        ]);
    }
}
