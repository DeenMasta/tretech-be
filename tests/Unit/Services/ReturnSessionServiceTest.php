<?php

namespace Tests\Unit\Services;

use App\Models\Client;
use App\Models\Consignment;
use App\Models\Lot;
use App\Models\LotMovement;
use App\Models\Product;
use App\Models\Reconciliation;
use App\Models\ReturnSession;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Return\ReturnSessionService;
use PHPUnit\Framework\Attributes\Test;

class ReturnSessionServiceTest extends ServiceTestCase
{
    private ReturnSessionService $service;
    private User $actor;
    private Client $client;
    private Supplier $supplier;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ReturnSessionService::class);
        $this->actor = $this->makeActor('return-session-reopen@test.test');
        $this->client = Client::query()->create(['client_name' => 'Hospital', 'client_type' => 'hospital', 'is_active' => true]);
        $this->supplier = Supplier::query()->create(['supplier_name' => 'Supplier', 'is_active' => true]);
        $this->product = Product::query()->create([
            'ref_num' => 'REF-RETURN-REOPEN', 'product_name' => 'Return Reopen Product',
            'product_type' => 'consumable', 'category' => 'general', 'uom' => 'pcs',
            'requires_expiry' => true, 'requires_lot' => true, 'is_active' => true,
        ]);
    }

    #[Test]
    public function reopen_restores_consigned_quantity_for_damaged_and_missing_movements(): void
    {
        [$session, $reconciliation] = $this->makeCompletedSessionWithFinalizedReconciliation();
        $lot = Lot::query()->create([
            'product_id' => $this->product->id, 'supplier_id' => $this->supplier->id,
            'lot_number' => 'LOT-RETURN-REOPEN', 'manufacturing_date' => '2026-01-01',
            'status' => 'damaged', 'current_location_type' => 'warehouse', 'received_at' => now(),
            'quantity' => 3, 'quantity_available' => 0, 'quantity_consigned' => 0,
        ]);
        $this->recordMovement($reconciliation, $lot, 'damaged', 2, 'damaged', 'warehouse');
        $this->recordMovement($reconciliation, $lot, 'missing', 1, 'missing', 'client');

        $result = $this->service->reopenWithReconciliation($session, 'Correct the recorded outcomes.', $this->actor);

        $this->assertSame('in_progress', $result->status);
        $lot->refresh();
        $this->assertSame('depleted', $lot->status);
        $this->assertSame('client', $lot->current_location_type);
        $this->assertSame($this->client->id, $lot->current_location_id);
        $this->assertSame(0, $lot->quantity_available);
        $this->assertSame(3, $lot->quantity_consigned);
        $this->assertDatabaseMissing('lot_movements', ['reference_id' => $reconciliation->id]);
        $this->assertDatabaseMissing('reconciliations', ['id' => $reconciliation->id]);
    }

    /** @return array{0: ReturnSession, 1: Reconciliation} */
    private function makeCompletedSessionWithFinalizedReconciliation(): array
    {
        $consignment = Consignment::query()->create([
            'client_id' => $this->client->id, 'consignment_no' => 'CN-' . str()->upper(str()->random(6)),
            'consignment_at' => now(), 'pic_user_id' => $this->actor->id, 'status' => 'confirmed',
        ]);
        $session = ReturnSession::query()->create([
            'consignment_id' => $consignment->id, 'return_session_no' => 'RS-' . str()->upper(str()->random(6)),
            'pic_user_id' => $this->actor->id, 'status' => 'completed', 'started_at' => now(),
            'completed_at' => now(), 'completed_by_user_id' => $this->actor->id,
        ]);
        $reconciliation = Reconciliation::query()->create([
            'consignment_id' => $consignment->id, 'return_session_id' => $session->id,
            'reconciliation_no' => 'REC-' . str()->upper(str()->random(6)), 'pic_user_id' => $this->actor->id,
            'status' => 'finalized', 'completed_at' => now(), 'completed_by_user_id' => $this->actor->id,
        ]);

        return [$session, $reconciliation];
    }

    private function recordMovement(Reconciliation $reconciliation, Lot $lot, string $type, int $quantity, string $toStatus, string $toLocation): void
    {
        LotMovement::query()->create([
            'lot_id' => $lot->id, 'movement_type' => $type, 'reference_type' => Reconciliation::class,
            'reference_id' => $reconciliation->id, 'from_status' => 'depleted', 'to_status' => $toStatus,
            'from_location_type' => 'client', 'from_location_id' => $this->client->id,
            'to_location_type' => $toLocation, 'to_location_id' => $toLocation === 'client' ? $this->client->id : null,
            'performed_at' => now(), 'performed_by_user_id' => $this->actor->id, 'quantity' => $quantity,
        ]);
    }
}
