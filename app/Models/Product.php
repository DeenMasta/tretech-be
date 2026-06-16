<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['ref_num', 'product_name', 'product_type', 'category', 'uom', 'requires_expiry', 'requires_lot', 'is_active'])]
class Product extends Model
{
    use HasFactory;

    public function lots(): HasMany
    {
        return $this->hasMany('App\\Models\\Lot');
    }

    public function stockInItems(): HasMany
    {
        return $this->hasMany('App\\Models\\StockInItem');
    }

    public function instrumentSetItems(): HasMany
    {
        return $this->hasMany('App\\Models\\InstrumentSetItem');
    }

    protected function casts(): array
    {
        return [
            'requires_expiry' => 'boolean',
            'requires_lot' => 'boolean',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
