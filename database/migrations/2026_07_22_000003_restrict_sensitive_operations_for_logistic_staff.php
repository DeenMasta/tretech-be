<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const RESTRICTED_PERMISSION_CODES = [
        'stock_in.correct_confirmed',
        'consignments.edit_confirmed',
        'returns.reopen_reconciliation',
    ];

    public function up(): void
    {
        $role = Role::query()->where('role_code', 'logistic_staff')->first();

        if (! $role) {
            return;
        }

        $permissionIds = Permission::query()
            ->whereIn('permission_code', self::RESTRICTED_PERMISSION_CODES)
            ->pluck('id')
            ->all();

        $role->permissions()->detach($permissionIds);
        $role->flushPermissionCache();
    }

    public function down(): void
    {
        $role = Role::query()->where('role_code', 'logistic_staff')->first();

        if (! $role) {
            return;
        }

        $permissionIds = Permission::query()
            ->whereIn('permission_code', self::RESTRICTED_PERMISSION_CODES)
            ->pluck('id')
            ->all();

        $role->permissions()->syncWithoutDetaching($permissionIds);
        $role->flushPermissionCache();
    }
};
