<?php

namespace App\Http\Resources\Api\V1\Consignment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConsignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if ($this->relationLoaded('consignmentItems')) {
            $componentLotNumbersByProduct = $this->getAttribute('component_lot_numbers_by_product') ?? [];
            foreach ($this->consignmentItems as $item) {
                $item->setAttribute('component_lot_numbers_by_product', $componentLotNumbersByProduct);
            }
        }

        return [
            'id'             => $this->id,
            'consignment_no' => $this->consignment_no,
            'status'         => $this->status,
            'client_id'      => $this->client_id,
            'client'         => $this->whenLoaded('client', function () {
                return [
                    'id'          => $this->client?->id,
                    'client_name' => $this->client?->client_name,
                ];
            }),
            'consignment_at' => $this->consignment_at?->toIso8601String(),
            'pic_user_id'    => $this->pic_user_id,
            'pic_user'       => $this->whenLoaded('picUser', function () {
                return [
                    'id'        => $this->picUser?->id,
                    'full_name' => $this->picUser?->full_name,
                ];
            }),
            'surgeon_name'   => $this->surgeon_name,
            'case_name'      => $this->case_name,
            'case_date'      => $this->case_date?->toIso8601String(),
            'remarks'        => $this->remarks,
            'confirmed_at'          => $this->confirmed_at?->toIso8601String(),
            'confirmed_by_user_id'  => $this->confirmed_by_user_id,
            'confirmed_by_user'     => $this->whenLoaded('confirmedByUser', function () {
                return [
                    'id'        => $this->confirmedByUser?->id,
                    'full_name' => $this->confirmedByUser?->full_name,
                ];
            }),
            'edited_after_confirmation'        => $this->edited_after_confirmation,
            'last_post_confirm_edit_at'         => $this->last_post_confirm_edit_at?->toIso8601String(),
            'last_post_confirm_edit_by_user_id' => $this->last_post_confirm_edit_by_user_id,
            'last_post_confirm_edit_by_user'    => $this->whenLoaded('lastPostConfirmEditByUser', function () {
                return [
                    'id'        => $this->lastPostConfirmEditByUser?->id,
                    'full_name' => $this->lastPostConfirmEditByUser?->full_name,
                ];
            }),
            'last_post_confirm_edit_reason' => $this->last_post_confirm_edit_reason,
            'items_count' => $this->whenCounted('consignmentItems'),
            'items'       => $this->whenLoaded('consignmentItems', fn () =>
                ConsignmentItemResource::collection($this->consignmentItems)
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
