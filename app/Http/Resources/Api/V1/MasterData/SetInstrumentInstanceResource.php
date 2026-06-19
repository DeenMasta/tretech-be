<?php

namespace App\Http\Resources\Api\V1\MasterData;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SetInstrumentInstanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lot_id' => $this->lot_id,
            'instrument_set_id' => $this->instrument_set_id,
            'set_instrument_id' => $this->set_instrument_id,
            'stock_in_id' => $this->stock_in_id,
            'stock_in_item_id' => $this->stock_in_item_id,
            'instance_number' => $this->instance_number,
            'status' => $this->status,
            'remarks' => $this->remarks,
            'set_instrument' => $this->whenLoaded('setInstrument', function () {
                return [
                    'id' => $this->setInstrument?->id,
                    'code' => $this->setInstrument?->code,
                    'name' => $this->setInstrument?->name,
                ];
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
