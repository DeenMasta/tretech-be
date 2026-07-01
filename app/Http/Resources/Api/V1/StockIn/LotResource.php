<?php

namespace App\Http\Resources\Api\V1\StockIn;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LotResource extends JsonResource
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
            'product_id' => $this->product_id,
            'instrument_set_id' => $this->instrument_set_id,
            'supplier_id' => $this->supplier_id,
            'lot_number' => $this->lot_number,

            'is_system_generated_lot' => (bool) $this->is_system_generated_lot,
            'manufacturing_date' => $this->manufacturing_date,
            'quantity' => $this->quantity ?? 1,
            'quantity_available' => $this->quantity_available ?? 1,
            'quantity_consigned' => $this->quantity_consigned ?? 0,
            'expiry_date' => $this->expiry_date?->format('Y-m-d'),
            'status' => $this->status,
            'current_location_type' => $this->current_location_type,
            'current_location_id' => $this->current_location_id,
            'received_at' => $this->received_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
