<?php

namespace Tests\Feature\Api\V1;

use App\Models\QrLabel;
use App\Models\QrPrintJob;
use Laravel\Sanctum\Sanctum;

class QrLabelTest extends FeatureTestCase
{
    // =========================================================================
    // QR Label — canonical format
    // =========================================================================

    public function test_guest_cannot_access_qr_label(): void
    {
        $product  = $this->createProduct();
        $supplier = $this->createSupplier();
        $lot      = $this->createLot($product, $supplier);

        $this->getJson("/api/v1/qr-labels/{$lot->id}")
            ->assertStatus(401);
    }

    public function test_user_without_permission_cannot_access_qr_label(): void
    {
        $product  = $this->createProduct();
        $supplier = $this->createSupplier();
        $lot      = $this->createLot($product, $supplier);

        Sanctum::actingAs($this->makeUserWithPermissions([]));

        $this->getJson("/api/v1/qr-labels/{$lot->id}")
            ->assertStatus(403);
    }

    public function test_can_fetch_qr_label_for_lot(): void
    {
        $user     = $this->makeUserWithPermissions(['stock_in.view']);
        $product  = $this->createProduct();
        $supplier = $this->createSupplier();
        $lot      = $this->createLot($product, $supplier, 'available', 'LOT-QR-001');
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/qr-labels/{$lot->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['lot_id', 'qr_payload', 'generated_at']]);
    }

    public function test_qr_label_payload_follows_canonical_format(): void
    {
        $user     = $this->makeUserWithPermissions(['stock_in.view']);
        $product  = $this->createProduct('REF-CANON-001');
        $supplier = $this->createSupplier();
        $lot      = $this->createLot($product, $supplier, 'available', 'LOT-CANON-001');
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/qr-labels/{$lot->id}");

        $response->assertOk();

        $payload = $response->json('data.qr_payload');

        // Must start with V=1
        $this->assertStringStartsWith('V=1;', $payload);

        // Must contain REF, LOT, BATCH, EXP segments
        $this->assertStringContainsString('REF=REF-CANON-001', $payload);
        $this->assertStringContainsString('LOT=LOT-CANON-001', $payload);
        $this->assertMatchesRegularExpression('/BATCH=[^;]+/', $payload);
        $this->assertMatchesRegularExpression('/EXP=\d{4}-\d{2}-\d{2}|-/', $payload);
    }

    public function test_qr_label_fetch_is_idempotent(): void
    {
        $user     = $this->makeUserWithPermissions(['stock_in.view']);
        $product  = $this->createProduct();
        $supplier = $this->createSupplier();
        $lot      = $this->createLot($product, $supplier, 'available', 'LOT-IDEM-001');
        Sanctum::actingAs($user);

        // Two fetches should return the same label record
        $this->getJson("/api/v1/qr-labels/{$lot->id}")->assertOk();
        $this->getJson("/api/v1/qr-labels/{$lot->id}")->assertOk();

        $this->assertEquals(1, QrLabel::query()->where('lot_id', $lot->id)->count());
    }

    // =========================================================================
    // QR Label — preview endpoint
    // =========================================================================

    public function test_guest_cannot_access_qr_label_preview(): void
    {
        $product  = $this->createProduct();
        $supplier = $this->createSupplier();
        $lot      = $this->createLot($product, $supplier);

        $this->getJson("/api/v1/qr-labels/{$lot->id}/preview")
            ->assertStatus(401);
    }

    public function test_preview_returns_payload_and_tspl_without_persisting(): void
    {
        $user     = $this->makeUserWithPermissions(['stock_in.view']);
        $product  = $this->createProduct('REF-PREV-001');
        $supplier = $this->createSupplier();
        $lot      = $this->createLot($product, $supplier, 'available', 'LOT-PREV-001');
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/qr-labels/{$lot->id}/preview");

        $response->assertOk()
            ->assertJsonStructure(['data' => ['lot_id', 'lot_number', 'qr_payload', 'tspl_payload']]);

        // Preview must NOT persist a QrLabel record
        $this->assertEquals(0, QrLabel::query()->where('lot_id', $lot->id)->count());
    }

    public function test_preview_payload_follows_canonical_format(): void
    {
        $user     = $this->makeUserWithPermissions(['stock_in.view']);
        $product  = $this->createProduct('REF-PREV-FORMAT');
        $supplier = $this->createSupplier();
        $lot      = $this->createLot($product, $supplier, 'available', 'LOT-PREV-FMT');
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/qr-labels/{$lot->id}/preview");

        $payload = $response->json('data.qr_payload');

        $this->assertStringStartsWith('V=1;', $payload);
        $this->assertStringContainsString('REF=REF-PREV-FORMAT', $payload);
        $this->assertStringContainsString('LOT=LOT-PREV-FMT', $payload);
    }

    // =========================================================================
    // Print Jobs — listing and show
    // =========================================================================

    public function test_guest_cannot_list_print_jobs(): void
    {
        $this->getJson('/api/v1/print-jobs')
            ->assertStatus(401);
    }

    public function test_user_without_permission_cannot_list_print_jobs(): void
    {
        Sanctum::actingAs($this->makeUserWithPermissions([]));

        $this->getJson('/api/v1/print-jobs')
            ->assertStatus(403);
    }

    public function test_can_list_print_jobs(): void
    {
        $user = $this->makeUserWithPermissions(['stock_in.view']);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/print-jobs');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data', 'pagination' => ['total']]);
    }

    public function test_can_show_print_job(): void
    {
        $user     = $this->makeUserWithPermissions(['stock_in.view']);
        $product  = $this->createProduct();
        $supplier = $this->createSupplier();
        $lot      = $this->createLot($product, $supplier, 'available', 'LOT-PJ-SHOW');
        Sanctum::actingAs($user);

        // Create a label first, then a job
        $label = QrLabel::query()->create([
            'lot_id'               => $lot->id,
            'qr_payload'           => 'V=1;REF=TEST;LOT=LOT-PJ-SHOW;BATCH=-;EXP=-',
            'generated_at'         => now(),
            'generated_by_user_id' => $user->id,
        ]);

        $job = QrPrintJob::query()->create([
            'lot_id'               => $lot->id,
            'qr_label_id'          => $label->id,
            'action_type'          => 'print',
            'status'               => 'queued',
            'tspl_payload'         => 'SIZE 40 mm, 30 mm',
            'requested_by_user_id' => $user->id,
            'requested_at'         => now(),
        ]);

        $this->getJson("/api/v1/print-jobs/{$job->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $job->id)
            ->assertJsonPath('data.status', 'queued');
    }

    // =========================================================================
    // Print Jobs — create
    // =========================================================================

    public function test_can_create_print_job_for_available_lot(): void
    {
        $user     = $this->makeUserWithPermissions(['stock_in.view']);
        $product  = $this->createProduct('REF-PJCRT-001');
        $supplier = $this->createSupplier();
        $lot      = $this->createLot($product, $supplier, 'available', 'LOT-PJCRT-001');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/print-jobs', [
            'lot_id'       => $lot->id,
            'printer_name' => 'BT-PRINTER-1',
            'device_id'    => 'DEVICE-ABC',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'queued')
            ->assertJsonPath('data.action_type', 'print');

        $this->assertDatabaseHas('qr_print_jobs', [
            'lot_id'      => $lot->id,
            'status'      => 'queued',
            'action_type' => 'print',
        ]);
    }

    public function test_create_print_job_requires_lot_id(): void
    {
        $user = $this->makeUserWithPermissions(['stock_in.view']);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/print-jobs', [])
            ->assertStatus(422);
    }

    // =========================================================================
    // Print Jobs — lifecycle (queued → printed)
    // =========================================================================

    public function test_can_mark_print_job_as_printed(): void
    {
        $user     = $this->makeUserWithPermissions(['stock_in.view']);
        $product  = $this->createProduct('REF-MK-PRINT');
        $supplier = $this->createSupplier();
        $lot      = $this->createLot($product, $supplier, 'available', 'LOT-MK-PRINT');
        Sanctum::actingAs($user);

        $label = QrLabel::query()->create([
            'lot_id'               => $lot->id,
            'qr_payload'           => 'V=1;REF=REF-MK-PRINT;LOT=LOT-MK-PRINT;BATCH=-;EXP=-',
            'generated_at'         => now(),
            'generated_by_user_id' => $user->id,
        ]);

        $job = QrPrintJob::query()->create([
            'lot_id'               => $lot->id,
            'qr_label_id'          => $label->id,
            'action_type'          => 'print',
            'status'               => 'queued',
            'tspl_payload'         => 'SIZE 40 mm, 30 mm',
            'requested_by_user_id' => $user->id,
            'requested_at'         => now(),
        ]);

        $response = $this->patchJson("/api/v1/print-jobs/{$job->id}/mark-printed");

        $response->assertOk()
            ->assertJsonPath('data.status', 'printed');

        $this->assertDatabaseHas('qr_print_jobs', ['id' => $job->id, 'status' => 'printed']);
    }

    public function test_cannot_mark_already_printed_job_as_printed_again(): void
    {
        $user     = $this->makeUserWithPermissions(['stock_in.view']);
        $product  = $this->createProduct();
        $supplier = $this->createSupplier();
        $lot      = $this->createLot($product, $supplier, 'available', 'LOT-DBLPRINT');
        Sanctum::actingAs($user);

        $label = QrLabel::query()->create([
            'lot_id'               => $lot->id,
            'qr_payload'           => 'V=1;REF=TEST;LOT=LOT-DBLPRINT;BATCH=-;EXP=-',
            'generated_at'         => now(),
            'generated_by_user_id' => $user->id,
        ]);

        $job = QrPrintJob::query()->create([
            'lot_id'               => $lot->id,
            'qr_label_id'          => $label->id,
            'action_type'          => 'print',
            'status'               => 'printed',  // already printed
            'printed_at'           => now(),
            'tspl_payload'         => 'SIZE 40 mm, 30 mm',
            'requested_by_user_id' => $user->id,
            'requested_at'         => now(),
        ]);

        $this->patchJson("/api/v1/print-jobs/{$job->id}/mark-printed")
            ->assertStatus(400);
    }

    // =========================================================================
    // Print Jobs — lifecycle (queued → failed)
    // =========================================================================

    public function test_can_mark_print_job_as_failed(): void
    {
        $user     = $this->makeUserWithPermissions(['stock_in.view']);
        $product  = $this->createProduct();
        $supplier = $this->createSupplier();
        $lot      = $this->createLot($product, $supplier, 'available', 'LOT-FAIL-001');
        Sanctum::actingAs($user);

        $label = QrLabel::query()->create([
            'lot_id'               => $lot->id,
            'qr_payload'           => 'V=1;REF=TEST;LOT=LOT-FAIL-001;BATCH=-;EXP=-',
            'generated_at'         => now(),
            'generated_by_user_id' => $user->id,
        ]);

        $job = QrPrintJob::query()->create([
            'lot_id'               => $lot->id,
            'qr_label_id'          => $label->id,
            'action_type'          => 'print',
            'status'               => 'queued',
            'tspl_payload'         => 'SIZE 40 mm, 30 mm',
            'requested_by_user_id' => $user->id,
            'requested_at'         => now(),
        ]);

        $response = $this->patchJson("/api/v1/print-jobs/{$job->id}/mark-failed", [
            'error_message' => 'BLE connection lost',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'failed');

        $this->assertDatabaseHas('qr_print_jobs', ['id' => $job->id, 'status' => 'failed']);
    }

    public function test_mark_failed_requires_error_message(): void
    {
        $user     = $this->makeUserWithPermissions(['stock_in.view']);
        $product  = $this->createProduct();
        $supplier = $this->createSupplier();
        $lot      = $this->createLot($product, $supplier, 'available', 'LOT-FAIL-REQ');
        Sanctum::actingAs($user);

        $label = QrLabel::query()->create([
            'lot_id'               => $lot->id,
            'qr_payload'           => 'V=1;REF=TEST;LOT=LOT-FAIL-REQ;BATCH=-;EXP=-',
            'generated_at'         => now(),
            'generated_by_user_id' => $user->id,
        ]);

        $job = QrPrintJob::query()->create([
            'lot_id'               => $lot->id,
            'qr_label_id'          => $label->id,
            'action_type'          => 'print',
            'status'               => 'queued',
            'tspl_payload'         => 'SIZE 40 mm, 30 mm',
            'requested_by_user_id' => $user->id,
            'requested_at'         => now(),
        ]);

        $this->patchJson("/api/v1/print-jobs/{$job->id}/mark-failed", [])
            ->assertStatus(422);
    }

    // =========================================================================
    // Print Jobs — reprint lifecycle
    // =========================================================================

    public function test_reprint_requires_reason(): void
    {
        $user     = $this->makeUserWithPermissions(['stock_in.view']);
        $product  = $this->createProduct('REF-REPRINT-REQ');
        $supplier = $this->createSupplier();
        $lot      = $this->createLot($product, $supplier, 'available', 'LOT-REPRINT-REQ');
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/print-jobs/reprint', [
            'lot_id' => $lot->id,
            // missing 'reason'
        ])->assertStatus(422);
    }

    public function test_can_create_reprint_job_with_reason(): void
    {
        $user     = $this->makeUserWithPermissions(['stock_in.view']);
        $product  = $this->createProduct('REF-REPRINT-OK');
        $supplier = $this->createSupplier();
        $lot      = $this->createLot($product, $supplier, 'available', 'LOT-REPRINT-OK');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/print-jobs/reprint', [
            'lot_id' => $lot->id,
            'reason' => 'Label was torn during application',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.action_type', 'reprint')
            ->assertJsonPath('data.status', 'queued');

        $this->assertDatabaseHas('qr_print_jobs', [
            'lot_id'         => $lot->id,
            'action_type'    => 'reprint',
            'reprint_reason' => 'Label was torn during application',
        ]);
    }

    public function test_print_job_list_filterable_by_device_id(): void
    {
        $user     = $this->makeUserWithPermissions(['stock_in.view']);
        $product  = $this->createProduct();
        $supplier = $this->createSupplier();
        $lot      = $this->createLot($product, $supplier, 'available', 'LOT-DEV-FLT');
        Sanctum::actingAs($user);

        $label = QrLabel::query()->create([
            'lot_id'               => $lot->id,
            'qr_payload'           => 'V=1;REF=TEST;LOT=LOT-DEV-FLT;BATCH=-;EXP=-',
            'generated_at'         => now(),
            'generated_by_user_id' => $user->id,
        ]);

        QrPrintJob::query()->create([
            'lot_id'               => $lot->id,
            'qr_label_id'          => $label->id,
            'action_type'          => 'print',
            'status'               => 'queued',
            'device_id'            => 'DEVICE-TARGET',
            'tspl_payload'         => 'SIZE 40 mm, 30 mm',
            'requested_by_user_id' => $user->id,
            'requested_at'         => now(),
        ]);

        QrPrintJob::query()->create([
            'lot_id'               => $lot->id,
            'qr_label_id'          => $label->id,
            'action_type'          => 'print',
            'status'               => 'queued',
            'device_id'            => 'DEVICE-OTHER',
            'tspl_payload'         => 'SIZE 40 mm, 30 mm',
            'requested_by_user_id' => $user->id,
            'requested_at'         => now(),
        ]);

        $response = $this->getJson('/api/v1/print-jobs?device_id=DEVICE-TARGET');

        $response->assertOk();

        $deviceIds = collect($response->json('data'))->pluck('device_id')->unique()->values()->all();
        $this->assertEquals(['DEVICE-TARGET'], $deviceIds);
    }
}
