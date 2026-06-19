<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['set_code', 'set_name', 'description', 'is_active'])]
class InstrumentSet extends Model
{
    use HasFactory;

    public function lots(): HasMany
    {
        return $this->hasMany('App\\Models\\Lot');
    }

    public function instrumentSetItems(): HasMany
    {
        return $this->hasMany('App\\Models\\InstrumentSetItem');
    }

    /**
     * Non-product instruments registered directly under this set.
     * Complements instrumentSetItems(), which links existing Products.
     */
    public function setInstruments(): HasMany
    {
        return $this->hasMany('App\\Models\\SetInstrument');
    }

    /**
     * Stock-in lines that received this set as a unit (entry_kind = 'set').
     */
    public function stockInItems(): HasMany
    {
        return $this->hasMany('App\\Models\\StockInItem');
    }

    public function setInstrumentInstances(): HasMany
    {
        return $this->hasMany('App\\Models\\SetInstrumentInstance');
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
