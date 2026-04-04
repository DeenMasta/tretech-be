<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['role_code', 'role_name'])]
class Role extends Model
{
    use HasFactory;

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * The permissions that belong to this role.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions')->withTimestamps();
    }

    /**
     * Check if role has a specific permission.
     */
    public function hasPermission(string $permissionCode): bool
    {
        return $this->permissions()->where('permission_code', $permissionCode)->exists();
    }

    /**
     * Get all permission codes for this role.
     */
    public function getPermissionCodes(): array
    {
        return $this->permissions()->pluck('permission_code')->toArray();
    }
}
