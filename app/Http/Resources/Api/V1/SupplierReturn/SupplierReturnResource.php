<?php

namespace App\Http\Resources\Api\V1\SupplierReturn;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierReturnResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                        => $this->id,
            'supplier_return_no'        => $this->supplier_return_no,
            'status'                    => $this->status,
            'supplier_id'               => $this->supplier_id,
            'supplier'                  => $this->whenLoaded('supplier', fn () => [
                'id'            => $this->supplier?->id,
                'supplier_name' => $this->supplier?->supplier_name,
            ]),
            'returned_at'               => $this->returned_at?->toIso8601String(),
            'reference_no'              => $this->reference_no,
            'remarks'                   => $this->remarks,
            'pic_user_id'               => $this->pic_user_id,
            'pic_user'                  => $this->whenLoaded('picUser', fn () => [
                'id'        => $this->picUser?->id,
                'full_name' => $this->picUser?->full_name,
            ]),
            'completed_at'              => $this->completed_at?->toIso8601String(),
            'completed_by_user_id'      => $this->completed_by_user_id,
            'completed_by_user'         => $this->whenLoaded('completedByUser', fn () => [
                'id'        => $this->completedByUser?->id,
                'full_name' => $this->completedByUser?->full_name,
            ]),
            'supplier_return_items_count' => $this->whenCounted('supplierReturnItems'),
            'supplier_return_items'       => $this->whenLoaded(
                'supplierReturnItems',
                fn () => SupplierReturnItemResource::collection($this->supplierReturnItems)
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
