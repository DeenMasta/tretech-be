<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['lot_id', 'movement_type', 'old_status', 'new_status', 'old_location_type', 'old_location_id', 'new_location_type', 'new_location_id', 'recorded_by_user_id', 'reference_type', 'reference_id', 'notes', 'recorded_at'])]
class LotMovement extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
        ];
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\Lot');
    }

    public function recordedByUser(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\User', 'recorded_by_user_id');
    }
}
