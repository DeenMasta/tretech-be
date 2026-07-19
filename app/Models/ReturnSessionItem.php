<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['return_session_id', 'lot_id', 'instrument_set_id', 'product_id', 'returned_at', 'returned_by_user_id', 'source_qr_payload', 'remarks', 'quantity', 'used_quantity', 'damaged_quantity', 'missing_quantity'])]
class ReturnSessionItem extends Model
{
    use HasFactory;

    protected $casts = [
        'returned_at' => 'datetime',
    ];

    public function returnSession(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\ReturnSession');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\Lot');
    }

    public function setInstrumentItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany('App\\Models\\ReturnSessionSetInstrumentItem');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\Product');
    }

    public function instrumentSet(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\InstrumentSet');
    }
}
