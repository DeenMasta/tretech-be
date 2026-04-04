<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['lot_id', 'status', 'assigned_lot_number', 'assignment_reason', 'assigned_by_user_id', 'assigned_at'])]
class LotHolding extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
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
}
