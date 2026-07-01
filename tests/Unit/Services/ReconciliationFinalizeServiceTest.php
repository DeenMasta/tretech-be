<?php

namespace Tests\Unit\Services;

use App\Exceptions\BusinessLogicException;
use App\Models\Client;
use App\Models\Consignment;
use App\Models\ConsignmentItem;
use App\Models\Lot;
use App\Models\Product;
use App\Models\Reconciliation;
use App\Models\ReconciliationItem;
use App\Models\ReturnSession;
use App\Models\ReturnSessionItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Reconciliation\ReconciliationFinalizeService;
use PHPUnit\Framework\Attributes\Test;

class ReconciliationFinalizeServiceTest extends ServiceTestCase
{
    private ReconciliationFinalizeService $service;
    private User $actor;
    private Supplier $supplier;
    private Product $product;
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ReconciliationFinalizeService::class);
        $this->actor   = $this->makeActor('recon@test.test');

        $this->supplier = Supplier::query()->create(['supplier_name' => 'Sup', 'is_active' => true]);
        $this->product  = Product::query()->create([
            'ref_num' => 'REF-RECON', 'product_name' => 'Recon Product',
            'product_type' => 'consumable', 'category' => 'general',
            'uom' => 'pcs', 'requires_expiry' => true, 'requires_lot' => true, 'is_active' => true,
        ]);
        $this->client = Client::query()->create(['client_name' => 'Hosp', 'client_type' => 'hospital', 'is_active' => true]);
    }

    #[Test]
    public function finalize_all_lots_returned_marks_all_as_available(): void
    {
        [$consignment, $returnSession, $lots] = $this->buildScenario(consign: 2, returnBack: 2);
        $reconciliation = $this->makePendingReconciliation($consignment, $returnSession);

        $result = $this->service->finalize($reconciliation, $this->actor);

        $this->assertSame('finalized', $result->status);

        foreach ($lots as $lot) {
            $this->assertSame('holding', $lot->refresh()->status);
        }

        $this->assertSame(0, ReconciliationItem::query()
            ->where('reconciliation_id', $result->id)
            ->where('result', 'used')
            ->count());

        $this->assertSame(2, ReconciliationItem::query()
            ->where('reconciliation_id', $result->id)
            ->where('result', 'returned')
            ->count());
    }

    #[Test]
    public function finalize_no_lots_returned_marks_all_as_used(): void
    {
        [$consignment, $returnSession, $lots] = $this->buildScenario(consign: 2, returnBack: 0);
        $reconciliation = $this->makePendingReconciliation($consignment, $returnSession);

        $result = $this->service->finalize($reconciliation, $this->actor);

        foreach ($lots as $lot) {
            $this->assertSame('used', $lot->refresh()->status);
        }

        $this->assertSame(2, ReconciliationItem::query()
            ->where('reconciliation_id', $result->id)
            ->where('result', 'used')
            ->count());
    }

    #[Test]
    public function finalize_partial_return_creates_correct_used_and_returned_items(): void
    {
        [$consignment, $returnSession, $lots] = $this->buildScenario(consign: 3, returnBack: 1);
        $reconciliation = $this->makePendingReconciliation($consignment, $returnSession);

        $result = $this->service->finalize($reconciliation, $this->actor);

        $usedCount   = ReconciliationItem::query()->where('reconciliation_id', $result->id)->where('result', 'used')->count();
        $returnCount = ReconciliationItem::query()->where('reconciliation_id', $result->id)->where('result', 'returned')->count();

        $this->assertSame(2, $usedCount);
        $this->assertSame(1, $returnCount);
    }

    #[Test]
    public function finalize_creates_lot_movements_for_all_lots(): void
    {
        [$consignment, $returnSession] = $this->buildScenario(consign: 2, returnBack: 1);
        $reconciliation = $this->makePendingReconciliation($consignment, $returnSession);

        $result = $this->service->finalize($reconciliation, $this->actor);

        // 1 returned movement + 1 used movement
        $this->assertDatabaseHas('lot_movements', ['movement_type' => 'returned', 'reference_id' => $result->id]);
        $this->assertDatabaseHas('lot_movements', ['movement_type' => 'used',     'reference_id' => $result->id]);
    }

    #[Test]
    public function finalize_throws_when_reconciliation_already_finalized(): void
    {
        [$consignment, $returnSession] = $this->buildScenario(consign: 1, returnBack: 0);
        $reconciliation = $this->makePendingReconciliation($consignment, $returnSession);
        $reconciliation->fill(['status' => 'finalized'])->save();

        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessageMatches('/pending|reopened/i');

        $this->service->finalize($reconciliation, $this->actor);
    }

    #[Test]
    public function finalize_throws_when_no_consigned_lots_exist(): void
    {
        $consignment  = $this->makeConfirmedConsignment();
        $returnSession = $this->makeCompletedReturnSession($consignment);
        $reconciliation = $this->makePendingReconciliation($consignment, $returnSession);

        // No ConsignmentItems were created
        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessageMatches('/no consigned items/i');

        $this->service->finalize($reconciliation, $this->actor);
    }

    #[Test]
    public function finalize_reopened_reconciliation_succeeds(): void
    {
        [$consignment, $returnSession] = $this->buildScenario(consign: 1, returnBack: 1);
        $reconciliation = $this->makePendingReconciliation($consignment, $returnSession);
        $reconciliation->fill(['status' => 'reopened'])->save();

        $result = $this->service->finalize($reconciliation, $this->actor);

        $this->assertSame('finalized', $result->status);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build a full scenario: consign N lots, return back M of them.
     * Returns [Consignment, ReturnSession, Lot[]].
     *
     * @return array{0: Consignment, 1: ReturnSession, 2: Lot[]}
     */
    private function buildScenario(int $consign, int $returnBack): array
    {
        $consignment   = $this->makeConfirmedConsignment();
        $returnSession = $this->makeCompletedReturnSession($consignment);

        $lots = [];
        for ($i = 0; $i < $consign; $i++) {
            $lot = Lot::query()->create([
                'product_id'            => $this->product->id,
                'supplier_id'           => $this->supplier->id,
                'lot_number'            => 'LOT-R-' . str()->upper(str()->random(6)),
                'manufacturing_date'   => '2026-01-01',
                'status'                => 'supplied',
                'current_location_type' => 'client',
                'current_location_id'   => $this->client->id,
                'received_at'           => now(),
            ]);

            ConsignmentItem::query()->create([
                'consignment_id'    => $consignment->id,
                'lot_id'            => $lot->id,
                'issued_at'         => now(),
                'issued_by_user_id' => $this->actor->id,
            ]);

            $lots[] = $lot;
        }

        for ($i = 0; $i < $returnBack; $i++) {
            ReturnSessionItem::query()->create([
                'return_session_id'   => $returnSession->id,
                'lot_id'              => $lots[$i]->id,
                'returned_at'         => now(),
                'returned_by_user_id' => $this->actor->id,
            ]);
        }

        return [$consignment, $returnSession, $lots];
    }

    private function makeConfirmedConsignment(): Consignment
    {
        return Consignment::query()->create([
            'client_id'       => $this->client->id,
            'consignment_no'  => 'CN-' . str()->upper(str()->random(6)),
            'consignment_at'  => now(),
            'pic_user_id'     => $this->actor->id,
            'status'          => 'confirmed',
            'confirmed_at'    => now(),
            'confirmed_by_user_id' => $this->actor->id,
        ]);
    }

    private function makeCompletedReturnSession(Consignment $consignment): ReturnSession
    {
        return ReturnSession::query()->create([
            'consignment_id'    => $consignment->id,
            'return_session_no' => 'RS-' . str()->upper(str()->random(6)),
            'pic_user_id'       => $this->actor->id,
            'status'            => 'completed',
            'started_at'        => now(),
            'completed_at'      => now(),
            'completed_by_user_id' => $this->actor->id,
        ]);
    }

    private function makePendingReconciliation(Consignment $consignment, ReturnSession $returnSession): Reconciliation
    {
        return Reconciliation::query()->create([
            'consignment_id'    => $consignment->id,
            'return_session_id' => $returnSession->id,
            'reconciliation_no' => 'REC-' . str()->upper(str()->random(6)),
            'pic_user_id'       => $this->actor->id,
            'status'            => 'pending',
        ]);
    }
}
