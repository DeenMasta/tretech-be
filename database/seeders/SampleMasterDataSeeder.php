<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\InstrumentSet;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SampleMasterDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $suppliers = [
            [
                'supplier_name' => 'Tremed Surgical Solution Sdn Bhd',
                'pic_name' => 'Zikri Rahman',
                'phone' => '+60-3-7887-6398',
                'email' => 'sales@tremedsurgical.com.my',
                'address' => 'No. 6-1, Block A, Zenith Corporate Park, Jalan SS 7/26, Kelana Jaya, 47301 Petaling Jaya, Selangor',
                'is_active' => true,
            ],
            [
                'supplier_name' => 'Medikraft Orthopaedic Supplies Sdn Bhd',
                'pic_name' => 'Ahmad Faizal',
                'phone' => '+60-3-5512-3456',
                'email' => 'sales@medikraft.com.my',
                'address' => 'No. 18, Jalan Anggerik Vanilla 31/93, Kota Kemuning Industrial Park, 40460 Shah Alam, Selangor',
                'is_active' => true,
            ],
            [
                'supplier_name' => 'Borneo Meditech Distribution Sdn Bhd',
                'pic_name' => 'Lim Wei Jie',
                'phone' => '+60-3-8068-2190',
                'email' => 'orders@borneomeditech.com.my',
                'address' => 'Lot 12, Jalan TPP 5/17, Taman Perindustrian Puchong, 47100 Puchong, Selangor',
                'is_active' => true,
            ],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::query()->firstOrCreate(['supplier_name' => $supplier['supplier_name']], $supplier);
        }

        $clients = [
            [
                'client_name' => 'Hospital Kuala Lumpur',
                'client_type' => 'hospital',
                'phone' => '+60-3-2615-5555',
                'email' => 'procurement.hkl@tretech-demo.my',
                'address' => 'Jalan Pahang, 50586 Kuala Lumpur, Wilayah Persekutuan Kuala Lumpur',
                'is_active' => true,
            ],
            [
                'client_name' => 'Hospital Selayang',
                'client_type' => 'hospital',
                'phone' => '+60-3-6126-3333',
                'email' => 'stor.perubatan.selayang@tretech-demo.my',
                'address' => 'Lebuhraya Selayang-Kepong, 68100 Batu Caves, Selangor',
                'is_active' => true,
            ],
            [
                'client_name' => 'Pusat Perubatan Universiti Malaya',
                'client_type' => 'hospital',
                'phone' => '+60-3-7949-4422',
                'email' => 'unit.perolehan.ppum@tretech-demo.my',
                'address' => 'Lembah Pantai, 59100 Kuala Lumpur, Wilayah Persekutuan Kuala Lumpur',
                'is_active' => true,
            ],
        ];

        foreach ($clients as $client) {
            Client::query()->firstOrCreate(['client_name' => $client['client_name'], 'client_type' => $client['client_type']], $client);
        }

        $products = [
            // Implants
            [
                'ref_num' => '115011500', // Used by UatSeeder
                'product_name' => 'Titanium Bone Screw 3.5mm x 14mm',
                'product_type' => 'implant',
                'category' => 'Orthopedic Implant',
                'uom' => 'pcs',
                'requires_expiry' => false,
                'requires_lot' => true,
                'is_active' => true,
            ],
            [
                'ref_num' => 'IMP-001',
                'product_name' => 'Locking Bone Plate, 6 holes',
                'product_type' => 'implant',
                'category' => 'Orthopedic Implant',
                'uom' => 'pcs',
                'requires_expiry' => false,
                'requires_lot' => true,
                'is_active' => true,
            ],
            [
                'ref_num' => 'IMP-002',
                'product_name' => 'Polyethylene Tibial Insert, Size M',
                'product_type' => 'implant',
                'category' => 'Joint Replacement',
                'uom' => 'pcs',
                'requires_expiry' => true, // Polyethylene often has an expiry
                'requires_lot' => true,
                'is_active' => true,
            ],
            [
                'ref_num' => 'IMP-003',
                'product_name' => 'Femoral Knee Prosthesis, Size L',
                'product_type' => 'implant',
                'category' => 'Joint Replacement',
                'uom' => 'pcs',
                'requires_expiry' => false,
                'requires_lot' => true,
                'is_active' => true,
            ],

            // Consumables
            [
                'ref_num' => '115012300', // Used by UatSeeder
                'product_name' => 'Sterile Surgical Gloves, Size 7.5',
                'product_type' => 'consumable',
                'category' => 'PPE',
                'uom' => 'box',
                'requires_expiry' => true,
                'requires_lot' => true,
                'is_active' => true,
            ],
            [
                'ref_num' => '115011400', // Used by UatSeeder
                'product_name' => '10ml Luer Lock Syringe',
                'product_type' => 'consumable',
                'category' => 'General Medical',
                'uom' => 'box',
                'requires_expiry' => true,
                'requires_lot' => true,
                'is_active' => true,
            ],
            [
                'ref_num' => '115010300', // Used by UatSeeder
                'product_name' => 'N95 Surgical Mask',
                'product_type' => 'consumable',
                'category' => 'PPE',
                'uom' => 'box',
                'requires_expiry' => true,
                'requires_lot' => true,
                'is_active' => true,
            ],
            [
                'ref_num' => '115011600', // Used by UatSeeder
                'product_name' => 'Intravenous Catheter 20G',
                'product_type' => 'consumable',
                'category' => 'General Medical',
                'uom' => 'pcs',
                'requires_expiry' => true,
                'requires_lot' => true,
                'is_active' => true,
            ],
            [
                'ref_num' => 'CON-001',
                'product_name' => 'Normal Saline 0.9%, 500ml',
                'product_type' => 'consumable',
                'category' => 'Fluids',
                'uom' => 'btl',
                'requires_expiry' => true,
                'requires_lot' => true,
                'is_active' => true,
            ],
            [
                'ref_num' => 'CON-002',
                'product_name' => 'Povidone-Iodine Solution 10%',
                'product_type' => 'consumable',
                'category' => 'Antiseptics',
                'uom' => 'btl',
                'requires_expiry' => true,
                'requires_lot' => true,
                'is_active' => true,
            ],
            [
                'ref_num' => 'CON-003',
                'product_name' => 'Sterile Gauze Swabs 10x10cm',
                'product_type' => 'consumable',
                'category' => 'Dressings',
                'uom' => 'pkt',
                'requires_expiry' => true,
                'requires_lot' => true,
                'is_active' => true,
            ],

            // Loose Instruments (not in a set)
            [
                'ref_num' => 'INS-001',
                'product_name' => 'Reusable Scalpel Handle No.3',
                'product_type' => 'instrument',
                'category' => 'General Surgery',
                'uom' => 'pcs',
                'requires_expiry' => false,
                'requires_lot' => true,
                'is_active' => true,
            ],
        ];

        foreach ($products as $product) {
            Product::query()->firstOrCreate(['ref_num' => $product['ref_num']], $product);
        }

        $instrumentSets = [
            [
                'set_code' => 'SET-ORTHO-ACL-01',
                'set_name' => 'ACL Reconstruction Instrument Set',
                'description' => 'Used for arthroscopic anterior cruciate ligament reconstruction, tunnel preparation, graft sizing, and fixation support.',
                'is_active' => true,
                'instruments' => [
                    [
                        'code' => 'INST-ACL-001',
                        'name' => 'Hamstring Tendon Stripper, Closed End, 7mm',
                        'quantity' => 1,
                        'sort_order' => 1,
                        'remarks' => 'For semitendinosus/gracilis tendon harvesting',
                    ],
                    [
                        'code' => 'INST-ACL-002',
                        'name' => 'Graft Sizing Block',
                        'quantity' => 1,
                        'sort_order' => 2,
                        'remarks' => 'Sizing range 6mm to 12mm',
                    ],
                    [
                        'code' => 'INST-ACL-003',
                        'name' => 'Tibial ACL Drill Guide, Pin Tip',
                        'quantity' => 1,
                        'sort_order' => 3,
                        'remarks' => 'For tibial tunnel targeting',
                    ],

                ],
            ],
            [
                'set_code' => 'SET-ORTHO-BONE-02',
                'set_name' => 'Basic Bone Plating Instrument Set',
                'description' => 'Used for open reduction and internal fixation of simple fractures requiring plates and screws.',
                'is_active' => true,
                'instruments' => [
                    [
                        'code' => 'INST-BONE-001',
                        'name' => 'Bone Holding Forceps, Large',
                        'quantity' => 2,
                        'sort_order' => 1,
                        'remarks' => 'Serrated jaw',
                    ],
                    [
                        'code' => 'INST-BONE-002',
                        'name' => 'Reduction Forceps with Points',
                        'quantity' => 2,
                        'sort_order' => 2,
                        'remarks' => null,
                    ],
                    [
                        'code' => 'INST-BONE-003',
                        'name' => 'Periosteal Elevator, 15mm',
                        'quantity' => 1,
                        'sort_order' => 3,
                        'remarks' => 'For soft tissue elevation',
                    ],
                    [
                        'code' => 'INST-BONE-004',
                        'name' => 'Hohmann Retractor, Small',
                        'quantity' => 2,
                        'sort_order' => 4,
                        'remarks' => null,
                    ],
                ],
            ],
            [
                'set_code' => 'SET-GENSURG-MINOR-01',
                'set_name' => 'Minor Surgery Tray',
                'description' => 'Used for minor procedures such as wound exploration, incision and drainage, biopsy, and simple soft tissue repair.',
                'is_active' => true,
                'instruments' => [
                    [
                        'code' => 'INST-MIN-001',
                        'name' => 'Scalpel Handle No. 3',
                        'quantity' => 1,
                        'sort_order' => 1,
                        'remarks' => 'For blades No. 10, 11, 15',
                    ],
                    [
                        'code' => 'INST-MIN-002',
                        'name' => 'Mayo Scissors, Straight, 15cm',
                        'quantity' => 1,
                        'sort_order' => 2,
                        'remarks' => null,
                    ],
                ],
            ],
        ];

        foreach ($instrumentSets as $setData) {
            $instruments = $setData['instruments'] ?? [];
            unset($setData['instruments']);

            $set = InstrumentSet::query()->firstOrCreate(['set_code' => $setData['set_code']], $setData);

            foreach ($instruments as $instrumentData) {
                // 1. Ensure the product exists
                $refNum = $instrumentData['code'] ?? ('INST-AUTO-'.strtoupper(substr(md5($instrumentData['name']), 0, 6)));

                $product = Product::query()->firstOrCreate(
                    ['ref_num' => $refNum],
                    [
                        'product_name' => $instrumentData['name'],
                        'product_type' => 'instrument',
                        'category' => 'Instrument Set Component',
                        'uom' => 'pcs',
                        'requires_expiry' => false,
                        'requires_lot' => true,
                        'is_active' => true,
                    ]
                );

                // Existing component products must also follow the lot-tracking
                // rule when this idempotent seed is run again.
                if (! $product->requires_lot) {
                    $product->update(['requires_lot' => true]);
                }

                // 2. Link it in instrument_set_items
                DB::table('instrument_set_items')->updateOrInsert(
                    [
                        'instrument_set_id' => $set->id,
                        'product_id' => $product->id,
                    ],
                    [
                        'quantity' => $instrumentData['quantity'] ?? 1,
                        'sort_order' => $instrumentData['sort_order'] ?? 0,
                        'remarks' => $instrumentData['remarks'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }
}
