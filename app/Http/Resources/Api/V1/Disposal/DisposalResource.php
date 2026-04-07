<?php

namespace App\Http\Resources\Api\V1\Disposal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DisposalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'disposal_no'           => $this->disposal_no,
            'status'                => $this->status,
            'disposed_at'           => $this->disposed_at?->toIso8601String(),
            'remarks'               => $this->remarks,
            'pic_user_id'           => $this->pic_user_id,
            'pic_user'              => $this->whenLoaded('picUser', fn () => [
                'id'        => $this->picUser?->id,
                'full_name' => $this->picUser?->full_name,
            ]),
            'completed_at'          => $this->completed_at?->toIso8601String(),
            'completed_by_user_id'  => $this->completed_by_user_id,
            'completed_by_user'     => $this->whenLoaded('completedByUser', fn () => [
                'id'        => $this->completedByUser?->id,
                'full_name' => $this->completedByUser?->full_name,
            ]),
            'disposal_items_count'  => $this->whenCounted('disposalItems'),
            'disposal_items'        => $this->whenLoaded(
                'disposalItems',
                fn () => DisposalItemResource::collection($this->disposalItems)
            ),
            'created_at'            => $this->created_at?->toIso8601String(),
            'updated_at'            => $this->updated_at?->toIso8601String(),
        ];
    }
}
