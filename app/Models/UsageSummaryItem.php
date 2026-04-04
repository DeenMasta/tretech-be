<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['usage_summary_id', 'product_id', 'lot_id', 'qty_consigned', 'qty_returned', 'qty_used', 'qty_disposed', 'qty_returned_to_supplier'])]
class UsageSummaryItem extends Model
{
    use HasFactory;

    public function usageSummary(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\UsageSummary');
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
