<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\InstrumentSet;
use Illuminate\Database\Seeder;

class SampleMasterDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $suppliers = [
            ['supplier_name' => 'Medline Nusantara', 'phone' => '+62-21-555-0101', 'email' => 'ops@medline-nusantara.id', 'address' => 'Jakarta, Indonesia', 'is_active' => true],
            ['supplier_name' => 'SteriPro Labs', 'phone' => '+62-21-555-0102', 'email' => 'sales@steriprolabs.id', 'address' => 'Bandung, Indonesia', 'is_active' => true],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::query()->firstOrCreate(['supplier_name' => $supplier['supplier_name']], $supplier);
        }

        $clients = [
            ['client_name' => 'RS Sehat Sentosa', 'client_type' => 'hospital', 'phone' => '+62-21-555-0201', 'email' => 'procurement@rssehatsentosa.id', 'address' => 'Jakarta, Indonesia', 'is_active' => true],
            ['client_name' => 'Klinik Prima Care', 'client_type' => 'clinic', 'phone' => '+62-21-555-0202', 'email' => 'admin@primacare.id', 'address' => 'Depok, Indonesia', 'is_active' => true],
        ];

        foreach ($clients as $client) {
            Client::query()->firstOrCreate(['client_name' => $client['client_name'], 'client_type' => $client['client_type']], $client);
        }

        $products = [
            ['ref_num' => 'P-GLV-001', 'product_name' => 'Surgical Gloves', 'product_type' => 'consumable', 'category' => 'PPE', 'uom' => 'box', 'requires_expiry' => true, 'requires_lot' => true, 'is_active' => true],
            ['ref_num' => 'P-SYR-001', 'product_name' => 'Sterile Syringe 5ml', 'product_type' => 'consumable', 'category' => 'Injection', 'uom' => 'pcs', 'requires_expiry' => true, 'requires_lot' => true, 'is_active' => true],
            ['ref_num' => 'P-INS-001', 'product_name' => 'Basic Instrument Kit', 'product_type' => 'instrument', 'category' => 'Surgical Set', 'uom' => 'set', 'requires_expiry' => false, 'requires_lot' => true, 'is_active' => true],
        ];

        foreach ($products as $product) {
            Product::query()->firstOrCreate(['ref_num' => $product['ref_num']], $product);
        }

        $instrumentSets = [
            ['set_code' => 'SET-ORTHO-01', 'set_name' => 'Orthopedic Basic Set', 'description' => 'Standard set for minor orthopedic procedures', 'is_active' => true],
            ['set_code' => 'SET-GEN-01', 'set_name' => 'General Surgery Starter Set', 'description' => 'Starter instrument set for general surgery', 'is_active' => true],
        ];

        foreach ($instrumentSets as $instrumentSet) {
            InstrumentSet::query()->firstOrCreate(['set_code' => $instrumentSet['set_code']], $instrumentSet);
        }
    }
}
