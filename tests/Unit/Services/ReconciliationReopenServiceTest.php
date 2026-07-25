<?php

namespace Tests\Unit\Services;

use App\Models\Client;
use App\Models\Consignment;
use App\Models\Lot;
use App\Models\LotMovement;
use App\Models\Product;
use App\Models\Reconciliation;
use App\Models\ReconciliationItem;
use App\Models\ReturnSession;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Reconciliation\ReconciliationReopenService;
use PHPUnit\Framework\Attributes\Test;

class ReconciliationReopenServiceTest extends ServiceTestCase
{
    private ReconciliationReopenService $service;
    private User $actor;
    private Client $client;
    private Supplier $supplier;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ReconciliationReopenService::class);
        $this->actor = $this->makeActor('reconciliation-reopen@test.test');
        $this->client = Client::query()->create(['client_name' => 'Hospital', 'client_type' => 'hospital', 'is_active' => true]);
        $this->supplier = Supplier::query()->create(['supplier_name' => 'Supplier', 'is_active' => true]);
        $this->product = Product::query()->create([
            'ref_num' => 'REF-REOPEN', 'product_name' => 'Reopen Product',
            'product_type' => 'consumable', 'category' => 'general', 'uom' => 'pcs',
            'requires_expiry' => true, 'requires_lot' => true, 'is_active' => true,
        ]);
    }

    #[Test]
    public function reopen_reverses_all_finalization_movements_and_removes_them(): void
    {
        $reconciliation = $this->makeFinalizedReconciliation();
        $partialLot = $this->makeLot('LOT-PARTIAL', 5, 'available', 'warehouse', null, 2, 0);
        $writeOffLot = $this->makeLot('LOT-WRITEOFF', 3, 'damaged', 'warehouse', null, 0, 0);

        $this->recordMovement($reconciliation, $partialLot, 'returned', 2, 'depleted', 'available');
        $this->recordMovement($reconciliation, $partialLot, 'used', 3, 'available', 'used');
        $this->recordMovement($reconciliation, $writeOffLot, 'damaged', 2, 'depleted', 'damaged');
        $this->recordMovement($reconciliation, $writeOffLot, 'missing', 1, 'depleted', 'missing');
        ReconciliationItem::query()->create([
            'reconciliation_id' => $reconciliation->id, 'lot_id' => $partialLot->id,
            'result' => 'partial', 'quantity' => 5, 'returned_quantity' => 2, 'used_quantity' => 3,
        ]);
        ReconciliationItem::query()->create([
            'reconciliation_id' => $reconciliation->id, 'lot_id' => $writeOffLot->id,
            'result' => 'used', 'quantity' => 3, 'damaged_quantity' => 2, 'missing_quantity' => 1,
        ]);

        $result = $this->service->reopen($reconciliation, 'Correct the recorded outcomes.', $this->actor);

        $this->assertSame('reopened', $result->status);
        $this->assertLotWasRestored($partialLot, 5);
        $this->assertLotWasRestored($writeOffLot, 3);
        $this->assertDatabaseMissing('lot_movements', [
            'reference_type' => Reconciliation::class,
            'reference_id' => $reconciliation->id,
        ]);
        $this->assertDatabaseMissing('reconciliation_items', ['reconciliation_id' => $reconciliation->id]);
    }

    private function makeFinalizedReconciliation(): Reconciliation
    {
        $consignment = Consignment::query()->create([
            'client_id' => $this->client->id, 'consignment_no' => 'CN-' . str()->upper(str()->random(6)),
            'consignment_at' => now(), 'pic_user_id' => $this->actor->id, 'status' => 'confirmed',
        ]);
        $returnSession = ReturnSession::query()->create([
            'consignment_id' => $consignment->id, 'return_session_no' => 'RS-' . str()->upper(str()->random(6)),
            'pic_user_id' => $this->actor->id, 'status' => 'completed', 'started_at' => now(),
        ]);

        return Reconciliation::query()->create([
            'consignment_id' => $consignment->id, 'return_session_id' => $returnSession->id,
            'reconciliation_no' => 'REC-' . str()->upper(str()->random(6)), 'pic_user_id' => $this->actor->id,
            'status' => 'finalized', 'completed_at' => now(), 'completed_by_user_id' => $this->actor->id,
        ]);
    }

    private function makeLot(string $lotNumber, int $quantity, string $status, string $locationType, ?int $locationId, int $available, int $consigned): Lot
    {
        return Lot::query()->create([
            'product_id' => $this->product->id, 'supplier_id' => $this->supplier->id,
            'lot_number' => $lotNumber, 'manufacturing_date' => '2026-01-01', 'status' => $status,
            'current_location_type' => $locationType, 'current_location_id' => $locationId, 'received_at' => now(),
            'quantity' => $quantity, 'quantity_available' => $available, 'quantity_consigned' => $consigned,
        ]);
    }

    private function recordMovement(Reconciliation $reconciliation, Lot $lot, string $type, int $quantity, string $fromStatus, string $toStatus): void
    {
        LotMovement::query()->create([
            'lot_id' => $lot->id, 'movement_type' => $type, 'reference_type' => Reconciliation::class,
            'reference_id' => $reconciliation->id, 'from_status' => $fromStatus, 'to_status' => $toStatus,
            'from_location_type' => 'client', 'from_location_id' => $this->client->id,
            'to_location_type' => $toStatus === 'used' || $toStatus === 'missing' ? 'client' : 'warehouse',
            'to_location_id' => $toStatus === 'used' || $toStatus === 'missing' ? $this->client->id : null,
            'performed_at' => now(), 'performed_by_user_id' => $this->actor->id, 'quantity' => $quantity,
        ]);
    }

    private function assertLotWasRestored(Lot $lot, int $quantity): void
    {
        $lot->refresh();

        $this->assertSame('depleted', $lot->status);
        $this->assertSame('client', $lot->current_location_type);
        $this->assertSame($this->client->id, $lot->current_location_id);
        $this->assertSame(0, $lot->quantity_available);
        $this->assertSame($quantity, $lot->quantity_consigned);
    }
}
