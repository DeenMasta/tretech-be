<?php

namespace App\Http\Resources\Api\V1\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LotMovementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lot_id' => $this->lot_id,
            'movement_type' => $this->movement_type,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'from_status' => $this->from_status,
            'to_status' => $this->to_status,
            'from_location_type' => $this->from_location_type,
            'from_location_id' => $this->from_location_id,
            'to_location_type' => $this->to_location_type,
            'to_location_id' => $this->to_location_id,
            'performed_at' => $this->performed_at?->toIso8601String(),
            'performed_by_user' => $this->whenLoaded('recordedByUser', function () {
                return [
                    'id' => $this->recordedByUser?->id,
                    'full_name' => $this->recordedByUser?->full_name,
                    'email' => $this->recordedByUser?->email,
                ];
            }),
            'lot' => $this->whenLoaded('lot', function () {
                return [
                    'id' => $this->lot?->id,
                    'lot_number' => $this->lot?->lot_number,
                    'status' => $this->lot?->status,
                    'product' => [
                        'id' => $this->lot?->product?->id,
                        'ref_num' => $this->lot?->product?->ref_num,
                        'product_name' => $this->lot?->product?->product_name,
                    ],
                ];
            }),
            'remarks' => $this->remarks,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
