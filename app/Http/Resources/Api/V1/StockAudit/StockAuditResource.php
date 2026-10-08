<?php

namespace App\Http\Resources\Api\V1\StockAudit;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockAuditResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'audit_no' => $this->audit_no,
            'status' => $this->status?->value,
            'scope_type' => $this->scope_type,
            'blind_count' => $this->blind_count,
            'remarks' => $this->remarks,
            'snapshot_movement_id' => $this->snapshot_movement_id,
            'summary' => $this->when(isset($this->summary), $this->summary ?? null),
            'started_at' => $this->started_at?->toIso8601String(),
            'started_by_user' => $this->userSummary('startedByUser'),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'submitted_by_user' => $this->userSummary('submittedByUser'),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'completed_by_user' => $this->userSummary('completedByUser'),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancelled_by_user' => $this->userSummary('cancelledByUser'),
            'cancel_reason' => $this->cancel_reason,
            'items_count' => $this->whenCounted('items'),
            'items' => $this->whenLoaded('items', fn () => StockAuditItemResource::collection($this->items)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function userSummary(string $relation): mixed
    {
        return $this->whenLoaded($relation, function () use ($relation) {
            $user = $this->{$relation};

            return $user ? ['id' => $user->id, 'full_name' => $user->full_name] : null;
        });
    }
}
