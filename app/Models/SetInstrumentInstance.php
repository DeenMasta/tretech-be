<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'lot_id',
    'instrument_set_id',
    'set_instrument_id',
    'stock_in_id',
    'stock_in_item_id',
    'instance_number',
    'status',
    'remarks',
])]
class SetInstrumentInstance extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\Lot');
    }

    public function instrumentSet(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\InstrumentSet');
    }

    public function setInstrument(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\SetInstrument');
    }

    public function stockIn(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\StockIn');
    }

    public function stockInItem(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\StockInItem');
    }
}
