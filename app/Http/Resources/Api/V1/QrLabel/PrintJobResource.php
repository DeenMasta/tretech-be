<?php

namespace App\Http\Resources\Api\V1\QrLabel;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrintJobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'lot_id'                => $this->lot_id,
            'qr_label_id'           => $this->qr_label_id,
            'action_type'           => $this->action_type,
            'reprint_reason'        => $this->reprint_reason,
            'status'                => $this->status,
            'printer_name'          => $this->printer_name,
            'device_id'             => $this->device_id,
            // TSPL payload included so the Flutter app can send it directly to the printer
            'tspl_payload'          => $this->tspl_payload,
            'error_message'         => $this->error_message,
            'requested_by_user_id'  => $this->requested_by_user_id,
            'requested_at'          => $this->requested_at?->toIso8601String(),
            'printed_at'            => $this->printed_at?->toIso8601String(),
            'failed_at'             => $this->failed_at?->toIso8601String(),
            'created_at'            => $this->created_at?->toIso8601String(),

            // Lot summary
            'lot' => $this->when($this->relationLoaded('lot'), function () {
                $lot = $this->lot;
                return [
                    'id'         => $lot->id,
                    'lot_number' => $lot->lot_number,
                    'product'    => $this->when($lot->relationLoaded('product'), function () use ($lot) {
                        return [
                            'id'           => $lot->product?->id,
                            'ref_num'      => $lot->product?->ref_num,
                            'product_name' => $lot->product?->product_name,
                        ];
                    }),
                ];
            }),

            // Requester info
            'requested_by' => $this->when($this->relationLoaded('requestedByUser'), function () {
                return [
                    'id'        => $this->requestedByUser?->id,
                    'full_name' => $this->requestedByUser?->full_name,
                ];
            }),
        ];
    }
}
