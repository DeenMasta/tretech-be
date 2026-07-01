<?php

namespace App\Http\Resources\Api\V1\HoldingArea;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HoldingAreaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'lot_number'              => $this->lot_number,

            'is_system_generated_lot' => (bool) $this->is_system_generated_lot,
            'manufacturing_date'     => $this->manufacturing_date,
            'expiry_date'             => $this->expiry_date?->format('Y-m-d'),
            'status'                  => $this->status,
            'received_at'             => $this->received_at?->toIso8601String(),
            'remarks'                 => $this->remarks,

            'product' => $this->whenLoaded('product', fn () => [
                'id'           => $this->product?->id,
                'ref_num'      => $this->product?->ref_num,
                'product_name' => $this->product?->product_name,
            ]),

            'supplier' => $this->whenLoaded('supplier', fn () => [
                'id'            => $this->supplier?->id,
                'supplier_name' => $this->supplier?->supplier_name,
            ]),

            // The open holding record — always present for holding-status lots
            'lot_holding' => $this->whenLoaded('lotHolding', fn () => $this->lotHolding ? [
                'id'                   => $this->lotHolding->id,
                'holding_reason'       => $this->lotHolding->holding_reason,
                'assigned_at'          => $this->lotHolding->assigned_at?->toIso8601String(),
                'assigned_by_user'     => $this->lotHolding->relationLoaded('assignedByUser') ? [
                    'id'        => $this->lotHolding->assignedByUser?->id,
                    'full_name' => $this->lotHolding->assignedByUser?->full_name,
                ] : null,
                'released_at'          => $this->lotHolding->released_at?->toIso8601String(),
                'released_by_user_id'  => $this->lotHolding->released_by_user_id,
                'corrected_lot_number' => $this->lotHolding->corrected_lot_number,
                'resolution_reason'    => $this->lotHolding->resolution_reason,
                'remarks'              => $this->lotHolding->remarks,
            ] : null),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
