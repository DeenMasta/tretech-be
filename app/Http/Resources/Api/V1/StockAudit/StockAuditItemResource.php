<?php

namespace App\Http\Resources\Api\V1\StockAudit;

use App\Enums\StockAuditStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockAuditItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $hideExpected = $this->stockAudit?->blind_count
            && $this->stockAudit?->status === StockAuditStatus::InProgress;

        return [
            'id' => $this->id,
            'stock_audit_id' => $this->stock_audit_id,
            'lot_id' => $this->lot_id,
            'snapshot' => $this->snapshot,
            'expected_quantity' => $hideExpected ? null : $this->expected_quantity,
            'counted_quantity' => $this->counted_quantity,
            'variance_quantity' => $hideExpected ? null : $this->variance_quantity,
            'result' => $hideExpected ? ($this->counted_quantity === null ? 'pending' : 'counted') : $this->result?->value,
            'count_method' => $this->count_method,
            'counted_at' => $this->counted_at?->toIso8601String(),
            'counted_by_user' => $this->whenLoaded('countedByUser', fn () => $this->countedByUser ? [
                'id' => $this->countedByUser->id,
                'full_name' => $this->countedByUser->full_name,
            ] : null),
            'remarks' => $this->remarks,
            'version' => $this->version,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
