<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'stock_in_id',
    'entry_kind',
    'product_id',
    'instrument_set_id',
    'lot_id',
    'scanned_lot_number',
    'manufacturing_date',
    'expiry_date',
    'lot_entry_mode',
    'expiry_entry_mode',
    'missing_lot_flag',
    'source_barcode',
    'entry_override_reason',
    'remarks',
    'quantity',
])]
class StockInItem extends Model
{
    use HasFactory;

    public const ENTRY_KIND_PRODUCT = 'product';
    public const ENTRY_KIND_SET = 'set';

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'missing_lot_flag' => 'boolean',
        ];
    }

    public function stockIn(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\StockIn');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\Product');
    }

    public function instrumentSet(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\InstrumentSet');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\Lot');
    }

    public function isSetEntry(): bool
    {
        return $this->entry_kind === self::ENTRY_KIND_SET;
    }
}
