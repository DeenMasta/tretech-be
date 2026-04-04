<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['consignment_id', 'return_session_id', 'reconciliation_no', 'pic_user_id', 'status', 'remarks', 'completed_at', 'completed_by_user_id', 'reopened_at', 'reopened_by_user_id', 'reopen_reason'])]
class Reconciliation extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'reopened_at' => 'datetime',
        ];
    }

    public function consignment(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\Consignment');
    }

    public function returnSession(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\ReturnSession');
    }

    public function picUser(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\User', 'pic_user_id');
    }

    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\User', 'completed_by_user_id');
    }

    public function reopenedByUser(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\User', 'reopened_by_user_id');
    }

    public function reconciliationItems(): HasMany
    {
        return $this->hasMany('App\\Models\\ReconciliationItem');
    }

    public function usageSummary(): HasOne
    {
        return $this->hasOne('App\\Models\\UsageSummary');
    }
}
