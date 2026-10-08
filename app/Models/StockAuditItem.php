<?php

namespace App\Models;

use App\Enums\StockAuditResult;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'stock_audit_id', 'lot_id', 'snapshot', 'expected_quantity', 'counted_quantity',
    'variance_quantity', 'result', 'count_method', 'source_qr_payload', 'counted_at',
    'counted_by_user_id', 'remarks', 'version',
])]
class StockAuditItem extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'result' => StockAuditResult::class,
            'expected_quantity' => 'integer',
            'counted_quantity' => 'integer',
            'variance_quantity' => 'integer',
            'version' => 'integer',
            'counted_at' => 'datetime',
        ];
    }

    public function stockAudit(): BelongsTo
    {
        return $this->belongsTo(StockAudit::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function countedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by_user_id');
    }
}
