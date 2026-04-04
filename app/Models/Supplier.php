<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['supplier_name', 'phone', 'email', 'address', 'is_active'])]
class Supplier extends Model
{
    use HasFactory;

    public function lots(): HasMany
    {
        return $this->hasMany('App\\Models\\Lot');
    }

    public function stockIns(): HasMany
    {
        return $this->hasMany('App\\Models\\StockIn');
    }

    public function supplierReturns(): HasMany
    {
        return $this->hasMany('App\\Models\\SupplierReturn');
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
