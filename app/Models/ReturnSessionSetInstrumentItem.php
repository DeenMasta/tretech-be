<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'return_session_item_id',
    'product_id',
    'returned_quantity',
    'remarks',
])]
class ReturnSessionSetInstrumentItem extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'returned_quantity' => 'integer',
        ];
    }

    public function returnSessionItem(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\ReturnSessionItem');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\Product');
    }
}
