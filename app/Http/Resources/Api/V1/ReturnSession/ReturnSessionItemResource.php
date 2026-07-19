<?php

namespace App\Http\Resources\Api\V1\ReturnSession;

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
                    'product'     => $this->lot?->relationLoaded('product') ? [
                        'id'           => $this->lot->product?->id,
                        'ref_num'      => $this->lot->product?->ref_num,
                        'product_name' => $this->lot->product?->product_name,
                    ] : null,
                ];
            }),
            'instrument_set'       => $this->whenLoaded('instrumentSet', function () {
                return [
                    'id'       => $this->instrumentSet?->id,
                    'set_name' => $this->instrumentSet?->set_name,
                ];
            }),
            'product'              => $this->whenLoaded('product', function () {
                return [
                    'id'           => $this->product?->id,
                    'ref_num'      => $this->product?->ref_num,
                    'product_name' => $this->product?->product_name,
                ];
            }),
            'returned_at'          => $this->returned_at?->toIso8601String(),
            'returned_by_user_id'  => $this->returned_by_user_id,
            'source_qr_payload'    => $this->source_qr_payload,
            'remarks'              => $this->remarks,
            'quantity'             => $this->quantity,
            'used_quantity'        => $this->used_quantity,
            'damaged_quantity'     => $this->damaged_quantity,
            'missing_quantity'     => $this->missing_quantity,
            'instrument_results'   => $this->whenLoaded('setInstrumentItems', function () {
                return $this->setInstrumentItems->map(fn ($item) => [
                    'product_id'        => $item->product_id,
                    'returned_quantity' => $item->returned_quantity,
                    'remarks'           => $item->remarks,
                    'product'           => $item->relationLoaded('product') ? [
                        'id' => $item->product?->id,
                        'product_name' => $item->product?->product_name,
                        'ref_num' => $item->product?->ref_num,
                    ] : null,
                ]);
            }),
            'created_at'           => $this->created_at?->toIso8601String(),
        ];
    }
}
