<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'lot_id',
    'qr_label_id',
    'action_type',
    'reprint_reason',
    'status',
    'printer_name',
    'device_id',
    'tspl_payload',
    'error_message',
    'requested_by_user_id',
    'requested_at',
    'printed_at',
    'failed_at',
])]
class QrPrintJob extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'printed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\Lot');
    }

    public function qrLabel(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\QrLabel');
    }

    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\User', 'requested_by_user_id');
    }
}
