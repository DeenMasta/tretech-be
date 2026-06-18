<?php

namespace Tests\Feature\Api\V1;

use App\Models\Consignment;
use App\Models\ConsignmentItem;
use App\Models\Reconciliation;
use App\Models\ReturnSession;
use App\Models\ReturnSessionItem;
use Laravel\Sanctum\Sanctum;

class ReturnAndReconciliationTest extends FeatureTestCase
{
    // =========================================================================
    // Return Sessions
    // =========================================================================

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_guest_cannot_list_return_sessions(): void
    {
        $this->getJson('/api/v1/return-sessions')
            ->assertStatus(401);
    }

    public function test_user_without_permission_cannot_list_return_sessions(): void
    {
        Sanctum::actingAs($this->makeUserWithPermissions([]));

        $this->getJson('/api/v1/return-sessions')
            ->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Store (create return session)
    // -------------------------------------------------------------------------

    public function test_can_create_return_session_for_confirmed_consignment(): void
    {
        $user     = $this->makeUserWithPermissions(['returns.create']);
        $client   = $this->createClient();
        $product  = $this->createProduct();
        $supplier = $this->createSupplier();
        Sanctum::actingAs($user);

        $consignment = Consignment::query()->create([
            'client_id'      => $client->id,
            'consignment_no' => 'CN-20250201-0001',
            'consignment_at' => now(),
            'pic_user_id'    => $user->id,
            'status'         => 'confirmed',
        ]);

        $response = $this->postJson('/api/v1/return-sessions', [
            'consignment_id' => $consignment->id,
            'pic_user_id'    => $user->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'in_progress');

        $this->assertDatabaseHas('return_sessions', ['consignment_id' => $consignment->id]);
    }

    public function test_cannot_create_return_session_for_draft_consignment(): void
    {
        $user   = $this->makeUserWithPermissions(['returns.create']);
        $client = $this->createClient();
        Sanctum::actingAs($user);

        $consignment = Consignment::query()->create([
            'client_id'      => $client->id,
            'consignment_no' => 'CN-20250201-0002',
            'consignment_at' => now(),
            'pic_user_id'    => $user->id,
            'status'         => 'draft',
        ]);

        $this->postJson('/api/v1/return-sessions', [
            'consignment_id' => $consignment->id,
            'pic_user_id'    => $user->id,
        ])->assertStatus(400);
    }

    public function test_cannot_create_duplicate_return_session_for_same_consignment(): void
    {
        $user   = $this->makeUserWithPermissions(['returns.create']);
        $client = $this->createClient();
        Sanctum::actingAs($user);

        $consignment = Consignment::query()->create([
            'client_id'      => $client->id,
            'consignment_no' => 'CN-20250201-0003',
            'consignment_at' => now(),
            'pic_user_id'    => $user->id,
            'status'         => 'confirmed',
        ]);

        ReturnSession::query()->create([
            'consignment_id'    => $consignment->id,
            'return_session_no' => 'RS-20250201-0001',
            'pic_user_id'       => $user->id,
            'status'            => 'in_progress',
            'started_at'        => now(),
        ]);

        $this->postJson('/api/v1/return-sessions', [
            'consignment_id' => $consignment->id,
            'pic_user_id'    => $user->id,
        ])->assertStatus(400);
    }

    // -------------------------------------------------------------------------
    // Scan item
    // -------------------------------------------------------------------------

    public function test_can_scan_lot_into_return_session(): void
    {
        $user     = $this->makeUserWithPermissions(['returns.create']);
        $client   = $this->createClient();
        $product  = $this->createProduct();
        $supplier = $this->createSupplier();
        Sanctum::actingAs($user);

        $consignment = Consignment::query()->create([
            'client_id'      => $client->id,
            'consignment_no' => 'CN-20250201-0010',
            'consignment_at' => now(),
            'pic_user_id'    => $user->id,
            'status'         => 'confirmed',
        ]);

        $returnSession = ReturnSession::query()->create([
            'consignment_id'    => $consignment->id,
            'return_session_no' => 'RS-20250201-0010',
            'pic_user_id'       => $user->id,
            'status'            => 'in_progress',
            'started_at'        => now(),
        ]);

        $lot = $this->createLot($product, $supplier, 'supplied');

        // Attach lot to consignment
        ConsignmentItem::query()->create([
            'consignment_id'    => $consignment->id,
            'lot_id'            => $lot->id,
            'issued_at'         => now(),
            'issued_by_user_id' => $user->id,
        ]);

        $response = $this->postJson("/api/v1/return-sessions/{$returnSession->id}/scan", [
            'lot_id' => $lot->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('return_session_items', [
            'return_session_id' => $returnSession->id,
            'lot_id'            => $lot->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // Complete return session
    // -------------------------------------------------------------------------

    public function test_can_complete_return_session_with_items(): void
    {
        $user     = $this->makeUserWithPermissions(['returns.finalize']);
        $client   = $this->createClient();
        $product  = $this->createProduct();
        $supplier = $this->createSupplier();
        Sanctum::actingAs($user);

        $consignment = Consignment::query()->create([
            'client_id'      => $client->id,
            'consignment_no' => 'CN-20250201-0020',
            'consignment_at' => now(),
            'pic_user_id'    => $user->id,
            'status'         => 'confirmed',
        ]);

        $returnSession = ReturnSession::query()->create([
            'consignment_id'    => $consignment->id,
            'return_session_no' => 'RS-20250201-0020',
            'pic_user_id'       => $user->id,
            'status'            => 'in_progress',
            'started_at'        => now(),
        ]);

        $lot = $this->createLot($product, $supplier, 'supplied');
        ConsignmentItem::query()->create([
            'consignment_id'    => $consignment->id,
            'lot_id'            => $lot->id,
            'issued_at'         => now(),
            'issued_by_user_id' => $user->id,
        ]);
        ReturnSessionItem::query()->create([
            'return_session_id'    => $returnSession->id,
            'lot_id'               => $lot->id,
            'returned_at'          => now(),
            'returned_by_user_id'  => $user->id,
        ]);

        $response = $this->postJson("/api/v1/return-sessions/{$returnSession->id}/complete");

        $response->assertOk()
            ->assertJsonPath('data.status', 'completed');
    }

    // =========================================================================
    // Reconciliations
    // =========================================================================

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_can_create_reconciliation_from_return_session(): void
    {
        $user   = $this->makeUserWithPermissions(['returns.finalize']);
        $client = $this->createClient();
        Sanctum::actingAs($user);

        $consignment = Consignment::query()->create([
            'client_id'      => $client->id,
            'consignment_no' => 'CN-20250201-0030',
            'consignment_at' => now(),
            'pic_user_id'    => $user->id,
            'status'         => 'confirmed',
        ]);

        $returnSession = ReturnSession::query()->create([
            'consignment_id'    => $consignment->id,
            'return_session_no' => 'RS-20250201-0030',
            'pic_user_id'       => $user->id,
            'status'            => 'completed',
            'started_at'        => now(),
            'completed_at'      => now(),
        ]);

        $response = $this->postJson('/api/v1/reconciliations', [
            'return_session_id' => $returnSession->id,
            'pic_user_id'       => $user->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('reconciliations', ['return_session_id' => $returnSession->id]);
    }

    // -------------------------------------------------------------------------
    // Finalize
    // -------------------------------------------------------------------------

    public function test_reconciliation_finalize_marks_lots_used(): void
    {
        $user     = $this->makeUserWithPermissions(['returns.finalize']);
        $client   = $this->createClient();
        $product  = $this->createProduct();
        $supplier = $this->createSupplier();
        Sanctum::actingAs($user);

        $consignment = Consignment::query()->create([
            'client_id'      => $client->id,
            'consignment_no' => 'CN-20250201-0040',
            'consignment_at' => now(),
            'pic_user_id'    => $user->id,
            'status'         => 'confirmed',
        ]);

        $returnSession = ReturnSession::query()->create([
            'consignment_id'    => $consignment->id,
            'return_session_no' => 'RS-20250201-0040',
            'pic_user_id'       => $user->id,
            'status'            => 'completed',
            'started_at'        => now(),
            'completed_at'      => now(),
        ]);

        $reconciliation = Reconciliation::query()->create([
            'consignment_id'    => $consignment->id,
            'return_session_id' => $returnSession->id,
            'reconciliation_no' => 'REC-20250201-0040',
            'pic_user_id'       => $user->id,
            'status'            => 'pending',
        ]);

        // Consigned lot (not returned → will be marked 'used')
        $lotUsed = $this->createLot($product, $supplier, 'supplied');
        ConsignmentItem::query()->create([
            'consignment_id'    => $consignment->id,
            'lot_id'            => $lotUsed->id,
            'issued_at'         => now(),
            'issued_by_user_id' => $user->id,
        ]);

        // Returned lot (returned → will be marked 'available')
        $lotReturned = $this->createLot($product, $supplier, 'supplied');
        ConsignmentItem::query()->create([
            'consignment_id'    => $consignment->id,
            'lot_id'            => $lotReturned->id,
            'issued_at'         => now(),
            'issued_by_user_id' => $user->id,
        ]);
        ReturnSessionItem::query()->create([
            'return_session_id'   => $returnSession->id,
            'lot_id'              => $lotReturned->id,
            'returned_at'         => now(),
            'returned_by_user_id' => $user->id,
        ]);

        $response = $this->postJson("/api/v1/reconciliations/{$reconciliation->id}/finalize");

        $response->assertOk()
            ->assertJsonPath('data.status', 'finalized');

        $this->assertDatabaseHas('lots', ['id' => $lotUsed->id, 'status' => 'used']);
        $this->assertDatabaseHas('lots', ['id' => $lotReturned->id, 'status' => 'holding']);
    }

    public function test_cannot_finalize_reconciliation_with_no_consigned_lots(): void
    {
        $user   = $this->makeUserWithPermissions(['returns.finalize']);
        $client = $this->createClient();
        Sanctum::actingAs($user);

        $consignment = Consignment::query()->create([
            'client_id'      => $client->id,
            'consignment_no' => 'CN-20250201-0041',
            'consignment_at' => now(),
            'pic_user_id'    => $user->id,
            'status'         => 'confirmed',
        ]);

        $returnSession = ReturnSession::query()->create([
            'consignment_id'    => $consignment->id,
            'return_session_no' => 'RS-20250201-0041',
            'pic_user_id'       => $user->id,
            'status'            => 'completed',
            'started_at'        => now(),
            'completed_at'      => now(),
        ]);

        $reconciliation = Reconciliation::query()->create([
            'consignment_id'    => $consignment->id,
            'return_session_id' => $returnSession->id,
            'reconciliation_no' => 'REC-20250201-0041',
            'pic_user_id'       => $user->id,
            'status'            => 'pending',
        ]);

        $this->postJson("/api/v1/reconciliations/{$reconciliation->id}/finalize")
            ->assertStatus(400);
    }

    // -------------------------------------------------------------------------
    // Reopen
    // -------------------------------------------------------------------------

    public function test_can_reopen_finalized_reconciliation(): void
    {
        $user   = $this->makeUserWithPermissions(['returns.reopen_reconciliation']);
        $client = $this->createClient();
        Sanctum::actingAs($user);

        $consignment = Consignment::query()->create([
            'client_id'      => $client->id,
            'consignment_no' => 'CN-20250201-0050',
            'consignment_at' => now(),
            'pic_user_id'    => $user->id,
            'status'         => 'confirmed',
        ]);

        $returnSession = ReturnSession::query()->create([
            'consignment_id'    => $consignment->id,
            'return_session_no' => 'RS-20250201-0050',
            'pic_user_id'       => $user->id,
            'status'            => 'completed',
            'started_at'        => now(),
            'completed_at'      => now(),
        ]);

        $reconciliation = Reconciliation::query()->create([
            'consignment_id'    => $consignment->id,
            'return_session_id' => $returnSession->id,
            'reconciliation_no' => 'REC-20250201-0050',
            'pic_user_id'       => $user->id,
            'status'            => 'finalized',
        ]);

        $response = $this->postJson("/api/v1/reconciliations/{$reconciliation->id}/reopen", [
            'reopen_reason' => 'Correction required',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'reopened');
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_can_show_reconciliation(): void
    {
        $user   = $this->makeUserWithPermissions(['returns.view']);
        $client = $this->createClient();
        Sanctum::actingAs($user);

        $consignment = Consignment::query()->create([
            'client_id'      => $client->id,
            'consignment_no' => 'CN-20250201-0060',
            'consignment_at' => now(),
            'pic_user_id'    => $user->id,
            'status'         => 'confirmed',
        ]);

        $returnSession = ReturnSession::query()->create([
            'consignment_id'    => $consignment->id,
            'return_session_no' => 'RS-20250201-0060',
            'pic_user_id'       => $user->id,
            'status'            => 'completed',
            'started_at'        => now(),
            'completed_at'      => now(),
        ]);

        $reconciliation = Reconciliation::query()->create([
            'consignment_id'    => $consignment->id,
            'return_session_id' => $returnSession->id,
            'reconciliation_no' => 'REC-20250201-0060',
            'pic_user_id'       => $user->id,
            'status'            => 'pending',
        ]);

        $this->getJson("/api/v1/reconciliations/{$reconciliation->id}")
            ->assertOk()
            ->assertJsonPath('data.reconciliation_no', 'REC-20250201-0060');
    }
}
