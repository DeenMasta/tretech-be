<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['reconciliation_id', 'lot_id', 'item_status'])]
class ReconciliationItem extends Model
{
    use HasFactory;

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\Reconciliation');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\Lot');
    }
}
