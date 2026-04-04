<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['supplier_id', 'session_no', 'do_number', 'stock_in_at', 'pic_user_id', 'status', 'remarks', 'confirmed_at', 'confirmed_by_user_id'])]
class StockIn extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'stock_in_at' => 'datetime',
            'confirmed_at' => 'datetime',
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

    public function confirmedByUser(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\User', 'confirmed_by_user_id');
    }

    public function stockInItems(): HasMany
    {
        return $this->hasMany('App\\Models\\StockInItem');
    }
}
