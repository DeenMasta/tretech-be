<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['stock_in_id', 'product_id', 'lot_id', 'scanned_lot_number', 'supplier_batch_code', 'expiry_date', 'lot_entry_mode', 'expiry_entry_mode', 'missing_lot_flag', 'source_barcode', 'entry_override_reason', 'remarks'])]
class StockInItem extends Model
{
    use HasFactory;

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

    public function lot(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\Lot');
    }
}
