<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Cache;

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
     * Permission codes are cached per role for 1 hour to avoid repeated DB lookups on every request.
     */
    public function hasPermission(string $permissionCode): bool
    {
        return in_array($permissionCode, $this->getCachedPermissionCodes(), true);
    }

    /**
     * Get all permission codes for this role.
     */
    public function getPermissionCodes(): array
    {
        return $this->getCachedPermissionCodes();
    }

    /**
     * Flush the cached permission codes for this role.
     * Call this whenever role permissions are modified.
     */
    public function flushPermissionCache(): void
    {
        Cache::forget("role_permissions_{$this->id}");
    }

    /**
     * @return string[]
     */
    private function getCachedPermissionCodes(): array
    {
        return Cache::remember(
            "role_permissions_{$this->id}",
            3600,
            fn () => $this->permissions()->pluck('permission_code')->all()
        );
    }
}
