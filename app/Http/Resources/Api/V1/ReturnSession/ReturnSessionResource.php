<?php

namespace App\Http\Resources\Api\V1\ReturnSession;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReturnSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'return_session_no'   => $this->return_session_no,
            'status'              => $this->status,
            'consignment_id'      => $this->consignment_id,
            'consignment'         => $this->whenLoaded('consignment', function () {
                return [
                    'id'             => $this->consignment?->id,
                    'consignment_no' => $this->consignment?->consignment_no,
                ];
            }),
            'pic_user_id'         => $this->pic_user_id,
            'pic_user'            => $this->whenLoaded('picUser', function () {
                return [
                    'id'        => $this->picUser?->id,
                    'full_name' => $this->picUser?->full_name,
                ];
            }),
            'remarks'             => $this->remarks,
            'started_at'          => $this->started_at?->toIso8601String(),
            'completed_at'        => $this->completed_at?->toIso8601String(),
            'completed_by_user_id' => $this->completed_by_user_id,
            'completed_by_user'   => $this->whenLoaded('completedByUser', function () {
                return [
                    'id'        => $this->completedByUser?->id,
                    'full_name' => $this->completedByUser?->full_name,
                ];
            }),
            'items_count'         => $this->whenCounted('returnSessionItems'),
            'items'               => $this->whenLoaded('returnSessionItems', fn () =>
                ReturnSessionItemResource::collection($this->returnSessionItems)
            ),
            'reconciliation'      => $this->whenLoaded('reconciliation', fn () => 
                $this->reconciliation ? new \App\Http\Resources\Api\V1\Reconciliation\ReconciliationResource($this->reconciliation) : null
            ),
            'created_at'          => $this->created_at?->toIso8601String(),
            'updated_at'          => $this->updated_at?->toIso8601String(),
        ];
    }
}
