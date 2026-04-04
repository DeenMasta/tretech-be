<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['lot_id', 'qr_label_id', 'printer_device_id', 'status', 'queued_at', 'sent_to_printer_at', 'completed_at', 'failure_reason', 'reprint_reason', 'reprint_count', 'sent_by_user_id'])]
class QrPrintJob extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'queued_at' => 'datetime',
            'sent_to_printer_at' => 'datetime',
            'completed_at' => 'datetime',
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

    public function sentByUser(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\User', 'sent_by_user_id');
    }
}
