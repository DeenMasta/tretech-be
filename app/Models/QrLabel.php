<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['lot_id', 'qr_payload', 'generated_at', 'generated_by_user_id'])]
class QrLabel extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
        ];
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\Lot');
    }

    public function generatedByUser(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\User', 'generated_by_user_id');
    }

    public function qrPrintJobs(): HasMany
    {
        return $this->hasMany('App\\Models\\QrPrintJob');
    }
}
