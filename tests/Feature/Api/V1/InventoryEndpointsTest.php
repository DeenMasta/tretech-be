<?php

namespace Tests\Feature\Api\V1;

use App\Models\Lot;
use App\Models\LotMovement;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventoryEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('logging.channels.api', [
            'driver' => 'single',
            'path' => storage_path('logs/api-test.log'),
            'level' => 'debug',
        ]);

        $this->seed(PermissionSeeder::class);
    }

    public function test_guest_cannot_access_inventory_units_index(): void
    {
        $response = $this->getJson('/api/v1/inventory-units');

        $response->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_user_without_stock_in_view_permission_cannot_access_inventory_endpoints(): void
    {
        $user = $this->makeUserWithPermissions([]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/inventory-units')
            ->assertStatus(403)
            ->assertJsonPath('success', false);

        $this->getJson('/api/v1/inventory-ledger')
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_inventory_units_index_supports_core_filters(): void
    {
        $user = $this->makeUserWithPermissions(['stock_in.view']);
        Sanctum::actingAs($user);

        $supplierA = $this->createSupplier('Supplier A');
        $supplierB = $this->createSupplier('Supplier B');
        $productA = $this->createProduct('REF-AAA', 'Alpha Product');
        $productB = $this->createProduct('REF-BBB', 'Beta Product');

        $lot1 = $this->createLot($productA, $supplierA, 'LOT-A1', 'available');
        $this->createLot($productA, $supplierB, 'LOT-A2', 'supplied');
        $lot3 = $this->createLot($productB, $supplierA, 'LOT-B1', 'available');

        $this->getJson('/api/v1/inventory-units?status=available')
            ->assertOk()
            ->assertJsonPath('pagination.total', 2);

        $this->getJson('/api/v1/inventory-units?supplier_id=' . $supplierB->id)
            ->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('data.0.lot_number', 'LOT-A2');

        $this->getJson('/api/v1/inventory-units?product_id=' . $productB->id)
            ->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('data.0.lot_number', $lot3->lot_number);

        $this->getJson('/api/v1/inventory-units?search=REF-AAA')
            ->assertOk()
            ->assertJsonPath('pagination.total', 2);

        $this->getJson('/api/v1/inventory-units?search=' . $lot1->lot_number)
            ->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('data.0.id', $lot1->id);
    }

    public function test_inventory_lookup_by_lot_returns_data_and_not_found(): void
    {
        $user = $this->makeUserWithPermissions(['stock_in.view']);
        Sanctum::actingAs($user);

        $supplier = $this->createSupplier('Supplier Lookup');
        $product = $this->createProduct('REF-LKP', 'Lookup Product');
        $lot = $this->createLot($product, $supplier, 'LOT-LOOKUP-1', 'available');

        $this->getJson('/api/v1/inventory-units/lookup/by-lot/' . $lot->lot_number)
            ->assertOk()
            ->assertJsonPath('data.id', $lot->id)
            ->assertJsonPath('data.product.ref_num', 'REF-LKP');

        $this->getJson('/api/v1/inventory-units/lookup/by-lot/UNKNOWN-LOT')
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_inventory_lookup_by_ref_returns_matching_lots(): void
    {
        $user = $this->makeUserWithPermissions(['stock_in.view']);
        Sanctum::actingAs($user);

        $supplier = $this->createSupplier('Supplier Ref');
        $targetProduct = $this->createProduct('REF-TARGET', 'Target Product');
        $otherProduct = $this->createProduct('REF-OTHER', 'Other Product');

        $this->createLot($targetProduct, $supplier, 'LOT-T1', 'available');
        $this->createLot($targetProduct, $supplier, 'LOT-T2', 'supplied');
        $this->createLot($otherProduct, $supplier, 'LOT-O1', 'available');

        $this->getJson('/api/v1/inventory-units/lookup/by-ref/REF-TARGET')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/v1/inventory-units/lookup/by-ref/REF-NOT-FOUND')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(0, 'data');
    }

    public function test_inventory_show_returns_lot_and_not_found(): void
    {
        $user = $this->makeUserWithPermissions(['stock_in.view']);
        Sanctum::actingAs($user);

        $supplier = $this->createSupplier('Supplier Show');
        $product = $this->createProduct('REF-SHOW', 'Show Product');
        $lot = $this->createLot($product, $supplier, 'LOT-SHOW-1', 'available');

        $this->getJson('/api/v1/inventory-units/' . $lot->id)
            ->assertOk()
            ->assertJsonPath('data.id', $lot->id)
            ->assertJsonPath('data.supplier.supplier_name', 'Supplier Show');

        $this->getJson('/api/v1/inventory-units/999999')
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_inventory_ledger_supports_lot_movement_type_and_date_filters(): void
    {
        $user = $this->makeUserWithPermissions(['stock_in.view']);
        Sanctum::actingAs($user);

        $supplier = $this->createSupplier('Supplier Ledger');
        $product = $this->createProduct('REF-LEDGER', 'Ledger Product');
        $lot1 = $this->createLot($product, $supplier, 'LOT-LEDGER-1', 'available');
        $lot2 = $this->createLot($product, $supplier, 'LOT-LEDGER-2', 'supplied');

        LotMovement::query()->create([
            'lot_id' => $lot1->id,
            'movement_type' => 'stock_in',
            'reference_type' => 'StockIn',
            'reference_id' => 1,
            'from_status' => null,
            'to_status' => 'available',
            'from_location_type' => null,
            'from_location_id' => null,
            'to_location_type' => 'warehouse',
            'to_location_id' => null,
            'performed_at' => Carbon::parse('2026-04-01 09:00:00'),
            'performed_by_user_id' => $user->id,
            'remarks' => 'stock in movement',
        ]);

        LotMovement::query()->create([
            'lot_id' => $lot2->id,
            'movement_type' => 'consigned',
            'reference_type' => 'Consignment',
            'reference_id' => 2,
            'from_status' => 'available',
            'to_status' => 'supplied',
            'from_location_type' => 'warehouse',
            'from_location_id' => null,
            'to_location_type' => 'client',
            'to_location_id' => 99,
            'performed_at' => Carbon::parse('2026-04-05 15:00:00'),
            'performed_by_user_id' => $user->id,
            'remarks' => 'consigned movement',
        ]);

        $this->getJson('/api/v1/inventory-ledger?lot_number=' . $lot1->lot_number)
            ->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('data.0.lot.lot_number', $lot1->lot_number);

        $this->getJson('/api/v1/inventory-ledger?movement_type=consigned')
            ->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('data.0.movement_type', 'consigned');

        $this->getJson('/api/v1/inventory-ledger?from_date=2026-04-02&to_date=2026-04-06')
            ->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('data.0.lot.lot_number', $lot2->lot_number);

        $this->getJson('/api/v1/inventory-ledger?lot_id=' . $lot2->id)
            ->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('data.0.lot_id', $lot2->id);
    }

    /**
     * @param array<int, string> $permissionCodes
     */
    private function makeUserWithPermissions(array $permissionCodes): User
    {
        $role = Role::query()->create([
            'role_code' => 'test_role_' . str()->lower(str()->random(10)),
            'role_name' => 'Test Role ' . str()->random(5),
        ]);

        if ($permissionCodes !== []) {
            $permissionIds = Permission::query()
                ->whereIn('permission_code', $permissionCodes)
                ->pluck('id')
                ->all();

            $role->permissions()->sync($permissionIds);
        }

        return User::query()->create([
            'role_id' => $role->id,
            'full_name' => 'Inventory Tester',
            'email' => 'inventory_tester_' . str()->lower(str()->random(6)) . '@example.test',
            'password_hash' => 'Password123!',
            'is_active' => true,
        ]);
    }

    private function createSupplier(string $name): Supplier
    {
        return Supplier::query()->create([
            'supplier_name' => $name,
            'phone' => '123456789',
            'email' => str()->slug($name) . '@example.test',
            'address' => 'Test Address',
            'is_active' => true,
        ]);
    }

    private function createProduct(string $refNum, string $name): Product
    {
        return Product::query()->create([
            'ref_num' => $refNum,
            'product_name' => $name,
            'product_type' => 'consumable',
            'category' => 'test',
            'uom' => 'pcs',
            'requires_expiry' => true,
            'requires_lot' => true,
            'is_active' => true,
        ]);
    }

    private function createLot(Product $product, Supplier $supplier, string $lotNumber, string $status): Lot
    {
        return Lot::query()->create([
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'lot_number' => $lotNumber,
            'manufacturing_date' => '2026-01-01',
            'expiry_date' => '2027-01-01',
            'status' => $status,
            'current_location_type' => 'warehouse',
            'current_location_id' => null,
            'received_at' => now(),
        ]);
    }
}
