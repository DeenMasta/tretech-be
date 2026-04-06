<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['lot_id', 'movement_type', 'reference_type', 'reference_id', 'from_status', 'to_status', 'from_location_type', 'from_location_id', 'to_location_type', 'to_location_id', 'performed_at', 'performed_by_user_id', 'remarks'])]
class LotMovement extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'performed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\Lot');
    }

    public function recordedByUser(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\User', 'performed_by_user_id');
    }
}
