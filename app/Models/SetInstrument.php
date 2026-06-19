<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['instrument_set_id', 'code', 'name', 'quantity', 'sort_order', 'remarks', 'is_active'])]
class SetInstrument extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function instrumentSet(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\InstrumentSet');
    }

    public function reconciliationResults(): HasMany
    {
        return $this->hasMany('App\\Models\\ReconciliationSetInstrumentResult');
    }

    public function instances(): HasMany
    {
        return $this->hasMany('App\\Models\\SetInstrumentInstance');
    }
}
