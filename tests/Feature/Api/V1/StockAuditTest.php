<?php

namespace Tests\Feature\Api\V1;

use App\Models\LotMovement;
use Laravel\Sanctum\Sanctum;

class StockAuditTest extends FeatureTestCase
{
    private array $allPermissions = [
        'stock_audits.view', 'stock_audits.create', 'stock_audits.count',
        'stock_audits.review', 'stock_audits.complete', 'stock_audits.cancel', 'stock_audits.export',
    ];

    public function test_guest_and_unpermitted_user_are_rejected(): void
    {
        $this->getJson('/api/v1/stock-audits')->assertUnauthorized();

        Sanctum::actingAs($this->makeUserWithPermissions([]));
        $this->getJson('/api/v1/stock-audits')->assertForbidden();
    }

    public function test_full_blind_count_workflow_classifies_zero_count_as_short_and_does_not_change_inventory(): void
    {
        $user = $this->makeUserWithPermissions($this->allPermissions);
        Sanctum::actingAs($user);
        $product = $this->createProduct('AUDIT-REF');
        $supplier = $this->createSupplier();
        $tallyLot = $this->createLot($product, $supplier, 'available', 'AUDIT-LOT-1');
        $tallyLot->update(['quantity' => 5, 'quantity_available' => 5]);
        $missingLot = $this->createLot($product, $supplier, 'holding', 'AUDIT-LOT-2');
        $missingLot->update(['quantity' => 3, 'quantity_available' => 3]);
        $clientLot = $this->createLot($product, $supplier, 'supplied', 'CLIENT-LOT');
        $clientLot->update(['quantity_available' => 2, 'current_location_type' => 'client', 'current_location_id' => 44]);

        $auditId = $this->postJson('/api/v1/stock-audits', ['remarks' => 'October count'])
            ->assertCreated()
            ->json('data.id');

        $this->postJson("/api/v1/stock-audits/{$auditId}/start")
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.summary.total_lines', 2);

        $items = $this->getJson("/api/v1/stock-audits/{$auditId}/items?per_page=100")
            ->assertOk()
            ->assertJsonPath('pagination.total', 2)
            ->json('data');
        $tallyItem = collect($items)->firstWhere('lot_id', $tallyLot->id);
        $shortItem = collect($items)->firstWhere('lot_id', $missingLot->id);
        $this->assertNull($tallyItem['expected_quantity']);

        $this->putJson("/api/v1/stock-audits/{$auditId}/counts", [
            'items' => [
                [
                    'id' => $tallyItem['id'],
                    'counted_quantity' => 5,
                    'version' => $tallyItem['version'],
                    'count_method' => 'manual',
                ],
                [
                    'id' => $shortItem['id'],
                    'counted_quantity' => 0,
                    'version' => $shortItem['version'],
                    'count_method' => 'manual',
                    'remarks' => 'Stock not found during count',
                ],
            ],
        ])->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.result', 'counted')
            ->assertJsonPath('data.1.result', 'counted')
            ->assertJsonPath('data.1.remarks', 'Stock not found during count');

        $this->postJson("/api/v1/stock-audits/{$auditId}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', 'review')
            ->assertJsonPath('data.summary.tally', 1)
            ->assertJsonPath('data.summary.short', 1)
            ->assertJsonPath('data.summary.missing_quantity', 3);

        $this->postJson("/api/v1/stock-audits/{$auditId}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->assertSame(5, $tallyLot->refresh()->quantity_available);
        $this->assertSame(3, $missingLot->refresh()->quantity_available);
        $this->assertDatabaseCount('lot_movements', 0);
    }

    public function test_qr_lookup_uses_reference_and_lot_and_stale_count_returns_conflict(): void
    {
        $user = $this->makeUserWithPermissions($this->allPermissions);
        Sanctum::actingAs($user);
        $product = $this->createProduct('QR-REF');
        $lot = $this->createLot($product, $this->createSupplier(), 'available', 'SHARED-LOT');

        $auditId = $this->postJson('/api/v1/stock-audits')->json('data.id');
        $this->postJson("/api/v1/stock-audits/{$auditId}/start")->assertOk();

        $item = $this->postJson("/api/v1/stock-audits/{$auditId}/lookup", [
            'qr_payload' => 'V=1;REF=QR-REF;LOT=SHARED-LOT;MFG=2026-01-01;EXP=2027-06-30',
        ])->assertOk()->assertJsonPath('data.lot_id', $lot->id)->json('data');

        $payload = ['counted_quantity' => 1, 'version' => $item['version'], 'count_method' => 'qr'];
        $this->putJson("/api/v1/stock-audits/{$auditId}/items/{$item['id']}/count", $payload)->assertOk();
        $this->putJson("/api/v1/stock-audits/{$auditId}/items/{$item['id']}/count", $payload)->assertStatus(409);
    }

    public function test_inventory_movement_after_snapshot_blocks_submission_and_can_be_refreshed(): void
    {
        $user = $this->makeUserWithPermissions($this->allPermissions);
        Sanctum::actingAs($user);
        $lot = $this->createLot($this->createProduct(), $this->createSupplier(), 'available');
        $auditId = $this->postJson('/api/v1/stock-audits')->json('data.id');
        $this->postJson("/api/v1/stock-audits/{$auditId}/start")->assertOk();

        LotMovement::query()->create([
            'lot_id' => $lot->id,
            'movement_type' => 'consigned',
            'from_status' => 'available',
            'to_status' => 'available',
            'from_location_type' => 'warehouse',
            'to_location_type' => 'warehouse',
            'performed_at' => now(),
            'performed_by_user_id' => $user->id,
            'quantity' => 1,
        ]);

        $this->postJson("/api/v1/stock-audits/{$auditId}/submit")
            ->assertStatus(409);
        $this->getJson("/api/v1/stock-audits/{$auditId}/conflicts")
            ->assertOk()
            ->assertJsonPath('data.count', 1);
        $this->postJson("/api/v1/stock-audits/{$auditId}/refresh-conflicts")
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.summary.pending', 1);
    }

    public function test_completed_audit_can_be_exported_as_csv(): void
    {
        $user = $this->makeUserWithPermissions($this->allPermissions);
        Sanctum::actingAs($user);
        $this->createLot($this->createProduct('EXPORT-REF'), $this->createSupplier(), 'available', 'EXPORT-LOT');
        $auditId = $this->postJson('/api/v1/stock-audits')->json('data.id');
        $this->postJson("/api/v1/stock-audits/{$auditId}/start")->assertOk();
        $item = $this->getJson("/api/v1/stock-audits/{$auditId}/items?per_page=100")
            ->assertOk()
            ->json('data.0');
        $this->putJson("/api/v1/stock-audits/{$auditId}/counts", [
            'items' => [[
                'id' => $item['id'],
                'counted_quantity' => 0,
                'version' => $item['version'],
                'count_method' => 'manual',
            ]],
        ])->assertOk();
        $this->postJson("/api/v1/stock-audits/{$auditId}/submit")->assertOk();
        $this->postJson("/api/v1/stock-audits/{$auditId}/complete")->assertOk();

        $response = $this->postJson("/api/v1/stock-audits/{$auditId}/export", ['format' => 'csv']);
        $response->assertOk();
        $this->assertStringContainsStringIgnoringCase(
            'csv',
            $response->headers->get('Content-Type', '').' '.$response->headers->get('Content-Disposition', ''),
        );
    }
}
