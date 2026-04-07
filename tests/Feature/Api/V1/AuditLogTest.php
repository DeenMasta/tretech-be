<?php

namespace Tests\Feature\Api\V1;

use App\Models\AuditLog;
use App\Models\LotHolding;
use Laravel\Sanctum\Sanctum;

class AuditLogTest extends FeatureTestCase
{
    // =========================================================================
    // Access control — admin-only
    // =========================================================================

    public function test_guest_cannot_list_audit_logs(): void
    {
        $this->getJson('/api/v1/audit-logs')
            ->assertStatus(401);
    }

    public function test_guest_cannot_show_audit_log(): void
    {
        $this->getJson('/api/v1/audit-logs/1')
            ->assertStatus(401);
    }

    public function test_user_without_admin_permission_cannot_list_audit_logs(): void
    {
        Sanctum::actingAs($this->makeUserWithPermissions([]));

        $this->getJson('/api/v1/audit-logs')
            ->assertStatus(403);
    }

    public function test_user_with_reports_view_cannot_access_audit_logs(): void
    {
        // reports.view is NOT the same as system.manage_roles
        Sanctum::actingAs($this->makeUserWithPermissions(['reports.view']));

        $this->getJson('/api/v1/audit-logs')
            ->assertStatus(403);
    }

    public function test_admin_can_list_audit_logs(): void
    {
        $admin = $this->makeUserWithPermissions(['system.manage_roles']);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/audit-logs');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data', 'pagination' => ['total']]);
    }

    public function test_admin_can_show_audit_log(): void
    {
        $admin = $this->makeUserWithPermissions(['system.manage_roles']);
        Sanctum::actingAs($admin);

        $log = AuditLog::query()->create([
            'user_id'          => $admin->id,
            'auditable_type'   => 'App\\Models\\Lot',
            'auditable_id'     => 1,
            'action_type'      => 'lot.created',
            'description'      => 'Lot created',
            'server_timestamp' => now(),
        ]);

        $response = $this->getJson("/api/v1/audit-logs/{$log->id}");

        $response->assertOk()
            ->assertJsonPath('data.action_type', 'lot.created');
    }

    public function test_show_returns_404_for_nonexistent_log(): void
    {
        $admin = $this->makeUserWithPermissions(['system.manage_roles']);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/audit-logs/99999999')
            ->assertStatus(404);
    }

    // =========================================================================
    // Event capture — holding lot assignment is audit-logged
    // =========================================================================

    public function test_holding_lot_assignment_creates_audit_log(): void
    {
        $admin    = $this->makeUserWithPermissions(['holding_area.assign_lot', 'system.manage_roles']);
        $product  = $this->createProduct();
        $supplier = $this->createSupplier();
        Sanctum::actingAs($admin);

        $lot = $this->createLot($product, $supplier, 'holding', 'HOLD-AUDIT-001');

        LotHolding::query()->create([
            'lot_id'              => $lot->id,
            'holding_reason'      => 'Missing lot label on arrival',
            'assigned_at'         => now(),
            'assigned_by_user_id' => $admin->id,
        ]);

        $before = AuditLog::query()->count();

        $this->postJson("/api/v1/holding-area/{$lot->id}/assign-lot", [
            'lot_number'        => 'ASSIGNED-LOT-001',
            'resolution_reason' => 'Received correct lot documentation',
        ])->assertOk();

        $after = AuditLog::query()->count();

        $this->assertGreaterThan($before, $after, 'An audit log entry should have been created.');
    }

    // =========================================================================
    // Event capture — disposal completion is audit-logged
    // =========================================================================

    public function test_disposal_completion_creates_audit_log(): void
    {
        $user     = $this->makeUserWithPermissions(['disposals.create', 'system.manage_roles']);
        $product  = $this->createProduct();
        $supplier = $this->createSupplier();
        Sanctum::actingAs($user);

        $disposal = \App\Models\Disposal::query()->create([
            'disposal_no' => 'DSP-AUDIT-001',
            'disposed_at' => now(),
            'pic_user_id' => $user->id,
            'status'      => 'draft',
        ]);

        $lot = $this->createLot($product, $supplier, 'available');
        \App\Models\DisposalItem::query()->create([
            'disposal_id'       => $disposal->id,
            'lot_id'            => $lot->id,
            'disposal_category' => 'expired',
            'reason_text'       => 'Past expiry date',
        ]);

        $before = AuditLog::query()->count();

        $this->postJson("/api/v1/disposals/{$disposal->id}/complete")
            ->assertOk();

        $after = AuditLog::query()->count();

        $this->assertGreaterThan($before, $after, 'Disposal completion should produce an audit log entry.');
    }

    // =========================================================================
    // Filtering
    // =========================================================================

    public function test_audit_logs_can_be_filtered_by_action_type(): void
    {
        $admin = $this->makeUserWithPermissions(['system.manage_roles']);
        Sanctum::actingAs($admin);

        // Seed two entries with different action types
        AuditLog::query()->create([
            'user_id'          => $admin->id,
            'auditable_type'   => 'App\\Models\\Lot',
            'auditable_id'     => 10,
            'action_type'      => 'lot.created',
            'description'      => 'Lot created during stock-in',
            'server_timestamp' => now(),
        ]);

        AuditLog::query()->create([
            'user_id'          => $admin->id,
            'auditable_type'   => 'App\\Models\\Disposal',
            'auditable_id'     => 20,
            'action_type'      => 'disposal.completed',
            'description'      => 'Disposal completed',
            'server_timestamp' => now(),
        ]);

        $response = $this->getJson('/api/v1/audit-logs?action_type=lot.created');

        $response->assertOk();

        $actionTypes = collect($response->json('data'))->pluck('action_type')->unique()->values()->all();
        $this->assertEquals(['lot.created'], $actionTypes);
    }

    public function test_audit_logs_can_be_filtered_by_user(): void
    {
        $admin     = $this->makeUserWithPermissions(['system.manage_roles']);
        $otherUser = $this->makeUserWithPermissions([]);
        Sanctum::actingAs($admin);

        AuditLog::query()->create([
            'user_id'          => $admin->id,
            'auditable_type'   => 'App\\Models\\Lot',
            'auditable_id'     => 1,
            'action_type'      => 'lot.updated',
            'server_timestamp' => now(),
        ]);

        AuditLog::query()->create([
            'user_id'          => $otherUser->id,
            'auditable_type'   => 'App\\Models\\Lot',
            'auditable_id'     => 2,
            'action_type'      => 'lot.updated',
            'server_timestamp' => now(),
        ]);

        $response = $this->getJson("/api/v1/audit-logs?user_id={$admin->id}");

        $response->assertOk();

        $userIds = collect($response->json('data'))->pluck('user_id')->unique()->values()->all();
        $this->assertNotContains($otherUser->id, $userIds);
    }

    public function test_audit_logs_can_be_filtered_by_date_range(): void
    {
        $admin = $this->makeUserWithPermissions(['system.manage_roles']);
        Sanctum::actingAs($admin);

        // Entry from a year ago
        AuditLog::query()->create([
            'user_id'          => $admin->id,
            'auditable_type'   => 'App\\Models\\Lot',
            'auditable_id'     => 99,
            'action_type'      => 'lot.created',
            'server_timestamp' => now()->subYear(),
        ]);

        // Entry from today
        AuditLog::query()->create([
            'user_id'          => $admin->id,
            'auditable_type'   => 'App\\Models\\Lot',
            'auditable_id'     => 100,
            'action_type'      => 'lot.created',
            'server_timestamp' => now(),
        ]);

        $fromDate = now()->subMonth()->toDateString();
        $toDate   = now()->toDateString();

        $response = $this->getJson("/api/v1/audit-logs?from_date={$fromDate}&to_date={$toDate}");

        $response->assertOk();

        // The year-old entry should not appear
        $auditableIds = collect($response->json('data'))->pluck('auditable_id')->all();
        $this->assertNotContains(99, $auditableIds);
        $this->assertContains(100, $auditableIds);
    }
}
