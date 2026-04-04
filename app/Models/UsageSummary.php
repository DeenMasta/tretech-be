<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['reconciliation_id', 'summary_no', 'generated_at', 'generated_by_user_id', 'status'])]
class UsageSummary extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
        ];
    }

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\Reconciliation');
    }

    public function generatedByUser(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\User', 'generated_by_user_id');
    }

    public function usageSummaryItems(): HasMany
    {
        return $this->hasMany('App\\Models\\UsageSummaryItem');
    }

    public function usageSummaryPushLogs(): HasMany
    {
        return $this->hasMany('App\\Models\\UsageSummaryPushLog');
    }
}
