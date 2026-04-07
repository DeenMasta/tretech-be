<?php

namespace Tests\Unit\Services;

use App\Exceptions\BusinessLogicException;
use App\Models\Client;
use App\Models\Consignment;
use App\Models\ConsignmentItem;
use App\Models\Lot;
use App\Models\Product;
use App\Models\ReturnSession;
use App\Models\ReturnSessionItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Return\ReturnScanService;
use PHPUnit\Framework\Attributes\Test;

class ReturnScanServiceTest extends ServiceTestCase
{
    private ReturnScanService $service;
    private User $actor;
    private Supplier $supplier;
    private Product $product;
    private Client $client;
    private Consignment $consignment;
    private ReturnSession $returnSession;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ReturnScanService::class);
        $this->actor   = $this->makeActor('scan@test.test');

        $this->supplier = Supplier::query()->create(['supplier_name' => 'Sup', 'is_active' => true]);
        $this->product  = Product::query()->create([
            'ref_num' => 'REF-SCAN', 'product_name' => 'Scan Product',
            'product_type' => 'consumable', 'category' => 'general',
            'uom' => 'pcs', 'requires_expiry' => true, 'requires_lot' => true, 'is_active' => true,
        ]);
        $this->client = Client::query()->create(['client_name' => 'Hosp', 'client_type' => 'hospital', 'is_active' => true]);

        $this->consignment = Consignment::query()->create([
            'client_id'            => $this->client->id,
            'consignment_no'       => 'CN-SCAN-001',
            'consignment_at'       => now(),
            'pic_user_id'          => $this->actor->id,
            'status'               => 'confirmed',
            'confirmed_at'         => now(),
            'confirmed_by_user_id' => $this->actor->id,
        ]);

        $this->returnSession = ReturnSession::query()->create([
            'consignment_id'    => $this->consignment->id,
            'return_session_no' => 'RS-SCAN-001',
            'pic_user_id'       => $this->actor->id,
            'status'            => 'in_progress',
            'started_at'        => now(),
        ]);
    }

    #[Test]
    public function scan_valid_supplied_lot_creates_return_session_item(): void
    {
        $lot = $this->makeSuppliedLotInConsignment();

        $item = $this->service->scan($this->returnSession, ['lot_id' => $lot->id], $this->actor);

        $this->assertInstanceOf(ReturnSessionItem::class, $item);
        $this->assertSame($lot->id, $item->lot_id);
        $this->assertSame($this->returnSession->id, $item->return_session_id);

        $this->assertDatabaseHas('return_session_items', [
            'return_session_id' => $this->returnSession->id,
            'lot_id'            => $lot->id,
        ]);
    }

    #[Test]
    public function scan_lot_not_in_consignment_throws(): void
    {
        // Lot exists and is supplied, but NOT linked to this consignment
        $lot = Lot::query()->create([
            'product_id'            => $this->product->id,
            'supplier_id'           => $this->supplier->id,
            'lot_number'            => 'LOT-FOREIGN',
            'supplier_batch_code'   => 'BATCH-TEST',
            'status'                => 'supplied',
            'current_location_type' => 'client',
            'current_location_id'   => $this->client->id,
            'received_at'           => now(),
        ]);

        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessageMatches('/not part of the consignment/i');

        $this->service->scan($this->returnSession, ['lot_id' => $lot->id], $this->actor);
    }

    #[Test]
    public function scan_already_scanned_lot_throws(): void
    {
        $lot = $this->makeSuppliedLotInConsignment();

        // First scan
        $this->service->scan($this->returnSession, ['lot_id' => $lot->id], $this->actor);

        // Second scan of the same lot
        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessageMatches('/already been scanned/i');

        $this->service->scan($this->returnSession, ['lot_id' => $lot->id], $this->actor);
    }

    #[Test]
    public function scan_non_supplied_lot_throws(): void
    {
        $lot = $this->makeSuppliedLotInConsignment();
        $lot->fill(['status' => 'available'])->save();

        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessageMatches('/cannot be returned/i');

        $this->service->scan($this->returnSession, ['lot_id' => $lot->id], $this->actor);
    }

    #[Test]
    public function scan_on_non_in_progress_session_throws(): void
    {
        $lot = $this->makeSuppliedLotInConsignment();
        $this->returnSession->fill(['status' => 'completed'])->save();

        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessageMatches('/in.progress/i');

        $this->service->scan($this->returnSession, ['lot_id' => $lot->id], $this->actor);
    }

    #[Test]
    public function scan_can_resolve_lot_by_lot_number(): void
    {
        $lot = $this->makeSuppliedLotInConsignment('LOT-BYNUM');

        $item = $this->service->scan($this->returnSession, ['lot_number' => 'LOT-BYNUM'], $this->actor);

        $this->assertSame($lot->id, $item->lot_id);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeSuppliedLotInConsignment(?string $lotNumber = null): Lot
    {
        $lot = Lot::query()->create([
            'product_id'            => $this->product->id,
            'supplier_id'           => $this->supplier->id,
            'lot_number'            => $lotNumber ?? 'LOT-' . str()->upper(str()->random(6)),
            'supplier_batch_code'   => 'BATCH-TEST',
            'status'                => 'supplied',
            'current_location_type' => 'client',
            'current_location_id'   => $this->client->id,
            'received_at'           => now(),
        ]);

        ConsignmentItem::query()->create([
            'consignment_id'    => $this->consignment->id,
            'lot_id'            => $lot->id,
            'issued_at'         => now(),
            'issued_by_user_id' => $this->actor->id,
        ]);

        return $lot;
    }
}
