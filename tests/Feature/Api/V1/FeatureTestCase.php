<?php

namespace Tests\Feature\Api\V1;

use App\Models\Client;
use App\Models\Lot;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class FeatureTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('logging.channels.api', [
            'driver' => 'single',
            'path'   => storage_path('logs/api-test.log'),
            'level'  => 'debug',
        ]);

        $this->seed(PermissionSeeder::class);
    }

    protected function makeUserWithPermissions(array $permissionCodes): User
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
            'role_id'       => $role->id,
            'full_name'     => 'Feature Tester',
            'email'         => 'tester_' . str()->lower(str()->random(8)) . '@example.test',
            'password_hash' => 'Password123!',
            'is_active'     => true,
        ]);
    }

    protected function createProduct(?string $refNum = null, string $productType = 'consumable'): Product
    {
        return Product::query()->create([
            'ref_num'         => $refNum ?? 'PROD-' . str()->upper(str()->random(6)),
            'product_name'    => 'Test Product ' . str()->random(4),
            'product_type'    => $productType,
            'category'        => 'general',
            'uom'             => 'pcs',
            'requires_expiry' => true,
            'requires_lot'    => true,
            'is_active'       => true,
        ]);
    }

    protected function createSupplier(): Supplier
    {
        return Supplier::query()->create([
            'supplier_name' => 'Test Supplier ' . str()->random(5),
            'phone'         => '0123456789',
            'email'         => 'supplier_' . str()->lower(str()->random(6)) . '@example.test',
            'is_active'     => true,
        ]);
    }

    protected function createClient(): Client
    {
        return Client::query()->create([
            'client_name' => 'Test Client ' . str()->random(5),
            'client_type' => 'hospital',
            'phone'       => '0123456789',
            'email'       => 'client_' . str()->lower(str()->random(6)) . '@example.test',
            'is_active'   => true,
        ]);
    }

    protected function createLot(Product $product, Supplier $supplier, string $status = 'available', ?string $lotNumber = null): Lot
    {
        return Lot::query()->create([
            'product_id'            => $product->id,
            'supplier_id'           => $supplier->id,
            'lot_number'            => $lotNumber ?? 'LOT-' . str()->upper(str()->random(6)),
            'manufacturing_date'   => '2026-01-01',
            'expiry_date'           => '2027-06-30',
            'status'                => $status,
            'current_location_type' => 'warehouse',
            'current_location_id'   => null,
            'received_at'           => now(),
        ]);
    }
}
