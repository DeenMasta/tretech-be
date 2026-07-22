<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $role = Role::query()->where('role_code', 'logistic_staff')->first();
        $permission = Permission::query()->where('permission_code', 'products.view')->first();

        if (! $role || ! $permission) {
            return;
        }

        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $role->flushPermissionCache();
    }

    public function down(): void
    {
        $role = Role::query()->where('role_code', 'logistic_staff')->first();
        $permission = Permission::query()->where('permission_code', 'products.view')->first();

        if (! $role || ! $permission) {
            return;
        }

        $role->permissions()->detach($permission->id);
        $role->flushPermissionCache();
    }
};
