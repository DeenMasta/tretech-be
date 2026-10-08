<?php

namespace App\Models;

use App\Enums\StockAuditStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'audit_no', 'status', 'scope_type', 'blind_count', 'remarks', 'snapshot_movement_id',
    'started_at', 'started_by_user_id', 'submitted_at', 'submitted_by_user_id',
    'completed_at', 'completed_by_user_id', 'cancelled_at', 'cancelled_by_user_id', 'cancel_reason',
])]
class StockAudit extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => StockAuditStatus::class,
            'blind_count' => 'boolean',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockAuditItem::class);
    }

    public function startedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by_user_id');
    }

    public function submittedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }

    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }
}
