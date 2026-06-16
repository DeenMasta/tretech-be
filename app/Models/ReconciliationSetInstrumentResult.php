<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-instrument reconciliation outcome for a set-instance lot.
 *
 * Either `set_instrument_id` or `product_id` is populated, never both.
 */
#[Fillable([
    'reconciliation_item_id',
    'set_instrument_id',
    'product_id',
    'expected_quantity',
    'returned_quantity',
    'missing_quantity',
    'damaged_quantity',
    'result',
    'remarks',
])]
class ReconciliationSetInstrumentResult extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'expected_quantity' => 'integer',
            'returned_quantity' => 'integer',
            'missing_quantity' => 'integer',
            'damaged_quantity' => 'integer',
        ];
    }

    public function reconciliationItem(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\ReconciliationItem');
    }

    public function setInstrument(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\SetInstrument');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\Product');
    }
}
