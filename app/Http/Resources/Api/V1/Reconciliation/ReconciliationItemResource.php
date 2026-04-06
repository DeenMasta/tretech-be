<?php

namespace App\Http\Resources\Api\V1\Reconciliation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReconciliationItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'reconciliation_id' => $this->reconciliation_id,
            'lot_id'            => $this->lot_id,
            'lot'               => $this->whenLoaded('lot', function () {
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
            'result'     => $this->result,
            'remarks'    => $this->remarks,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
