<?php

namespace Tests\Feature\Console;

use App\Models\Consignment;
use App\Models\ConsignmentItem;
use App\Models\LotMovement;
use App\Models\QrLabel;
use App\Models\QrPrintJob;
use App\Models\Reconciliation;
use App\Models\ReconciliationItem;
use App\Models\ReturnSession;
use App\Models\ReturnSessionItem;
use App\Models\StockIn;
use App\Models\StockInItem;
use Tests\Feature\Api\V1\FeatureTestCase;

class ResetFinalizedInventoryCommandTest extends FeatureTestCase
{
    public function test_it_removes_finalized_inventory_and_linked_consignment_records_only(): void
    {
        $user = $this->makeUserWithPermissions([]);
        $supplier = $this->createSupplier();
        $client = $this->createClient();
        $product = $this->createProduct();

        $finalizedStockIn = StockIn::query()->create([
            'supplier_id' => $supplier->id,
            'session_no' => 'SI-RESET-FINAL',
            'do_number' => 'DO-RESET-FINAL',
            'stock_in_at' => now(),
            'pic_user_id' => $user->id,
            'status' => 'finalized',
        ]);
        $lot = $this->createLot($product, $supplier, lotNumber: 'LOT-RESET-FINAL');
        StockInItem::query()->create([
            'stock_in_id' => $finalizedStockIn->id,
            'product_id' => $product->id,
            'lot_id' => $lot->id,
            'scanned_lot_number' => $lot->lot_number,
        ]);
        LotMovement::query()->create([
            'lot_id' => $lot->id,
            'movement_type' => 'stock_in',
            'reference_type' => StockIn::class,
            'reference_id' => $finalizedStockIn->id,
            'to_status' => 'available',
            'performed_at' => now(),
            'performed_by_user_id' => $user->id,
        ]);

        $label = QrLabel::query()->create([
            'lot_id' => $lot->id,
            'qr_payload' => 'test-payload',
            'generated_at' => now(),
            'generated_by_user_id' => $user->id,
        ]);
        QrPrintJob::query()->create([
            'lot_id' => $lot->id,
            'qr_label_id' => $label->id,
            'action_type' => 'print',
            'status' => 'pending',
            'requested_by_user_id' => $user->id,
            'requested_at' => now(),
        ]);

        $consignment = Consignment::query()->create([
            'client_id' => $client->id,
            'consignment_no' => 'CN-RESET-FINAL',
            'consignment_at' => now(),
            'pic_user_id' => $user->id,
            'status' => 'confirmed',
        ]);
        ConsignmentItem::query()->create([
            'consignment_id' => $consignment->id,
            'lot_id' => $lot->id,
            'issued_at' => now(),
            'issued_by_user_id' => $user->id,
        ]);
        $returnSession = ReturnSession::query()->create([
            'consignment_id' => $consignment->id,
            'return_session_no' => 'RS-RESET-FINAL',
            'pic_user_id' => $user->id,
            'status' => 'completed',
            'started_at' => now(),
        ]);
        $returnItem = ReturnSessionItem::query()->create([
            'return_session_id' => $returnSession->id,
            'lot_id' => $lot->id,
            'returned_at' => now(),
            'returned_by_user_id' => $user->id,
        ]);
        $reconciliation = Reconciliation::query()->create([
            'consignment_id' => $consignment->id,
            'return_session_id' => $returnSession->id,
            'reconciliation_no' => 'RC-RESET-FINAL',
            'pic_user_id' => $user->id,
            'status' => 'finalized',
        ]);
        $reconciliationItem = ReconciliationItem::query()->create([
            'reconciliation_id' => $reconciliation->id,
            'lot_id' => $lot->id,
            'result' => 'returned',
        ]);

        $draftStockIn = StockIn::query()->create([
            'supplier_id' => $supplier->id,
            'session_no' => 'SI-RESET-DRAFT',
            'do_number' => 'DO-RESET-DRAFT',
            'stock_in_at' => now(),
            'pic_user_id' => $user->id,
            'status' => 'draft',
        ]);

        $this->artisan('tretech:reset-finalized-inventory --force')
            ->expectsConfirmation('This permanently deletes the listed inventory transactions. Continue?', 'yes')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('stock_ins', ['id' => $finalizedStockIn->id]);
        $this->assertDatabaseMissing('stock_in_items', ['stock_in_id' => $finalizedStockIn->id]);
        $this->assertDatabaseMissing('lots', ['id' => $lot->id]);
        $this->assertDatabaseMissing('qr_labels', ['id' => $label->id]);
        $this->assertDatabaseMissing('qr_print_jobs', ['lot_id' => $lot->id]);
        $this->assertDatabaseMissing('consignments', ['id' => $consignment->id]);
        $this->assertDatabaseMissing('return_sessions', ['id' => $returnSession->id]);
        $this->assertDatabaseMissing('return_session_items', ['id' => $returnItem->id]);
        $this->assertDatabaseMissing('reconciliations', ['id' => $reconciliation->id]);
        $this->assertDatabaseMissing('reconciliation_items', ['id' => $reconciliationItem->id]);

        $this->assertDatabaseHas('stock_ins', ['id' => $draftStockIn->id, 'status' => 'draft']);
        $this->assertDatabaseHas('clients', ['id' => $client->id]);
        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id]);
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }
}
