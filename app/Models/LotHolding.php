<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['lot_id', 'holding_reason', 'assigned_at', 'assigned_by_user_id', 'released_at', 'released_by_user_id', 'corrected_lot_number', 'resolution_reason', 'remarks'])]
class LotHolding extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\Lot');
    }

    public function assignedByUser(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\User', 'assigned_by_user_id');
    }

    public function releasedByUser(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\User', 'released_by_user_id');
    }
}
