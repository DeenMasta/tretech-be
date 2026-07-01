<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['disposal_id', 'lot_id', 'disposal_category', 'reason_text', 'remarks', 'quantity'])]
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
