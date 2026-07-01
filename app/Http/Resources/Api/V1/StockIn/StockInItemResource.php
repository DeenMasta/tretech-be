<?php

namespace App\Http\Resources\Api\V1\StockIn;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockInItemResource extends JsonResource
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
            'stock_in_id' => $this->stock_in_id,
            'entry_kind' => $this->entry_kind ?? 'product',
            'product_id' => $this->product_id,
            'product' => $this->whenLoaded('product', function () {
                if (!$this->product) {
                    return null;
                }

                return [
                    'id' => $this->product->id,
                    'ref_num' => $this->product->ref_num,
                    'product_name' => $this->product->product_name,
                ];
            }),
            'instrument_set_id' => $this->instrument_set_id,
            'instrument_set' => $this->whenLoaded('instrumentSet', function () {
                if (!$this->instrumentSet) {
                    return null;
                }

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

                return [
                    'id' => $this->instrumentSet->id,
                    'set_code' => $this->instrumentSet->set_code,
                    'set_name' => $this->instrumentSet->set_name,
                    'items' => $items->toArray(),
                ];
            }),
            'lot_id' => $this->lot_id,
            'lot' => $this->whenLoaded('lot', function () {
                return [
                    'id' => $this->lot?->id,
                    'lot_number' => $this->lot?->lot_number,
                    'status' => $this->lot?->status,
                ];
            }),
            'scanned_lot_number' => $this->scanned_lot_number,
            'quantity' => $this->quantity ?? 1,
            'manufacturing_date' => $this->manufacturing_date,
            'expiry_date' => $this->expiry_date?->format('Y-m-d'),
            'lot_entry_mode' => $this->lot_entry_mode,
            'expiry_entry_mode' => $this->expiry_entry_mode,
            'missing_lot_flag' => (bool) $this->missing_lot_flag,
            'source_barcode' => $this->source_barcode,
            'entry_override_reason' => $this->entry_override_reason,
            'remarks' => $this->remarks,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
