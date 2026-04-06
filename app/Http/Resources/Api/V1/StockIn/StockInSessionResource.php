<?php

namespace App\Http\Resources\Api\V1\StockIn;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockInSessionResource extends JsonResource
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
            'supplier_id' => $this->supplier_id,
            'supplier' => $this->whenLoaded('supplier', function () {
                return [
                    'id' => $this->supplier?->id,
                    'supplier_name' => $this->supplier?->supplier_name,
                ];
            }),
            'session_no' => $this->session_no,
            'do_number' => $this->do_number,
            'stock_in_at' => $this->stock_in_at?->toIso8601String(),
            'pic_user_id' => $this->pic_user_id,
            'pic_user' => $this->whenLoaded('picUser', function () {
                return [
                    'id' => $this->picUser?->id,
                    'full_name' => $this->picUser?->full_name,
                ];
            }),
            'status' => $this->status,
            'remarks' => $this->remarks,
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'confirmed_by_user_id' => $this->confirmed_by_user_id,
            'confirmed_by_user' => $this->whenLoaded('confirmedByUser', function () {
                return [
                    'id' => $this->confirmedByUser?->id,
                    'full_name' => $this->confirmedByUser?->full_name,
                ];
            }),
            'items_count' => $this->whenCounted('stockInItems'),
            'items' => $this->whenLoaded('stockInItems', fn () => StockInItemResource::collection($this->stockInItems)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
