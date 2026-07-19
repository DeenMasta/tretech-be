<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['supplier_return_id', 'lot_id', 'return_reason', 'remarks', 'quantity'])]
class SupplierReturnItem extends Model
{
    use HasFactory;

    public function supplierReturn(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\SupplierReturn');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\Lot');
    }
}
