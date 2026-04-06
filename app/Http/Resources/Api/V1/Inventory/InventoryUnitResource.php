<?php

namespace App\Http\Resources\Api\V1\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// LotMovementResource is in the same namespace, no import needed

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
            'id'                      => $this->id,
            'lot_number'              => $this->lot_number,
            'original_lot_number'     => $this->original_lot_number,
            'is_system_generated_lot' => (bool) $this->is_system_generated_lot,
            'supplier_batch_code'     => $this->supplier_batch_code,
            'expiry_date'             => $this->expiry_date?->format('Y-m-d'),
            'status'                  => $this->status,
            'current_location_type'   => $this->current_location_type,
            'current_location_id'     => $this->current_location_id,
            'remarks'                 => $this->remarks,
            'received_at'             => $this->received_at?->toIso8601String(),
            'created_at'              => $this->created_at?->toIso8601String(),
            'updated_at'              => $this->updated_at?->toIso8601String(),

            // Related product
            'product' => $this->whenLoaded('product', fn () => [
                'id'           => $this->product?->id,
                'ref_num'      => $this->product?->ref_num,
                'product_name' => $this->product?->product_name,
                'product_type' => $this->product?->product_type,
                'uom'          => $this->product?->uom,
            ]),

            // Related supplier
            'supplier' => $this->whenLoaded('supplier', fn () => [
                'id'            => $this->supplier?->id,
                'supplier_name' => $this->supplier?->supplier_name,
            ]),

            // Instrument set (when lot belongs to a set)
            'instrument_set' => $this->whenLoaded('instrumentSet', fn () => $this->instrumentSet ? [
                'id'        => $this->instrumentSet->id,
                'set_name'  => $this->instrumentSet->set_name ?? null,
            ] : null),

            // QR label — payload + timestamps (very useful for mobile scan confirmation)
            'qr_label' => $this->whenLoaded('qrLabel', fn () => $this->qrLabel ? [
                'id'           => $this->qrLabel->id,
                'qr_payload'   => $this->qrLabel->qr_payload,
                'generated_at' => $this->qrLabel->generated_at?->toIso8601String(),
            ] : null),

            // Holding record (present when status = holding)
            'lot_holding' => $this->whenLoaded('lotHolding', fn () => $this->lotHolding ? [
                'holding_reason' => $this->lotHolding->holding_reason,
                'assigned_at'    => $this->lotHolding->assigned_at?->toIso8601String(),
                'resolved_at'    => $this->lotHolding->resolved_at?->toIso8601String(),
            ] : null),

            // Movement timeline (present on detail / per-lot movements endpoint)
            'lot_movements' => $this->whenLoaded('lotMovements', fn () =>
                LotMovementResource::collection($this->lotMovements)
            ),

            // Count only (present on list view)
            'lot_movements_count' => $this->whenCounted('lotMovements'),
        ];
    }
}
