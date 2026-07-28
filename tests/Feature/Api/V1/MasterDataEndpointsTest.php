<?php

namespace Tests\Feature\Api\V1;

use App\Models\AuditLog;
use App\Models\InstrumentSet;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MasterDataEndpointsTest extends TestCase
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

    public function test_guest_cannot_access_master_data_products_index(): void
    {
        $response = $this->getJson('/api/v1/master-data/products');

        $response->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_user_without_permission_cannot_access_products_index(): void
    {
        $user = $this->makeUserWithPermissions([]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/master-data/products');

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_user_with_products_view_permission_can_access_products_index(): void
    {
        Product::query()->create([
            'ref_num' => 'PRD-001',
            'product_name' => 'Sterile Gloves',
            'product_type' => 'consumable',
            'category' => 'PPE',
            'uom' => 'box',
            'requires_expiry' => true,
            'requires_lot' => true,
            'is_active' => true,
        ]);

        $user = $this->makeUserWithPermissions(['products.view']);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/master-data/products?search=Sterile');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('data.0.ref_num', 'PRD-001');
    }

    public function test_logistic_staff_can_access_products_index_for_stock_in(): void
    {
        Product::query()->create([
            'ref_num' => 'PRD-LOG-001',
            'product_name' => 'Logistic Stock Item',
            'product_type' => 'consumable',
            'category' => 'PPE',
            'uom' => 'box',
            'requires_expiry' => true,
            'requires_lot' => true,
            'is_active' => true,
        ]);

        $staffRole = Role::query()->where('role_code', 'logistic_staff')->firstOrFail();
        $user = User::factory()->create(['role_id' => $staffRole->id]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/master-data/products?search=Logistic')
            ->assertOk()
            ->assertJsonPath('data.0.ref_num', 'PRD-LOG-001');
    }

    public function test_logistic_staff_has_standard_operations_permissions_but_no_sensitive_or_audit_access(): void
    {
        $staffRole = Role::query()->where('role_code', 'logistic_staff')->firstOrFail();

        foreach ([
            'stock_in.create',
            'stock_in.view',
            'stock_in.confirm',
            'stock_in.edit_draft',
            'consignments.create',
            'consignments.view',
            'consignments.confirm',
            'consignments.edit_draft',
            'returns.create',
            'returns.view',
            'returns.finalize',
            'holding_area.view',
            'holding_area.assign_lot',
            'disposals.create',
            'disposals.view',
            'supplier_returns.create',
            'supplier_returns.view',
        ] as $permissionCode) {
            $this->assertTrue($staffRole->hasPermission($permissionCode));
        }

        $this->assertFalse($staffRole->hasPermission('stock_in.correct_confirmed'));
        $this->assertFalse($staffRole->hasPermission('consignments.edit_confirmed'));
        $this->assertFalse($staffRole->hasPermission('returns.reopen_reconciliation'));
        $this->assertFalse($staffRole->hasPermission('system.manage_roles'));
    }

    public function test_user_with_manage_users_permission_can_list_roles(): void
    {
        $role = Role::query()->where('role_code', 'admin')->firstOrFail();

        $user = $this->makeUserWithPermissions(['system.manage_users']);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/master-data/users/roles');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.id', $role->id)
            ->assertJsonPath('data.0.name', $role->role_name)
            ->assertJsonPath('data.0.code', $role->role_code);
    }

    public function test_product_crud_endpoints_work_and_write_audit_logs(): void
    {
        $user = $this->makeUserWithPermissions(['products.view', 'products.create', 'products.edit', 'products.delete']);
        Sanctum::actingAs($user);

        $storeResponse = $this->postJson('/api/v1/master-data/products', [
            'ref_num' => 'PRD-002',
            'product_name' => 'Surgical Mask',
            'product_type' => 'consumable',
            'category' => 'PPE',
            'uom' => 'box',
            'requires_expiry' => true,
            'requires_lot' => true,
            'is_active' => true,
        ]);

        $storeResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.ref_num', 'PRD-002');

        $productId = (int) $storeResponse->json('data.id');

        $this->putJson("/api/v1/master-data/products/{$productId}", [
            'product_name' => 'Surgical Mask Premium',
            'uom' => 'pack',
        ])->assertOk()
            ->assertJsonPath('data.product_name', 'Surgical Mask Premium')
            ->assertJsonPath('data.uom', 'pack');

        $this->getJson("/api/v1/master-data/products/{$productId}")
            ->assertOk()
            ->assertJsonPath('data.id', $productId);

        $this->deleteJson("/api/v1/master-data/products/{$productId}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('products', ['id' => $productId]);

        $this->assertDatabaseCount('audit_logs', 3);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Product::class,
            'auditable_id' => $productId,
            'action_type' => 'create',
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Product::class,
            'auditable_id' => $productId,
            'action_type' => 'update',
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Product::class,
            'auditable_id' => $productId,
            'action_type' => 'delete',
            'user_id' => $user->id,
        ]);
    }

    public function test_instrument_products_always_require_lot_tracking(): void
    {
        $user = $this->makeUserWithPermissions(['products.create', 'products.edit']);
        Sanctum::actingAs($user);

        $created = $this->postJson('/api/v1/master-data/products', [
            'ref_num' => 'INS-LOT-001',
            'product_name' => 'Lot-tracked Forceps',
            'product_type' => 'Instrument',
            'uom' => 'pcs',
            'requires_lot' => false,
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('data.requires_lot', true);

        $productId = (int) $created->json('data.id');

        $this->patchJson("/api/v1/master-data/products/{$productId}", [
            'requires_lot' => false,
        ])->assertOk()
            ->assertJsonPath('data.requires_lot', true);

        $this->assertDatabaseHas('products', [
            'id' => $productId,
            'requires_lot' => true,
        ]);
    }

    public function test_store_endpoints_for_other_master_data_modules_are_reachable_with_permissions(): void
    {
        $user = $this->makeUserWithPermissions([
            'suppliers.manage',
            'clients.manage',
            'instrument_sets.manage',
            'system.manage_users',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/master-data/suppliers', [
            'supplier_name' => 'Medline QA',
            'email' => 'qa@medline.test',
            'phone' => '123456',
            'is_active' => true,
        ])->assertCreated()->assertJsonPath('data.supplier_name', 'Medline QA');

        $this->postJson('/api/v1/master-data/clients', [
            'client_name' => 'RS Test',
            'client_type' => 'hospital',
            'email' => 'client@test.local',
            'phone' => '998877',
            'is_active' => true,
        ])->assertCreated()->assertJsonPath('data.client_name', 'RS Test');

        $this->postJson('/api/v1/master-data/instrument-sets', [
            'set_code' => 'SET-TST-001',
            'set_name' => 'Test Set',
            'description' => 'Test description',
            'is_active' => true,
        ])->assertCreated()->assertJsonPath('data.set_code', 'SET-TST-001');

        $staffRole = Role::query()->where('role_code', 'logistic_staff')->firstOrFail();

        $this->postJson('/api/v1/master-data/users', [
            'role_id' => $staffRole->id,
            'full_name' => 'Feature Test User',
            'email' => 'feature-user@test.local',
            'password' => 'Password123!',
            'is_active' => true,
        ])->assertCreated()->assertJsonPath('data.email', 'feature-user@test.local');

        $this->assertDatabaseHas('suppliers', ['supplier_name' => 'Medline QA']);
        $this->assertDatabaseHas('clients', ['client_name' => 'RS Test']);
        $this->assertDatabaseHas('instrument_sets', ['set_code' => 'SET-TST-001']);
        $this->assertDatabaseHas('users', ['email' => 'feature-user@test.local']);

        $this->assertSame(4, AuditLog::query()->count());
    }

    public function test_instrument_set_can_register_and_manage_registered_products(): void
    {
        $user = $this->makeUserWithPermissions([
            'instrument_sets.manage',
            'products.create',
        ]);
        Sanctum::actingAs($user);

        $productA = Product::query()->create([
            'ref_num' => 'SET-PROD-001',
            'product_name' => 'Forceps Curved',
            'product_type' => 'instrument',
            'category' => 'surgical',
            'uom' => 'pcs',
            'requires_expiry' => false,
            'requires_lot' => true,
            'is_active' => true,
        ]);

        $productB = Product::query()->create([
            'ref_num' => 'SET-PROD-002',
            'product_name' => 'Scalpel Handle',
            'product_type' => 'instrument',
            'category' => 'surgical',
            'uom' => 'pcs',
            'requires_expiry' => false,
            'requires_lot' => true,
            'is_active' => true,
        ]);

        $setResponse = $this->postJson('/api/v1/master-data/instrument-sets', [
            'set_code' => 'SET-FLOW-001',
            'set_name' => 'General Surgery Set',
            'description' => 'Set for standard surgery prep',
            'is_active' => true,
        ])->assertCreated();

        $setId = (int) $setResponse->json('data.id');

        $itemResponse = $this->postJson("/api/v1/master-data/instrument-sets/{$setId}/items", [
            'product_id' => $productA->id,
            'quantity' => 2,
            'sort_order' => 1,
            'remarks' => 'Primary clamp item',
        ])->assertCreated();

        $itemId = (int) $itemResponse->json('data.id');

        $this->postJson("/api/v1/master-data/instrument-sets/{$setId}/items", [
            'product_id' => $productB->id,
            'quantity' => 1,
            'sort_order' => 2,
        ])->assertCreated();

        $this->getJson("/api/v1/master-data/instrument-sets/{$setId}/items")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');

        $this->patchJson("/api/v1/master-data/instrument-sets/{$setId}/items/{$itemId}", [
            'quantity' => 3,
            'remarks' => 'Updated quantity',
        ])->assertOk()
            ->assertJsonPath('data.quantity', 3)
            ->assertJsonPath('data.remarks', 'Updated quantity');

        $this->deleteJson("/api/v1/master-data/instrument-sets/{$setId}/items/{$itemId}")
            ->assertOk();

        $instrumentSet = InstrumentSet::query()
            ->withCount('instrumentSetItems')
            ->findOrFail($setId);

        $this->assertSame(1, $instrumentSet->instrument_set_items_count);
    }

    /**
     * @param  array<int, string>  $permissionCodes
     */
    private function makeUserWithPermissions(array $permissionCodes): User
    {
        $role = Role::query()->create([
            'role_code' => 'test_role_'.str()->lower(str()->random(10)),
            'role_name' => 'Test Role '.str()->random(5),
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
            'full_name' => 'Feature Tester',
            'email' => 'tester_'.str()->lower(str()->random(8)).'@example.test',
            'password_hash' => 'Password123!',
            'is_active' => true,
        ]);
    }
}
