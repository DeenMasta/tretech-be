<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['reconciliation_id', 'lot_id', 'result', 'remarks'])]
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

    /**
     * Per-instrument breakdown for set-instance lots. Empty for product lots.
     */
    public function setInstrumentResults(): HasMany
    {
        return $this->hasMany('App\\Models\\ReconciliationSetInstrumentResult');
    }
}
