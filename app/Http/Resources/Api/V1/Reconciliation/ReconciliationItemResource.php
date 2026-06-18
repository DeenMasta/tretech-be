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
            'result'             => $this->result,
            'remarks'            => $this->remarks,
            'instrument_results' => $this->whenLoaded('setInstrumentResults', function () {
                return $this->setInstrumentResults->map(fn ($item) => [
                    'set_instrument_id' => $item->set_instrument_id,
                    'product_id'        => $item->product_id,
                    'expected_quantity' => $item->expected_quantity,
                    'returned_quantity' => $item->returned_quantity,
                    'used_quantity'     => $item->used_quantity,
                    'missing_quantity'  => $item->missing_quantity,
                    'damaged_quantity'  => $item->damaged_quantity,
                    'result'            => $item->result,
                    'product'           => $item->relationLoaded('product') ? [
                        'id'           => $item->product?->id,
                        'product_name' => $item->product?->product_name,
                        'ref_num'      => $item->product?->ref_num,
                    ] : null,
                ]);
            }),
            'created_at'         => $this->created_at?->toIso8601String(),
        ];
    }
}
