<?php

namespace App\Http\Resources\Api\V1\MasterData;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstrumentSetResource extends JsonResource
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
            'set_code' => $this->set_code,
            'set_name' => $this->set_name,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'items_count' => $this->whenCounted('instrumentSetItems'),
            'instruments_count' => $this->whenCounted('setInstruments'),
            'items' => $this->whenLoaded('instrumentSetItems', fn () => InstrumentSetItemResource::collection($this->instrumentSetItems)),
            'instruments' => $this->whenLoaded('setInstruments', fn () => SetInstrumentResource::collection($this->setInstruments)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
