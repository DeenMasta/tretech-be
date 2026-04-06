<?php

namespace App\Http\Resources\Api\V1\QrLabel;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QrLabelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'lot_id'                => $this->lot_id,
            'qr_payload'            => $this->qr_payload,
            'generated_at'          => $this->generated_at?->toIso8601String(),
            'generated_by_user_id'  => $this->generated_by_user_id,
            'created_at'            => $this->created_at?->toIso8601String(),

            // Eager-loaded lot summary (when available)
            'lot' => $this->when($this->relationLoaded('lot'), function () {
                $lot = $this->lot;
                return [
                    'id'                  => $lot->id,
                    'lot_number'          => $lot->lot_number,
                    'supplier_batch_code' => $lot->supplier_batch_code,
                    'expiry_date'         => $lot->expiry_date?->format('Y-m-d'),
                    'status'              => $lot->status,
                ];
            }),
        ];
    }
}
