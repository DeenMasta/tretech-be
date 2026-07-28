<?php

namespace App\Http\Resources\Api\V1\Consignment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConsignmentItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'consignment_id'    => $this->consignment_id,
            'entry_kind'        => $this->entry_kind ?? 'lot',
            'lot_id'            => $this->lot_id,
            'instrument_set_id' => $this->instrument_set_id,
            'lot'               => $this->whenLoaded('lot', function () {
                return [
                    'id'           => $this->lot?->id,
                    'lot_number'   => $this->lot?->lot_number,
                    'status'       => $this->lot?->status,
                    'expiry_date'  => $this->lot?->expiry_date?->toDateString(),
                    'product'      => $this->lot?->relationLoaded('product') ? [
                        'id'           => $this->lot->product?->id,
                        'ref_num'      => $this->lot->product?->ref_num,
                        'product_name' => $this->lot->product?->product_name,
                        'product_type' => $this->lot->product?->product_type,
                    ] : null,
                ];
            }),
            // Instrument set (when consignment item is a set entry)
            'instrument_set' => $this->whenLoaded('instrumentSet', function () {
                if (!$this->instrumentSet) {
                    return null;
                }

                $items = [];
                $componentLotNumbersByProduct = $this->getAttribute('component_lot_numbers_by_product') ?? [];
                if ($this->instrumentSet->relationLoaded('instrumentSetItems')) {
                    foreach ($this->instrumentSet->instrumentSetItems as $inst) {
                        $items[] = [
                            'id'           => $inst->id,
                            'product_id'   => $inst->product_id,
                            'product_name' => $inst->product?->product_name ?? 'Unknown',
                            'ref_num'      => $inst->product?->ref_num,
                            'quantity'     => $inst->quantity,
                            'lot_numbers'  => $componentLotNumbersByProduct[$inst->product_id] ?? [],
                        ];
                    }
                }

                return [
                    'id'               => $this->instrumentSet->id,
                    'set_code'         => $this->instrumentSet->set_code,
                    'set_name'         => $this->instrumentSet->set_name,
                    'is_active'        => (bool) $this->instrumentSet->is_active,
                    'components'       => $items,
                ];
            }),
            'issued_at'             => $this->issued_at?->toIso8601String(),
            'issued_by_user_id'     => $this->issued_by_user_id,
            'remarks'               => $this->remarks,
            'proposed_quantity'     => $this->proposed_quantity,
            'quantity'              => $this->quantity,
            'created_at'            => $this->created_at?->toIso8601String(),
        ];
    }
}
