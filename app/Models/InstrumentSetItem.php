<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['instrument_set_id', 'product_id', 'quantity', 'remarks', 'sort_order'])]
class InstrumentSetItem extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function instrumentSet(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\InstrumentSet');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\Product');
    }
}
