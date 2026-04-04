<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['role_id', 'full_name', 'email', 'password_hash', 'is_active', 'last_login_at'])]
#[Hidden(['password_hash'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Auth uses password_hash instead of the default password column.
     */
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class);
    }

    public function loginAttempts(): HasMany
    {
        return $this->hasMany(LoginAttempt::class);
    }

    // Stock In relationships
    public function picStockIns(): HasMany
    {
        return $this->hasMany('App\\Models\\StockIn', 'pic_user_id');
    }

    public function confirmedStockIns(): HasMany
    {
        return $this->hasMany('App\\Models\\StockIn', 'confirmed_by_user_id');
    }

    // QR Label relationships
    public function generatedQrLabels(): HasMany
    {
        return $this->hasMany('App\\Models\\QrLabel', 'generated_by_user_id');
    }

    public function sentQrPrintJobs(): HasMany
    {
        return $this->hasMany('App\\Models\\QrPrintJob', 'sent_by_user_id');
    }

    // Consignment relationships
    public function picConsignments(): HasMany
    {
        return $this->hasMany('App\\Models\\Consignment', 'pic_user_id');
    }

    public function confirmedConsignments(): HasMany
    {
        return $this->hasMany('App\\Models\\Consignment', 'confirmed_by_user_id');
    }

    public function editedConsignments(): HasMany
    {
        return $this->hasMany('App\\Models\\Consignment', 'last_post_confirm_edit_by_user_id');
    }

    // Return Session relationships
    public function picReturnSessions(): HasMany
    {
        return $this->hasMany('App\\Models\\ReturnSession', 'pic_user_id');
    }

    public function completedReturnSessions(): HasMany
    {
        return $this->hasMany('App\\Models\\ReturnSession', 'completed_by_user_id');
    }

    // Reconciliation relationships
    public function picReconciliations(): HasMany
    {
        return $this->hasMany('App\\Models\\Reconciliation', 'pic_user_id');
    }

    public function completedReconciliations(): HasMany
    {
        return $this->hasMany('App\\Models\\Reconciliation', 'completed_by_user_id');
    }

    public function reopenedReconciliations(): HasMany
    {
        return $this->hasMany('App\\Models\\Reconciliation', 'reopened_by_user_id');
    }

    // Usage Summary relationships
    public function generatedUsageSummaries(): HasMany
    {
        return $this->hasMany('App\\Models\\UsageSummary', 'generated_by_user_id');
    }

    public function pushedUsageSummaries(): HasMany
    {
        return $this->hasMany('App\\Models\\UsageSummaryPushLog', 'pushed_by_user_id');
    }

    // Disposal relationships
    public function picDisposals(): HasMany
    {
        return $this->hasMany('App\\Models\\Disposal', 'pic_user_id');
    }

    public function completedDisposals(): HasMany
    {
        return $this->hasMany('App\\Models\\Disposal', 'completed_by_user_id');
    }

    // Supplier Return relationships
    public function picSupplierReturns(): HasMany
    {
        return $this->hasMany('App\\Models\\SupplierReturn', 'pic_user_id');
    }

    public function completedSupplierReturns(): HasMany
    {
        return $this->hasMany('App\\Models\\SupplierReturn', 'completed_by_user_id');
    }

    // Lot Holding relationships
    public function assignedLotHoldings(): HasMany
    {
        return $this->hasMany('App\\Models\\LotHolding', 'assigned_by_user_id');
    }

    // Lot Movement relationships
    public function recordedLotMovements(): HasMany
    {
        return $this->hasMany('App\\Models\\LotMovement', 'recorded_by_user_id');
    }

    // Audit Log relationships
    public function auditLogs(): HasMany
    {
        return $this->hasMany('App\\Models\\AuditLog');
    }

    /**
     * Check if user has a specific permission.
     *
     * @param string $permissionCode Permission code to check (e.g., 'products.view')
     * @return bool
     */
    public function hasPermission(string $permissionCode): bool
    {
        if (!$this->role) {
            return false;
        }

        return $this->role->hasPermission($permissionCode);
    }

    /**
     * Check if user has any of the given permissions.
     *
     * @param array $permissionCodes Array of permission codes
     * @return bool
     */
    public function hasAnyPermission(array $permissionCodes): bool
    {
        return collect($permissionCodes)->some(fn($code) => $this->hasPermission($code));
    }

    /**
     * Check if user has all of the given permissions.
     *
     * @param array $permissionCodes Array of permission codes
     * @return bool
     */
    public function hasAllPermissions(array $permissionCodes): bool
    {
        return collect($permissionCodes)->every(fn($code) => $this->hasPermission($code));
    }

    /**
     * Get all permission codes for the user's role.
     *
     * @return array
     */
    public function getPermissionCodes(): array
    {
        if (!$this->role) {
            return [];
        }

        return $this->role->getPermissionCodes();
    }

    /**
     * Get the user's role code.
     *
     * @return string|null
     */
    public function getRoleCode(): ?string
    {
        return $this->role?->role_code;
    }

    /**
     * Check if user is an admin.
     *
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this->getRoleCode() === 'admin';
    }

    /**
     * Check if user is logistic staff.
     *
     * @return bool
     */
    public function isLogisticStaff(): bool
    {
        return $this->getRoleCode() === 'logistic_staff';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password_hash' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }
}
