<?php

namespace App\Http\Resources\Api\V1\ReturnSession;

use App\Http\Resources\Api\V1\MasterData\SetInstrumentInstanceResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReturnSessionItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'return_session_id'    => $this->return_session_id,
            'lot_id'               => $this->lot_id,
            'lot'                  => $this->whenLoaded('lot', function () {
                return [
                    'id'          => $this->lot?->id,
                    'lot_number'  => $this->lot?->lot_number,
                    'status'      => $this->lot?->status,
                    'expiry_date' => $this->lot?->expiry_date?->toDateString(),
                    'set_instrument_instances' => $this->lot?->relationLoaded('setInstrumentInstances')
                        ? SetInstrumentInstanceResource::collection($this->lot->setInstrumentInstances)->resolve()
                        : [],
                    'product'     => $this->lot?->relationLoaded('product') ? [
                        'id'           => $this->lot->product?->id,
                        'ref_num'      => $this->lot->product?->ref_num,
                        'product_name' => $this->lot->product?->product_name,
                    ] : null,
                ];
            }),
            'returned_at'          => $this->returned_at?->toIso8601String(),
            'returned_by_user_id'  => $this->returned_by_user_id,
            'source_qr_payload'    => $this->source_qr_payload,
            'remarks'              => $this->remarks,
            'instrument_results'   => $this->whenLoaded('setInstrumentItems', function () {
                return $this->setInstrumentItems->map(fn ($item) => [
                    'set_instrument_id' => $item->set_instrument_id,
                    'product_id'        => $item->product_id,
                    'returned_quantity' => $item->returned_quantity,
                    'remarks'           => $item->remarks,
                ]);
            }),
            'created_at'           => $this->created_at?->toIso8601String(),
        ];
    }
}
