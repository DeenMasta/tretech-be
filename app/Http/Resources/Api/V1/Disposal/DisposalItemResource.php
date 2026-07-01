<?php

namespace App\Http\Resources\Api\V1\Disposal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DisposalItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'disposal_id'       => $this->disposal_id,
            'lot_id'            => $this->lot_id,
            'lot'               => $this->whenLoaded('lot', function () {
                return [
                    'id'                  => $this->lot?->id,
                    'lot_number'          => $this->lot?->lot_number,
                    'manufacturing_date' => $this->lot?->manufacturing_date,
                    'expiry_date'         => $this->lot?->expiry_date?->format('Y-m-d'),
                    'status'              => $this->lot?->status,
                    'product'             => $this->lot?->relationLoaded('product') ? [
                        'id'           => $this->lot->product?->id,
                        'ref_num'      => $this->lot->product?->ref_num,
                        'product_name' => $this->lot->product?->product_name,
                    ] : null,
                    'supplier'            => $this->lot?->relationLoaded('supplier') ? [
                        'id'            => $this->lot->supplier?->id,
                        'supplier_name' => $this->lot->supplier?->supplier_name,
                    ] : null,
                ];
            }),
            'disposal_category' => $this->disposal_category,
            'reason_text'       => $this->reason_text,
            'remarks'           => $this->remarks,
            'created_at'        => $this->created_at?->toIso8601String(),
        ];
    }
}
