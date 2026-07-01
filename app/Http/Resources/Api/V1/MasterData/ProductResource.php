<?php

namespace App\Http\Resources\Api\V1\MasterData;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
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
            'ref_num' => $this->ref_num,
            'product_name' => $this->product_name,
            'product_type' => $this->product_type,
            'category' => $this->category,
            'uom' => $this->uom,
            'requires_expiry' => $this->requires_expiry,
            'requires_lot' => $this->requires_lot,
            'is_active' => $this->is_active,
            'available_lots_count' => $this->whenCounted('available_lots_count'),
            'total_quantity_available' => (int) $this->total_quantity_available,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
