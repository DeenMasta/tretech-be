<?php

namespace App\Http\Resources\Api\V1\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryUnitResource extends JsonResource
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
            'lot_number' => $this->lot_number,
            'status' => $this->status,
            'supplier_batch_code' => $this->supplier_batch_code,
            'expiry_date' => $this->expiry_date?->format('Y-m-d'),
            'received_at' => $this->received_at?->toIso8601String(),
            'product' => $this->whenLoaded('product', function () {
                return [
                    'id' => $this->product?->id,
                    'ref_num' => $this->product?->ref_num,
                    'product_name' => $this->product?->product_name,
                ];
            }),
            'supplier' => $this->whenLoaded('supplier', function () {
                return [
                    'id' => $this->supplier?->id,
                    'supplier_name' => $this->supplier?->supplier_name,
                ];
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
