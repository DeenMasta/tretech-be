<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['disposal_id', 'lot_id', 'scanned_lot_number', 'disposal_reason_category', 'disposal_reason_description'])]
class DisposalItem extends Model
{
    use HasFactory;

    public function disposal(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\Disposal');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\Lot');
    }
}
