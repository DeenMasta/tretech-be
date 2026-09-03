<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const PERMISSIONS = [
        'disposals.reopen_completed' => [
            'permission_name' => 'Reopen Completed Disposals',
            'module' => 'Disposal & Returns',
            'description' => 'Reopen completed disposals for correction in Disposal & Returns',
        ],
        'supplier_returns.reopen_completed' => [
            'permission_name' => 'Reopen Completed Supplier Returns',
            'module' => 'Disposal & Returns',
            'description' => 'Reopen completed supplier returns for correction in Disposal & Returns',
        ],
    ];

    public function up(): void
    {
        $permissionIds = [];

        foreach (self::PERMISSIONS as $code => $attributes) {
            $permissionIds[] = Permission::query()->firstOrCreate(
                ['permission_code' => $code],
                $attributes,
            )->id;
        }

        $adminRole = Role::query()->where('role_code', 'admin')->first();

        if ($adminRole) {
            $adminRole->permissions()->syncWithoutDetaching($permissionIds);
            $adminRole->flushPermissionCache();
        }
    }

    public function down(): void
    {
        $permissions = Permission::query()
            ->whereIn('permission_code', array_keys(self::PERMISSIONS))
            ->get();

        foreach ($permissions as $permission) {
            $roles = $permission->roles()->get();
            $permission->roles()->detach();
            $permission->delete();

            $roles->each->flushPermissionCache();
        }
    }
};
