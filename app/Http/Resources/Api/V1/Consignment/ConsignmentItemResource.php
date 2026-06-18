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
                    ] : null,
                ];
            }),
            'instrument_set'        => $this->whenLoaded('instrumentSet', function () {
                $items = collect();

                if ($this->instrumentSet->relationLoaded('instrumentSetItems')) {
                    foreach ($this->instrumentSet->instrumentSetItems as $item) {
                        $items->push([
                            'id' => $item->id,
                            'name' => $item->product?->product_name ?? 'Unknown Product',
                            'code' => $item->product?->ref_num,
                            'quantity' => $item->quantity,
                            'type' => 'product',
                        ]);
                    }
                }

                if ($this->instrumentSet->relationLoaded('setInstruments')) {
                    foreach ($this->instrumentSet->setInstruments as $inst) {
                        $items->push([
                            'id' => $inst->id,
                            'name' => $inst->name,
                            'code' => $inst->code,
                            'quantity' => $inst->quantity,
                            'type' => 'instrument',
                        ]);
                    }
                }

                return [
                    'id'       => $this->instrumentSet?->id,
                    'set_code' => $this->instrumentSet?->set_code,
                    'set_name' => $this->instrumentSet?->set_name,
                    'items'    => $items->toArray(),
                ];
            }),
            'issued_at'             => $this->issued_at?->toIso8601String(),
            'issued_by_user_id'     => $this->issued_by_user_id,
            'remarks'               => $this->remarks,
            'created_at'            => $this->created_at?->toIso8601String(),
        ];
    }
}
