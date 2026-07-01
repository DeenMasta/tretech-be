<?php

namespace Tests\Unit\Services;

use App\Exceptions\BusinessLogicException;
use App\Models\Client;
use App\Models\Consignment;
use App\Models\ConsignmentItem;
use App\Models\Lot;
use App\Models\LotMovement;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Consignment\ConsignmentConfirmService;
use PHPUnit\Framework\Attributes\Test;

class ConsignmentConfirmServiceTest extends ServiceTestCase
{
    private ConsignmentConfirmService $service;
    private User $actor;
    private Client $client;
    private Product $product;
    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ConsignmentConfirmService::class);
        $this->actor   = $this->makeActor('actor@consignment.test');

        $this->supplier = Supplier::query()->create([
            'supplier_name' => 'Supplier A',
            'is_active'     => true,
        ]);

        $this->product = Product::query()->create([
            'ref_num'         => 'REF-CONS-001',
            'product_name'    => 'Consignment Product',
            'product_type'    => 'consumable',
            'category'        => 'general',
            'uom'             => 'pcs',
            'requires_expiry' => true,
            'requires_lot'    => true,
            'is_active'       => true,
        ]);

        $this->client = Client::query()->create([
            'client_name' => 'Hospital A',
            'client_type' => 'hospital',
            'is_active'   => true,
        ]);
    }

    #[Test]
    public function confirm_changes_lots_to_supplied_and_creates_movements(): void
    {
        $consignment = $this->makeDraftConsignment();
        $lot = $this->makeAvailableLot();
        $this->attachItem($consignment, $lot);

        $result = $this->service->confirm($consignment, $this->actor);

        $this->assertSame('confirmed', $result->status);
        $lot->refresh();
        $this->assertSame('depleted', $lot->status);
        $this->assertSame('client', $lot->current_location_type);

        $this->assertDatabaseHas('lot_movements', [
            'lot_id'        => $lot->id,
            'movement_type' => 'consigned',
            'from_status'   => 'available',
            'to_status'     => 'depleted',
        ]);
    }

    #[Test]
    public function confirm_sets_confirmed_at_and_confirmed_by(): void
    {
        $consignment = $this->makeDraftConsignment();
        $lot = $this->makeAvailableLot();
        $this->attachItem($consignment, $lot);

        $result = $this->service->confirm($consignment, $this->actor);

        $this->assertNotNull($result->confirmed_at);
        $this->assertSame($this->actor->id, $result->confirmed_by_user_id);
    }

    #[Test]
    public function confirm_handles_multiple_lots(): void
    {
        $consignment = $this->makeDraftConsignment();
        $lot1 = $this->makeAvailableLot('LOT-C1');
        $lot2 = $this->makeAvailableLot('LOT-C2');
        $this->attachItem($consignment, $lot1);
        $this->attachItem($consignment, $lot2);

        $this->service->confirm($consignment, $this->actor);

        $this->assertSame('depleted', $lot1->refresh()->status);
        $this->assertSame('depleted', $lot2->refresh()->status);
        $this->assertSame(2, LotMovement::query()->where('movement_type', 'consigned')->count());
    }

    #[Test]
    public function confirm_empty_consignment_throws(): void
    {
        $consignment = $this->makeDraftConsignment();

        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessageMatches('/empty/i');

        $this->service->confirm($consignment, $this->actor);
    }

    #[Test]
    public function confirm_already_confirmed_consignment_throws(): void
    {
        $consignment = $this->makeDraftConsignment();
        $consignment->fill(['status' => 'confirmed'])->save();

        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessageMatches('/draft/i');

        $this->service->confirm($consignment, $this->actor);
    }

    #[Test]
    public function confirm_with_non_available_lot_throws(): void
    {
        $consignment = $this->makeDraftConsignment();
        $lot = $this->makeAvailableLot();
        $lot->fill(['status' => 'holding'])->save();
        $this->attachItem($consignment, $lot);

        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessageMatches('/not available/i');

        $this->service->confirm($consignment, $this->actor);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeDraftConsignment(): Consignment
    {
        return Consignment::query()->create([
            'client_id'       => $this->client->id,
            'consignment_no'  => 'CN-' . str()->upper(str()->random(6)),
            'consignment_at'  => now(),
            'pic_user_id'     => $this->actor->id,
            'status'          => 'draft',
        ]);
    }

    private function makeAvailableLot(?string $lotNumber = null): Lot
    {
        return Lot::query()->create([
            'product_id'            => $this->product->id,
            'supplier_id'           => $this->supplier->id,
            'lot_number'            => $lotNumber ?? 'LOT-' . str()->upper(str()->random(6)),
            'manufacturing_date'   => '2026-01-01',
            'expiry_date'           => '2027-12-31',
            'status'                => 'available',
            'current_location_type' => 'warehouse',
            'received_at'           => now(),
        ]);
    }

    private function attachItem(Consignment $consignment, Lot $lot): ConsignmentItem
    {
        return ConsignmentItem::query()->create([
            'consignment_id'    => $consignment->id,
            'lot_id'            => $lot->id,
            'issued_at'         => now(),
            'issued_by_user_id' => $this->actor->id,
        ]);
    }
}
