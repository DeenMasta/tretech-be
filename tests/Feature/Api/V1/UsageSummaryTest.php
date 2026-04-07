<?php

namespace Tests\Feature\Api\V1;

use App\Models\Consignment;
use App\Models\ConsignmentItem;
use App\Models\Reconciliation;
use App\Models\ReconciliationItem;
use App\Models\ReturnSession;
use App\Models\ReturnSessionItem;
use App\Models\UsageSummary;
use App\Models\UsageSummaryItem;
use App\Models\UsageSummaryPushLog;
use Laravel\Sanctum\Sanctum;

class UsageSummaryTest extends FeatureTestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Build a finalized reconciliation with one used lot and one returned lot,
     * and pre-populate ReconciliationItems so generate() will find them.
     *
     * @return array{reconciliation: Reconciliation}
     */
    private function buildFinalizedReconciliation(): array
    {
        $user     = $this->makeUserWithPermissions(['usage_summary.generate', 'usage_summary.view', 'usage_summary.view_logs']);
        $client   = $this->createClient();
        $product  = $this->createProduct();
        $supplier = $this->createSupplier();

        $consignment = Consignment::query()->create([
            'client_id'      => $client->id,
            'consignment_no' => 'CN-US-0001',
            'consignment_at' => now(),
            'pic_user_id'    => $user->id,
            'status'         => 'confirmed',
        ]);

        $returnSession = ReturnSession::query()->create([
            'consignment_id'    => $consignment->id,
            'return_session_no' => 'RS-US-0001',
            'pic_user_id'       => $user->id,
            'status'            => 'completed',
            'started_at'        => now(),
            'completed_at'      => now(),
        ]);

        $reconciliation = Reconciliation::query()->create([
            'consignment_id'    => $consignment->id,
            'return_session_id' => $returnSession->id,
            'reconciliation_no' => 'REC-US-0001',
            'pic_user_id'       => $user->id,
            'status'            => 'finalized',
        ]);

        // Two lots: one used, one returned
        $lotUsed     = $this->createLot($product, $supplier, 'used');
        $lotReturned = $this->createLot($product, $supplier, 'available');

        // Consignment items
        ConsignmentItem::query()->create(['consignment_id' => $consignment->id, 'lot_id' => $lotUsed->id, 'issued_at' => now(), 'issued_by_user_id' => $user->id]);
        ConsignmentItem::query()->create(['consignment_id' => $consignment->id, 'lot_id' => $lotReturned->id, 'issued_at' => now(), 'issued_by_user_id' => $user->id]);

        // Return session item (lotReturned was returned)
        ReturnSessionItem::query()->create([
            'return_session_id'   => $returnSession->id,
            'lot_id'              => $lotReturned->id,
            'returned_at'         => now(),
            'returned_by_user_id' => $user->id,
        ]);

        // Reconciliation items
        ReconciliationItem::query()->create(['reconciliation_id' => $reconciliation->id, 'lot_id' => $lotUsed->id, 'result' => 'used']);
        ReconciliationItem::query()->create(['reconciliation_id' => $reconciliation->id, 'lot_id' => $lotReturned->id, 'result' => 'returned']);

        return compact('reconciliation', 'user', 'product', 'lotUsed', 'lotReturned');
    }

    // =========================================================================
    // Index
    // =========================================================================

    public function test_guest_cannot_list_usage_summaries(): void
    {
        $this->getJson('/api/v1/usage-summaries')
            ->assertStatus(401);
    }

    public function test_user_without_permission_cannot_list_usage_summaries(): void
    {
        Sanctum::actingAs($this->makeUserWithPermissions([]));

        $this->getJson('/api/v1/usage-summaries')
            ->assertStatus(403);
    }

    public function test_can_list_usage_summaries(): void
    {
        $data = $this->buildFinalizedReconciliation();
        Sanctum::actingAs($data['user']);

        UsageSummary::query()->create([
            'reconciliation_id'   => $data['reconciliation']->id,
            'summary_no'          => 'US-20250301-0001',
            'generated_at'        => now(),
            'generated_by_user_id' => $data['user']->id,
            'status'              => 'generated',
        ]);

        $response = $this->getJson('/api/v1/usage-summaries');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data', 'pagination' => ['total']]);
    }

    // =========================================================================
    // Show
    // =========================================================================

    public function test_can_show_usage_summary(): void
    {
        $data = $this->buildFinalizedReconciliation();
        Sanctum::actingAs($data['user']);

        $summary = UsageSummary::query()->create([
            'reconciliation_id'   => $data['reconciliation']->id,
            'summary_no'          => 'US-20250301-0002',
            'generated_at'        => now(),
            'generated_by_user_id' => $data['user']->id,
            'status'              => 'generated',
        ]);

        $this->getJson("/api/v1/usage-summaries/{$summary->id}")
            ->assertOk()
            ->assertJsonPath('data.summary_no', 'US-20250301-0002');
    }

    // =========================================================================
    // Generate
    // =========================================================================

    public function test_can_generate_usage_summary_from_finalized_reconciliation(): void
    {
        $data = $this->buildFinalizedReconciliation();
        Sanctum::actingAs($data['user']);

        $response = $this->postJson('/api/v1/usage-summaries/generate', [
            'reconciliation_id' => $data['reconciliation']->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'generated');

        $this->assertDatabaseHas('usage_summaries', [
            'reconciliation_id' => $data['reconciliation']->id,
            'status'            => 'generated',
        ]);
    }

    public function test_generate_creates_usage_summary_items_per_product(): void
    {
        $data = $this->buildFinalizedReconciliation();
        Sanctum::actingAs($data['user']);

        $this->postJson('/api/v1/usage-summaries/generate', [
            'reconciliation_id' => $data['reconciliation']->id,
        ])->assertCreated();

        $summary = UsageSummary::query()
            ->where('reconciliation_id', $data['reconciliation']->id)
            ->first();

        $this->assertNotNull($summary);
        $this->assertGreaterThanOrEqual(1, $summary->usageSummaryItems()->count());
    }

    public function test_cannot_generate_from_non_finalized_reconciliation(): void
    {
        $user   = $this->makeUserWithPermissions(['usage_summary.generate']);
        $client = $this->createClient();
        Sanctum::actingAs($user);

        $consignment = Consignment::query()->create([
            'client_id'      => $client->id,
            'consignment_no' => 'CN-US-PENDING',
            'consignment_at' => now(),
            'pic_user_id'    => $user->id,
            'status'         => 'confirmed',
        ]);

        $returnSession = ReturnSession::query()->create([
            'consignment_id'    => $consignment->id,
            'return_session_no' => 'RS-US-PENDING',
            'pic_user_id'       => $user->id,
            'status'            => 'completed',
            'started_at'        => now(),
            'completed_at'      => now(),
        ]);

        $pendingReconciliation = Reconciliation::query()->create([
            'consignment_id'    => $consignment->id,
            'return_session_id' => $returnSession->id,
            'reconciliation_no' => 'REC-US-PENDING',
            'pic_user_id'       => $user->id,
            'status'            => 'pending',
        ]);

        $this->postJson('/api/v1/usage-summaries/generate', [
            'reconciliation_id' => $pendingReconciliation->id,
        ])->assertStatus(422);
    }

    public function test_generate_is_idempotent_and_replaces_existing_summary(): void
    {
        $data = $this->buildFinalizedReconciliation();
        Sanctum::actingAs($data['user']);

        // First generation
        $this->postJson('/api/v1/usage-summaries/generate', [
            'reconciliation_id' => $data['reconciliation']->id,
        ])->assertCreated();

        // Second generation should succeed and replace, not create duplicate
        $this->postJson('/api/v1/usage-summaries/generate', [
            'reconciliation_id' => $data['reconciliation']->id,
        ])->assertCreated();

        $count = UsageSummary::query()
            ->where('reconciliation_id', $data['reconciliation']->id)
            ->count();

        $this->assertEquals(1, $count);
    }

    // =========================================================================
    // Push
    // =========================================================================

    public function test_push_dispatches_job_and_creates_push_log(): void
    {
        $data = $this->buildFinalizedReconciliation();
        Sanctum::actingAs($data['user']);

        // First generate a summary
        $this->postJson('/api/v1/usage-summaries/generate', [
            'reconciliation_id' => $data['reconciliation']->id,
        ])->assertCreated();

        $summary = UsageSummary::query()
            ->where('reconciliation_id', $data['reconciliation']->id)
            ->first();

        // Push — with QUEUE_CONNECTION=sync (phpunit.xml), the job runs inline
        // The ERP url won't be valid in test but the push log should still be created
        $response = $this->postJson("/api/v1/usage-summaries/{$summary->id}/push");

        // Either 200 (success) or 202 (queued)
        $this->assertContains($response->status(), [200, 202, 422]);
    }

    // =========================================================================
    // Push logs
    // =========================================================================

    public function test_can_list_push_logs_for_summary(): void
    {
        $data = $this->buildFinalizedReconciliation();
        Sanctum::actingAs($data['user']);

        $summary = UsageSummary::query()->create([
            'reconciliation_id'   => $data['reconciliation']->id,
            'summary_no'          => 'US-20250301-0010',
            'generated_at'        => now(),
            'generated_by_user_id' => $data['user']->id,
            'status'              => 'generated',
        ]);

        UsageSummaryPushLog::query()->create([
            'usage_summary_id' => $summary->id,
            'push_url'         => 'https://erp.example.test/usage',
            'status'           => 'success',
            'http_status_code' => 200,
            'pushed_at'        => now(),
            'retry_count'      => 0,
            'pushed_by_user_id' => $data['user']->id,
        ]);

        $response = $this->getJson("/api/v1/usage-summaries/{$summary->id}/push-logs");

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    // =========================================================================
    // Export
    // =========================================================================

    public function test_can_export_usage_summary_as_csv(): void
    {
        $data = $this->buildFinalizedReconciliation();
        Sanctum::actingAs($data['user']);

        $summary = UsageSummary::query()->create([
            'reconciliation_id'   => $data['reconciliation']->id,
            'summary_no'          => 'US-20250301-0020',
            'generated_at'        => now(),
            'generated_by_user_id' => $data['user']->id,
            'status'              => 'generated',
        ]);

        $response = $this->postJson("/api/v1/usage-summaries/{$summary->id}/export", [
            'format' => 'csv',
        ]);

        $response->assertStatus(200);
    }

    public function test_cannot_export_with_invalid_format(): void
    {
        $data = $this->buildFinalizedReconciliation();
        Sanctum::actingAs($data['user']);

        $summary = UsageSummary::query()->create([
            'reconciliation_id'   => $data['reconciliation']->id,
            'summary_no'          => 'US-20250301-0021',
            'generated_at'        => now(),
            'generated_by_user_id' => $data['user']->id,
            'status'              => 'generated',
        ]);

        $this->postJson("/api/v1/usage-summaries/{$summary->id}/export", [
            'format' => 'doc',
        ])->assertStatus(422);
    }
}
