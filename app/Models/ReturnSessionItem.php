<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['return_session_id', 'lot_id', 'returned_at', 'returned_by_user_id', 'source_qr_payload', 'remarks'])]
class ReturnSessionItem extends Model
{
    use HasFactory;

    public function returnSession(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\ReturnSession');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\Lot');
    }
}
