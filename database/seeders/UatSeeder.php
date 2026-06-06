<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Product;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * UAT / Staging seeder — creates realistic end-to-end lifecycle data.
 *
 * Scenarios covered:
 *   SIN-UAT-001  Finalized stock-in (Medline Nusantara, 3 lots)
 *   SIN-UAT-002  Finalized stock-in (SteriPro Labs, 3 lots)
 *   SIN-UAT-003  Finalized stock-in (Global MedPharm, 2 lots)
 *   CON-UAT-001  Consignment → confirmed → return completed → reconciliation finalized
 *   CON-UAT-002  Consignment → confirmed → return in-progress → reconciliation pending
 *   DSP-UAT-001  Disposal (completed) — expired lot
 *   Holding      One lot placed on hold (damaged packaging)
 */
class UatSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->seed();
        });
    }

    // -------------------------------------------------------------------------

    private function seed(): void
    {
        $now    = Carbon::now();
        $t      = $now;   // shorthand for inline timestamps

        // ------------------------------------------------------------------ //
        // STEP 0 · Resolve base users / roles
        // ------------------------------------------------------------------ //
        $adminRole     = Role::query()->where('role_code', 'admin')->firstOrFail();
        $staffRole     = Role::query()->where('role_code', 'logistic_staff')->firstOrFail();

        /** @var User $admin */
        $admin = User::query()->where('email', config('app.admin.email', 'admin@tretech.com'))->firstOrFail();
        $adminId = $admin->id;

        // ------------------------------------------------------------------ //
        // STEP 1 · Additional master data
        // ------------------------------------------------------------------ //
        $supplierGmp = Supplier::query()->firstOrCreate(
            ['supplier_name' => 'Global MedPharm'],
            [
                'phone'     => '+60-3-5555-0103',
                'email'     => 'supply@globalmedpharm.my',
                'address'   => 'Shah Alam, Selangor',
                'is_active' => true,
            ]
        );

        $clientPkm = Client::query()->firstOrCreate(
            ['client_name' => 'Klinik Kesehatan Bandar Johor', 'client_type' => 'clinic'],
            [
                'phone'     => '+60-7-5555-0203',
                'email'     => 'admin@kkhbandarjohor.gov.my',
                'address'   => 'Johor Bahru, Johor',
                'is_active' => true,
            ]
        );

        $clientHhb = Client::query()->firstOrCreate(
            ['client_name' => 'Hospital Ampang Putra', 'client_type' => 'hospital'],
            [
                'phone'     => '+60-3-4555-0204',
                'email'     => 'procurement@hap.com.my',
                'address'   => 'Ampang, Selangor',
                'is_active' => true,
            ]
        );

        $productMsk = Product::query()->firstOrCreate(
            ['ref_num' => 'P-MSK-001'],
            [
                'product_name'    => 'Surgical Mask Type IIR',
                'product_type'    => 'consumable',
                'category'        => 'PPE',
                'uom'             => 'box',
                'requires_expiry' => true,
                'requires_lot'    => true,
                'is_active'       => true,
            ]
        );

        $productCat = Product::query()->firstOrCreate(
            ['ref_num' => 'P-CAT-001'],
            [
                'product_name'    => 'Urinary Catheter Kit 14Fr',
                'product_type'    => 'consumable',
                'category'        => 'Urology',
                'uom'             => 'pcs',
                'requires_expiry' => true,
                'requires_lot'    => true,
                'is_active'       => true,
            ]
        );

        // Resolve existing master data created by SampleMasterDataSeeder
        $supplierMed = Supplier::query()->where('supplier_name', 'Medline Malaysia')->firstOrFail();
        $supplierStp = Supplier::query()->where('supplier_name', 'SteriPro Asia')->firstOrFail();
        $clientRss   = Client::query()->where('client_name', 'Hospital Selangor Daya')->firstOrFail();
        $clientKpc   = Client::query()->where('client_name', 'Klinik Prima Care')->firstOrFail();
        $productGlv  = Product::query()->where('ref_num', 'P-GLV-001')->firstOrFail();
        $productSyr  = Product::query()->where('ref_num', 'P-SYR-001')->firstOrFail();
        $productIns  = Product::query()->where('ref_num', 'P-INS-001')->firstOrFail();

        // ------------------------------------------------------------------ //
        // STEP 2 · Logistic staff users
        // ------------------------------------------------------------------ //
        $staff1 = User::query()->updateOrCreate(
            ['email' => 'muhammad.hassan@tretech.com'],
            [
                'role_id'       => $staffRole->id,
                'full_name'     => 'Muhammad Hassan',
                'password_hash' => Hash::make('Staff123!'),
                'is_active'     => true,
            ]
        );

        $staff2 = User::query()->updateOrCreate(
            ['email' => 'nurul.aida@tretech.com'],
            [
                'role_id'       => $staffRole->id,
                'full_name'     => 'Nurul Aida',
                'password_hash' => Hash::make('Staff123!'),
                'is_active'     => true,
            ]
        );

        $staff1Id = $staff1->id;
        $staff2Id = $staff2->id;

        // ------------------------------------------------------------------ //
        // STEP 3 · Stock-In SIN-UAT-001  (Medline Nusantara · 3 lots)
        // ------------------------------------------------------------------ //
        $sin1At = $now->copy()->subDays(30);

        $sin1Id = $this->firstOrInsert('stock_ins', 'session_no', [
            'supplier_id'            => $supplierMed->id,
            'session_no'             => 'SIN-UAT-001',
            'do_number'              => 'DO-MED-2025-001',
            'stock_in_at'            => $sin1At,
            'pic_user_id'            => $staff1Id,
            'status'                 => 'finalized',
            'confirmed_at'           => $sin1At->copy()->addHours(2),
            'confirmed_by_user_id'   => $adminId,
            'created_at'             => $sin1At,
            'updated_at'             => $sin1At->copy()->addHours(2),
        ]);

        // Lots for SIN-UAT-001
        $lotGlv1Id = $this->seedLot([
            'product_id'                => $productGlv->id,
            'supplier_id'               => $supplierMed->id,
            'lot_number'                => 'LOT-GLV-U001',
            'supplier_batch_code'       => 'MED-B2501',
            'expiry_date'               => '2027-06-30',
            'status'                    => 'available',   // returned & back in warehouse
            'current_location_type'     => 'warehouse',
            'current_location_id'       => null,
            'received_at'               => $sin1At,
            'now'                       => $now,
        ]);

        $lotGlv2Id = $this->seedLot([
            'product_id'                => $productGlv->id,
            'supplier_id'               => $supplierMed->id,
            'lot_number'                => 'LOT-GLV-U002',
            'supplier_batch_code'       => 'MED-B2502',
            'expiry_date'               => '2027-06-30',
            'status'                    => 'available',   // returned & back in warehouse
            'current_location_type'     => 'warehouse',
            'current_location_id'       => null,
            'received_at'               => $sin1At,
            'now'                       => $now,
        ]);

        $lotSyr1Id = $this->seedLot([
            'product_id'                => $productSyr->id,
            'supplier_id'               => $supplierMed->id,
            'lot_number'                => 'LOT-SYR-U001',
            'supplier_batch_code'       => 'MED-B2503',
            'expiry_date'               => '2026-12-31',
            'status'                    => 'used',        // confirmed used in reconciliation
            'current_location_type'     => null,
            'current_location_id'       => null,
            'received_at'               => $sin1At,
            'now'                       => $now,
        ]);

        $this->seedStockInItems($sin1Id, [
            ['product_id' => $productGlv->id, 'lot_id' => $lotGlv1Id, 'scanned_lot_number' => 'LOT-GLV-U001', 'supplier_batch_code' => 'MED-B2501', 'expiry_date' => '2027-06-30', 'now' => $now],
            ['product_id' => $productGlv->id, 'lot_id' => $lotGlv2Id, 'scanned_lot_number' => 'LOT-GLV-U002', 'supplier_batch_code' => 'MED-B2502', 'expiry_date' => '2027-06-30', 'now' => $now],
            ['product_id' => $productSyr->id, 'lot_id' => $lotSyr1Id, 'scanned_lot_number' => 'LOT-SYR-U001', 'supplier_batch_code' => 'MED-B2503', 'expiry_date' => '2026-12-31', 'now' => $now],
        ]);

        $this->seedReceiveLotMovements(
            [$lotGlv1Id, $lotGlv2Id, $lotSyr1Id],
            $sin1Id, $sin1At, $staff1Id
        );

        $this->seedQrLabelsAndPrintJobs(
            [$lotGlv1Id, $lotGlv2Id, $lotSyr1Id],
            ['LOT-GLV-U001', 'LOT-GLV-U002', 'LOT-SYR-U001'],
            ['P-GLV-001', 'P-GLV-001', 'P-SYR-001'],
            ['MED-B2501', 'MED-B2502', 'MED-B2503'],
            ['2027-06-30', '2027-06-30', '2026-12-31'],
            $sin1At, $staff1Id
        );

        // ------------------------------------------------------------------ //
        // STEP 4 · Stock-In SIN-UAT-002  (SteriPro Labs · 3 lots)
        // ------------------------------------------------------------------ //
        $sin2At = $now->copy()->subDays(20);

        $sin2Id = $this->firstOrInsert('stock_ins', 'session_no', [
            'supplier_id'            => $supplierStp->id,
            'session_no'             => 'SIN-UAT-002',
            'do_number'              => 'DO-STP-2025-001',
            'stock_in_at'            => $sin2At,
            'pic_user_id'            => $staff2Id,
            'status'                 => 'finalized',
            'confirmed_at'           => $sin2At->copy()->addHours(1),
            'confirmed_by_user_id'   => $adminId,
            'created_at'             => $sin2At,
            'updated_at'             => $sin2At->copy()->addHours(1),
        ]);

        $lotIns1Id = $this->seedLot([
            'product_id'                => $productIns->id,
            'supplier_id'               => $supplierStp->id,
            'lot_number'                => 'LOT-INS-U001',
            'supplier_batch_code'       => 'STP-B2501',
            'expiry_date'               => null,
            'status'                    => 'supplied',    // in active consignment
            'current_location_type'     => 'consignment',
            'current_location_id'       => null,          // updated after consignment insert
            'received_at'               => $sin2At,
            'now'                       => $now,
        ]);

        $lotIns2Id = $this->seedLot([
            'product_id'                => $productIns->id,
            'supplier_id'               => $supplierStp->id,
            'lot_number'                => 'LOT-INS-U002',
            'supplier_batch_code'       => 'STP-B2502',
            'expiry_date'               => null,
            'status'                    => 'holding',     // put on hold
            'current_location_type'     => 'holding',
            'current_location_id'       => null,
            'received_at'               => $sin2At,
            'now'                       => $now,
        ]);

        $lotMsk1Id = $this->seedLot([
            'product_id'                => $productMsk->id,
            'supplier_id'               => $supplierStp->id,
            'lot_number'                => 'LOT-MSK-U001',
            'supplier_batch_code'       => 'STP-B2503',
            'expiry_date'               => '2026-09-30',
            'status'                    => 'supplied',    // in active consignment
            'current_location_type'     => 'consignment',
            'current_location_id'       => null,          // updated after consignment insert
            'received_at'               => $sin2At,
            'now'                       => $now,
        ]);

        $this->seedStockInItems($sin2Id, [
            ['product_id' => $productIns->id, 'lot_id' => $lotIns1Id, 'scanned_lot_number' => 'LOT-INS-U001', 'supplier_batch_code' => 'STP-B2501', 'expiry_date' => null, 'now' => $now],
            ['product_id' => $productIns->id, 'lot_id' => $lotIns2Id, 'scanned_lot_number' => 'LOT-INS-U002', 'supplier_batch_code' => 'STP-B2502', 'expiry_date' => null, 'now' => $now],
            ['product_id' => $productMsk->id, 'lot_id' => $lotMsk1Id, 'scanned_lot_number' => 'LOT-MSK-U001', 'supplier_batch_code' => 'STP-B2503', 'expiry_date' => '2026-09-30', 'now' => $now],
        ]);

        $this->seedReceiveLotMovements(
            [$lotIns1Id, $lotIns2Id, $lotMsk1Id],
            $sin2Id, $sin2At, $staff2Id
        );

        $this->seedQrLabelsAndPrintJobs(
            [$lotIns1Id, $lotIns2Id, $lotMsk1Id],
            ['LOT-INS-U001', 'LOT-INS-U002', 'LOT-MSK-U001'],
            ['P-INS-001', 'P-INS-001', 'P-MSK-001'],
            ['STP-B2501', 'STP-B2502', 'STP-B2503'],
            [null, null, '2026-09-30'],
            $sin2At, $staff2Id
        );

        // ------------------------------------------------------------------ //
        // STEP 5 · Stock-In SIN-UAT-003  (Global MedPharm · 2 lots)
        // ------------------------------------------------------------------ //
        $sin3At = $now->copy()->subDays(15);

        $sin3Id = $this->firstOrInsert('stock_ins', 'session_no', [
            'supplier_id'            => $supplierGmp->id,
            'session_no'             => 'SIN-UAT-003',
            'do_number'              => 'DO-GMP-2025-001',
            'stock_in_at'            => $sin3At,
            'pic_user_id'            => $staff1Id,
            'status'                 => 'finalized',
            'confirmed_at'           => $sin3At->copy()->addHours(1),
            'confirmed_by_user_id'   => $adminId,
            'created_at'             => $sin3At,
            'updated_at'             => $sin3At->copy()->addHours(1),
        ]);

        $lotCat1Id = $this->seedLot([
            'product_id'                => $productCat->id,
            'supplier_id'               => $supplierGmp->id,
            'lot_number'                => 'LOT-CAT-U001',
            'supplier_batch_code'       => 'GMP-B2501',
            'expiry_date'               => '2024-01-01',  // expired
            'status'                    => 'disposed',
            'current_location_type'     => null,
            'current_location_id'       => null,
            'received_at'               => $sin3At,
            'now'                       => $now,
        ]);

        $lotCat2Id = $this->seedLot([
            'product_id'                => $productCat->id,
            'supplier_id'               => $supplierGmp->id,
            'lot_number'                => 'LOT-CAT-U002',
            'supplier_batch_code'       => 'GMP-B2502',
            'expiry_date'               => '2026-08-31',
            'status'                    => 'available',
            'current_location_type'     => 'warehouse',
            'current_location_id'       => null,
            'received_at'               => $sin3At,
            'now'                       => $now,
        ]);

        $this->seedStockInItems($sin3Id, [
            ['product_id' => $productCat->id, 'lot_id' => $lotCat1Id, 'scanned_lot_number' => 'LOT-CAT-U001', 'supplier_batch_code' => 'GMP-B2501', 'expiry_date' => '2024-01-01', 'now' => $now],
            ['product_id' => $productCat->id, 'lot_id' => $lotCat2Id, 'scanned_lot_number' => 'LOT-CAT-U002', 'supplier_batch_code' => 'GMP-B2502', 'expiry_date' => '2026-08-31', 'now' => $now],
        ]);

        $this->seedReceiveLotMovements(
            [$lotCat1Id, $lotCat2Id],
            $sin3Id, $sin3At, $staff1Id
        );

        $this->seedQrLabelsAndPrintJobs(
            [$lotCat1Id, $lotCat2Id],
            ['LOT-CAT-U001', 'LOT-CAT-U002'],
            ['P-CAT-001', 'P-CAT-001'],
            ['GMP-B2501', 'GMP-B2502'],
            ['2024-01-01', '2026-08-31'],
            $sin3At, $staff1Id
        );

        // ------------------------------------------------------------------ //
        // STEP 6 · Consignment CON-UAT-001  (Hospital Selangor Daya · fully reconciled)
        // ------------------------------------------------------------------ //
        $con1At = $now->copy()->subDays(25);
        $con1ConfirmedAt = $con1At->copy()->addHours(3);

        $con1Id = $this->firstOrInsert('consignments', 'consignment_no', [
            'client_id'              => $clientRss->id,
            'consignment_no'         => 'CON-UAT-001',
            'consignment_at'         => $con1At,
            'pic_user_id'            => $staff1Id,
            'status'                 => 'confirmed',
            'remarks'                => 'UAT scenario: fully reconciled consignment to Hospital Selangor Daya.',
            'confirmed_at'           => $con1ConfirmedAt,
            'confirmed_by_user_id'   => $adminId,
            'edited_after_confirmation' => false,
            'created_at'             => $con1At,
            'updated_at'             => $con1ConfirmedAt,
        ]);

        $this->seedConsignmentItems($con1Id, [
            ['lot_id' => $lotGlv1Id, 'issued_at' => $con1ConfirmedAt, 'issued_by_user_id' => $staff1Id, 'now' => $now],
            ['lot_id' => $lotGlv2Id, 'issued_at' => $con1ConfirmedAt, 'issued_by_user_id' => $staff1Id, 'now' => $now],
            ['lot_id' => $lotSyr1Id, 'issued_at' => $con1ConfirmedAt, 'issued_by_user_id' => $staff1Id, 'now' => $now],
        ]);

        $this->seedLotMovement($lotGlv1Id, 'consignment_out', 'consignment', $con1Id, 'available', 'supplied', 'warehouse', null, 'consignment', $con1Id, $con1ConfirmedAt, $staff1Id);
        $this->seedLotMovement($lotGlv2Id, 'consignment_out', 'consignment', $con1Id, 'available', 'supplied', 'warehouse', null, 'consignment', $con1Id, $con1ConfirmedAt, $staff1Id);
        $this->seedLotMovement($lotSyr1Id, 'consignment_out', 'consignment', $con1Id, 'available', 'supplied', 'warehouse', null, 'consignment', $con1Id, $con1ConfirmedAt, $staff1Id);

        // Return session RSN-UAT-001 (completed)
        $rsn1At = $now->copy()->subDays(18);
        $rsn1CompletedAt = $rsn1At->copy()->addHours(4);

        $rsn1Id = $this->firstOrInsert('return_sessions', 'return_session_no', [
            'consignment_id'        => $con1Id,
            'return_session_no'     => 'RSN-UAT-001',
            'pic_user_id'           => $staff2Id,
            'status'                => 'completed',
            'remarks'               => 'All lots returned. Two for restocking, one confirmed used.',
            'started_at'            => $rsn1At,
            'completed_at'          => $rsn1CompletedAt,
            'completed_by_user_id'  => $staff2Id,
            'created_at'            => $rsn1At,
            'updated_at'            => $rsn1CompletedAt,
        ]);

        $this->seedReturnSessionItems($rsn1Id, [
            ['lot_id' => $lotGlv1Id, 'returned_at' => $rsn1At->copy()->addHours(1), 'returned_by_user_id' => $staff2Id, 'now' => $now],
            ['lot_id' => $lotGlv2Id, 'returned_at' => $rsn1At->copy()->addHours(1), 'returned_by_user_id' => $staff2Id, 'now' => $now],
            ['lot_id' => $lotSyr1Id, 'returned_at' => $rsn1At->copy()->addHours(2), 'returned_by_user_id' => $staff2Id, 'now' => $now],
        ]);

        // Reconciliation REC-UAT-001 (finalized)
        $rec1At = $rsn1CompletedAt->copy()->addHour();

        $rec1Id = $this->firstOrInsert('reconciliations', 'reconciliation_no', [
            'consignment_id'        => $con1Id,
            'return_session_id'     => $rsn1Id,
            'reconciliation_no'     => 'REC-UAT-001',
            'pic_user_id'           => $adminId,
            'status'                => 'finalized',
            'completed_at'          => $rec1At,
            'completed_by_user_id'  => $adminId,
            'created_at'            => $rec1At,
            'updated_at'            => $rec1At,
        ]);

        $this->seedReconciliationItems($rec1Id, [
            ['lot_id' => $lotGlv1Id, 'result' => 'returned_to_stock',  'remarks' => 'Returned in good condition', 'now' => $now],
            ['lot_id' => $lotGlv2Id, 'result' => 'returned_to_stock',  'remarks' => 'Returned in good condition', 'now' => $now],
            ['lot_id' => $lotSyr1Id, 'result' => 'confirmed_used',     'remarks' => 'Confirmed used by clinical team', 'now' => $now],
        ]);

        // Lot movements for reconciliation outcomes
        $this->seedLotMovement($lotGlv1Id, 'reconciliation_return', 'reconciliation', $rec1Id, 'supplied', 'available', 'consignment', $con1Id, 'warehouse', null, $rec1At, $adminId);
        $this->seedLotMovement($lotGlv2Id, 'reconciliation_return', 'reconciliation', $rec1Id, 'supplied', 'available', 'consignment', $con1Id, 'warehouse', null, $rec1At, $adminId);
        $this->seedLotMovement($lotSyr1Id, 'reconciliation_used',   'reconciliation', $rec1Id, 'supplied', 'used',      'consignment', $con1Id, null,        null, $rec1At, $adminId);

        // ------------------------------------------------------------------ //
        // STEP 7 · Consignment CON-UAT-002  (Klinik Prima Care · return in progress)
        // ------------------------------------------------------------------ //
        $con2At = $now->copy()->subDays(10);
        $con2ConfirmedAt = $con2At->copy()->addHours(2);

        $con2Id = $this->firstOrInsert('consignments', 'consignment_no', [
            'client_id'              => $clientKpc->id,
            'consignment_no'         => 'CON-UAT-002',
            'consignment_at'         => $con2At,
            'pic_user_id'            => $staff2Id,
            'status'                 => 'confirmed',
            'remarks'                => 'UAT scenario: active consignment with partial return in progress.',
            'confirmed_at'           => $con2ConfirmedAt,
            'confirmed_by_user_id'   => $adminId,
            'edited_after_confirmation' => false,
            'created_at'             => $con2At,
            'updated_at'             => $con2ConfirmedAt,
        ]);

        $this->seedConsignmentItems($con2Id, [
            ['lot_id' => $lotIns1Id, 'issued_at' => $con2ConfirmedAt, 'issued_by_user_id' => $staff2Id, 'now' => $now],
            ['lot_id' => $lotMsk1Id, 'issued_at' => $con2ConfirmedAt, 'issued_by_user_id' => $staff2Id, 'now' => $now],
        ]);

        $this->seedLotMovement($lotIns1Id, 'consignment_out', 'consignment', $con2Id, 'available', 'supplied', 'warehouse', null, 'consignment', $con2Id, $con2ConfirmedAt, $staff2Id);
        $this->seedLotMovement($lotMsk1Id, 'consignment_out', 'consignment', $con2Id, 'available', 'supplied', 'warehouse', null, 'consignment', $con2Id, $con2ConfirmedAt, $staff2Id);

        // Update lots' current_location_id now that we have the consignment IDs
        DB::table('lots')->where('id', $lotIns1Id)->update(['current_location_id' => $con2Id]);
        DB::table('lots')->where('id', $lotMsk1Id)->update(['current_location_id' => $con2Id]);

        // Return session RSN-UAT-002 (in progress — only one lot returned so far)
        $rsn2At = $now->copy()->subDays(3);

        $rsn2Id = $this->firstOrInsert('return_sessions', 'return_session_no', [
            'consignment_id'        => $con2Id,
            'return_session_no'     => 'RSN-UAT-002',
            'pic_user_id'           => $staff1Id,
            'status'                => 'in_progress',
            'remarks'               => 'Partial return — instrument kit scanned, mask still with client.',
            'started_at'            => $rsn2At,
            'completed_at'          => null,
            'completed_by_user_id'  => null,
            'created_at'            => $rsn2At,
            'updated_at'            => $rsn2At,
        ]);

        $this->seedReturnSessionItems($rsn2Id, [
            ['lot_id' => $lotIns1Id, 'returned_at' => $rsn2At->copy()->addHour(), 'returned_by_user_id' => $staff1Id, 'now' => $now],
        ]);

        // Reconciliation REC-UAT-002 (pending — not yet finalized)
        $rec2Id = $this->firstOrInsert('reconciliations', 'reconciliation_no', [
            'consignment_id'        => $con2Id,
            'return_session_id'     => $rsn2Id,
            'reconciliation_no'     => 'REC-UAT-002',
            'pic_user_id'           => $staff1Id,
            'status'                => 'pending',
            'completed_at'          => null,
            'completed_by_user_id'  => null,
            'created_at'            => $rsn2At,
            'updated_at'            => $rsn2At,
        ]);

        // ------------------------------------------------------------------ //
        // STEP 8 · Disposal DSP-UAT-001  (expired lot · completed)
        // ------------------------------------------------------------------ //
        $dspAt = $now->copy()->subDays(12);
        $dspCompletedAt = $dspAt->copy()->addHours(1);

        $dsp1Id = $this->firstOrInsert('disposals', 'disposal_no', [
            'disposal_no'           => 'DSP-UAT-001',
            'disposed_at'           => $dspAt,
            'pic_user_id'           => $staff1Id,
            'status'                => 'completed',
            'completed_at'          => $dspCompletedAt,
            'completed_by_user_id'  => $adminId,
            'created_at'            => $dspAt,
            'updated_at'            => $dspCompletedAt,
        ]);

        $this->seedDisposalItems($dsp1Id, [
            [
                'lot_id'            => $lotCat1Id,
                'disposal_category' => 'expired',
                'reason_text'       => 'Lot expired on 2024-01-01 — past usability threshold',
                'remarks'           => 'Identified during routine expiry audit',
                'now'               => $now,
            ],
        ]);

        $this->seedLotMovement($lotCat1Id, 'disposal', 'disposal', $dsp1Id, 'available', 'disposed', 'warehouse', null, null, null, $dspCompletedAt, $adminId);

        // ------------------------------------------------------------------ //
        // STEP 9 · Holding  LOT-INS-U002  (damaged packaging)
        // ------------------------------------------------------------------ //
        $holdAt = $now->copy()->subDays(17);

        $holdExists = DB::table('lot_holdings')->where('lot_id', $lotIns2Id)->exists();
        if (! $holdExists) {
            DB::table('lot_holdings')->insert([
                'lot_id'                => $lotIns2Id,
                'holding_reason'        => 'Damaged outer packaging detected on receipt; pending supplier investigation.',
                'assigned_at'           => $holdAt,
                'assigned_by_user_id'   => $staff2Id,
                'released_at'           => null,
                'released_by_user_id'   => null,
                'corrected_lot_number'  => null,
                'resolution_reason'     => null,
                'remarks'               => 'Awaiting replacement or RMA from SteriPro Asia.',
                'created_at'            => $holdAt,
                'updated_at'            => $holdAt,
            ]);
        }

        $this->seedLotMovement($lotIns2Id, 'hold', 'lot_holding', null, 'available', 'holding', 'warehouse', null, 'holding', null, $holdAt, $staff2Id);
    }

    // =========================================================================
    // Helper methods
    // =========================================================================

    /** Insert a row if a unique key column does not already exist. Returns the row id. */
    private function firstOrInsert(string $table, string $uniqueColumn, array $data): int
    {
        $existing = DB::table($table)->where($uniqueColumn, $data[$uniqueColumn])->first();
        if ($existing) {
            return (int) $existing->id;
        }

        return (int) DB::table($table)->insertGetId($data);
    }

    /** Insert a Lot if lot_number does not exist, return its id. */
    private function seedLot(array $params): int
    {
        $existing = DB::table('lots')->where('lot_number', $params['lot_number'])->first();
        if ($existing) {
            return (int) $existing->id;
        }

        return (int) DB::table('lots')->insertGetId([
            'product_id'            => $params['product_id'],
            'instrument_set_id'     => null,
            'supplier_id'           => $params['supplier_id'],
            'lot_number'            => $params['lot_number'],
            'original_lot_number'   => null,
            'is_system_generated_lot' => false,
            'supplier_batch_code'   => $params['supplier_batch_code'],
            'expiry_date'           => $params['expiry_date'],
            'status'                => $params['status'],
            'current_location_type' => $params['current_location_type'],
            'current_location_id'   => $params['current_location_id'],
            'remarks'               => null,
            'received_at'           => $params['received_at'],
            'created_at'            => $params['now'],
            'updated_at'            => $params['now'],
        ]);
    }

    /** Insert stock_in_items for a session (skips if lot already linked). */
    private function seedStockInItems(int $stockInId, array $items): void
    {
        foreach ($items as $item) {
            $exists = DB::table('stock_in_items')
                ->where('stock_in_id', $stockInId)
                ->where('scanned_lot_number', $item['scanned_lot_number'])
                ->exists();

            if (! $exists) {
                DB::table('stock_in_items')->insert([
                    'stock_in_id'           => $stockInId,
                    'product_id'            => $item['product_id'],
                    'lot_id'                => $item['lot_id'],
                    'scanned_lot_number'    => $item['scanned_lot_number'],
                    'supplier_batch_code'   => $item['supplier_batch_code'],
                    'expiry_date'           => $item['expiry_date'],
                    'lot_entry_mode'        => 'scan',
                    'expiry_entry_mode'     => 'scan',
                    'missing_lot_flag'      => false,
                    'source_barcode'        => null,
                    'entry_override_reason' => null,
                    'remarks'               => null,
                    'created_at'            => $item['now'],
                    'updated_at'            => $item['now'],
                ]);
            }
        }
    }

    /** Insert a receive (stock-in) lot_movement for each lot id. */
    private function seedReceiveLotMovements(array $lotIds, int $stockInId, Carbon $performedAt, int $performedByUserId): void
    {
        foreach ($lotIds as $lotId) {
            $exists = DB::table('lot_movements')
                ->where('lot_id', $lotId)
                ->where('movement_type', 'stock_in')
                ->where('reference_type', 'stock_in')
                ->where('reference_id', $stockInId)
                ->exists();

            if (! $exists) {
                DB::table('lot_movements')->insert([
                    'lot_id'                => $lotId,
                    'movement_type'         => 'stock_in',
                    'reference_type'        => 'stock_in',
                    'reference_id'          => $stockInId,
                    'from_status'           => null,
                    'to_status'             => 'available',
                    'from_location_type'    => null,
                    'from_location_id'      => null,
                    'to_location_type'      => 'warehouse',
                    'to_location_id'        => null,
                    'performed_at'          => $performedAt,
                    'performed_by_user_id'  => $performedByUserId,
                    'remarks'               => null,
                    'created_at'            => $performedAt,
                ]);
            }
        }
    }

    /** Insert QrLabel + QrPrintJob (printed) for each lot. */
    private function seedQrLabelsAndPrintJobs(
        array $lotIds,
        array $lotNumbers,
        array $productRefs,
        array $batchCodes,
        array $expiryDates,
        Carbon $generatedAt,
        int $generatedByUserId
    ): void {
        foreach ($lotIds as $index => $lotId) {
            $labelExists = DB::table('qr_labels')->where('lot_id', $lotId)->first();

            if (! $labelExists) {
                $payload = json_encode([
                    'lot_number'   => $lotNumbers[$index],
                    'product_ref'  => $productRefs[$index],
                    'batch_code'   => $batchCodes[$index],
                    'expiry_date'  => $expiryDates[$index],
                ]);

                $labelId = (int) DB::table('qr_labels')->insertGetId([
                    'lot_id'                => $lotId,
                    'qr_payload'            => $payload,
                    'generated_at'          => $generatedAt,
                    'generated_by_user_id'  => $generatedByUserId,
                    'created_at'            => $generatedAt,
                    'updated_at'            => $generatedAt,
                ]);
            } else {
                $labelId = (int) $labelExists->id;
            }

            $printExists = DB::table('qr_print_jobs')->where('lot_id', $lotId)->where('action_type', 'print')->exists();
            if (! $printExists) {
                DB::table('qr_print_jobs')->insert([
                    'lot_id'                 => $lotId,
                    'qr_label_id'            => $labelId,
                    'action_type'            => 'print',
                    'reprint_reason'         => null,
                    'status'                 => 'printed',
                    'printer_name'           => 'UAT-Printer-01',
                    'device_id'              => 'DEV-UAT-001',
                    'tspl_payload'           => null,
                    'error_message'          => null,
                    'requested_by_user_id'   => $generatedByUserId,
                    'requested_at'           => $generatedAt,
                    'printed_at'             => $generatedAt->copy()->addMinutes(1),
                    'failed_at'              => null,
                    'created_at'             => $generatedAt,
                    'updated_at'             => $generatedAt->copy()->addMinutes(1),
                ]);
            }
        }
    }

    /** Insert consignment_items (skips duplicate lot per consignment). */
    private function seedConsignmentItems(int $consignmentId, array $items): void
    {
        foreach ($items as $item) {
            $exists = DB::table('consignment_items')
                ->where('consignment_id', $consignmentId)
                ->where('lot_id', $item['lot_id'])
                ->exists();

            if (! $exists) {
                DB::table('consignment_items')->insert([
                    'consignment_id'        => $consignmentId,
                    'lot_id'                => $item['lot_id'],
                    'issued_at'             => $item['issued_at'],
                    'issued_by_user_id'     => $item['issued_by_user_id'],
                    'remarks'               => null,
                    'created_at'            => $item['now'],
                    'updated_at'            => $item['now'],
                ]);
            }
        }
    }

    /** Insert return_session_items (skips duplicate lot per session). */
    private function seedReturnSessionItems(int $returnSessionId, array $items): void
    {
        foreach ($items as $item) {
            $exists = DB::table('return_session_items')
                ->where('return_session_id', $returnSessionId)
                ->where('lot_id', $item['lot_id'])
                ->exists();

            if (! $exists) {
                DB::table('return_session_items')->insert([
                    'return_session_id'     => $returnSessionId,
                    'lot_id'                => $item['lot_id'],
                    'returned_at'           => $item['returned_at'],
                    'returned_by_user_id'   => $item['returned_by_user_id'],
                    'source_qr_payload'     => null,
                    'remarks'               => null,
                    'created_at'            => $item['now'],
                    'updated_at'            => $item['now'],
                ]);
            }
        }
    }

    /** Insert reconciliation_items (skips duplicate lot per reconciliation). */
    private function seedReconciliationItems(int $reconciliationId, array $items): void
    {
        foreach ($items as $item) {
            $exists = DB::table('reconciliation_items')
                ->where('reconciliation_id', $reconciliationId)
                ->where('lot_id', $item['lot_id'])
                ->exists();

            if (! $exists) {
                DB::table('reconciliation_items')->insert([
                    'reconciliation_id' => $reconciliationId,
                    'lot_id'            => $item['lot_id'],
                    'result'            => $item['result'],
                    'remarks'           => $item['remarks'],
                    'created_at'        => $item['now'],
                    'updated_at'        => $item['now'],
                ]);
            }
        }
    }

    /** Insert disposal_items (skips duplicate lot per disposal). */
    private function seedDisposalItems(int $disposalId, array $items): void
    {
        foreach ($items as $item) {
            $exists = DB::table('disposal_items')
                ->where('disposal_id', $disposalId)
                ->where('lot_id', $item['lot_id'])
                ->exists();

            if (! $exists) {
                DB::table('disposal_items')->insert([
                    'disposal_id'       => $disposalId,
                    'lot_id'            => $item['lot_id'],
                    'disposal_category' => $item['disposal_category'],
                    'reason_text'       => $item['reason_text'],
                    'remarks'           => $item['remarks'],
                    'created_at'        => $item['now'],
                    'updated_at'        => $item['now'],
                ]);
            }
        }
    }

    /** Insert a single lot_movement row (skips if same lot+type+ref already exists). */
    private function seedLotMovement(
        int     $lotId,
        string  $movementType,
        ?string $referenceType,
        ?int    $referenceId,
        ?string $fromStatus,
        ?string $toStatus,
        ?string $fromLocationType,
        ?int    $fromLocationId,
        ?string $toLocationType,
        ?int    $toLocationId,
        Carbon  $performedAt,
        int     $performedByUserId
    ): void {
        $exists = DB::table('lot_movements')
            ->where('lot_id', $lotId)
            ->where('movement_type', $movementType)
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->exists();

        if (! $exists) {
            DB::table('lot_movements')->insert([
                'lot_id'                => $lotId,
                'movement_type'         => $movementType,
                'reference_type'        => $referenceType,
                'reference_id'          => $referenceId,
                'from_status'           => $fromStatus,
                'to_status'             => $toStatus,
                'from_location_type'    => $fromLocationType,
                'from_location_id'      => $fromLocationId,
                'to_location_type'      => $toLocationType,
                'to_location_id'        => $toLocationId,
                'performed_at'          => $performedAt,
                'performed_by_user_id'  => $performedByUserId,
                'remarks'               => null,
                'created_at'            => $performedAt,
            ]);
        }
    }
}
