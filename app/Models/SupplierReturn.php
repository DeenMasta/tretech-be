<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['supplier_id', 'return_no', 'returned_at', 'pic_user_id', 'status', 'remarks', 'completed_at', 'completed_by_user_id'])]
class SupplierReturn extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'returned_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\Supplier');
    }

    public function picUser(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\User', 'pic_user_id');
    }

    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\User', 'completed_by_user_id');
    }

    public function supplierReturnItems(): HasMany
    {
        return $this->hasMany('App\\Models\\SupplierReturnItem');
    }
}
