<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['consignment_id', 'lot_id', 'scanned_lot_number'])]
class ConsignmentItem extends Model
{
    use HasFactory;

    public function consignment(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\Consignment');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\Lot');
    }
}
