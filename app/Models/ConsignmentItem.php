<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['consignment_id', 'entry_kind', 'lot_id', 'instrument_set_id', 'issued_at', 'issued_by_user_id', 'remarks'])]
class ConsignmentItem extends Model
{
    use HasFactory;

    protected $casts = [
        'issued_at' => 'datetime',
    ];

    public function consignment(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\Consignment');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\Lot');
    }

    public function instrumentSet(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\InstrumentSet');
    }

    public function isSetEntry(): bool
    {
        return $this->entry_kind === 'set';
    }
}
