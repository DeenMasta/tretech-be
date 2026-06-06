<?php

namespace App\Http\Resources\Api\V1\MasterData;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstrumentSetItemResource extends JsonResource
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
            'instrument_set_id' => $this->instrument_set_id,
            'product_id' => $this->product_id,
            'quantity' => (int) $this->quantity,
            'sort_order' => (int) $this->sort_order,
            'remarks' => $this->remarks,
            'product' => $this->whenLoaded('product', function () {
                return [
                    'id' => $this->product?->id,
                    'ref_num' => $this->product?->ref_num,
                    'product_name' => $this->product?->product_name,
                    'is_active' => $this->product?->is_active,
                ];
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
