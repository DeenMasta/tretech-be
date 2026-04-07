<?php

namespace App\Http\Resources\Api\V1\UsageSummary;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UsageSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'summary_no'      => $this->summary_no,
            'status'          => $this->status,
            'generated_at'    => $this->generated_at?->toIso8601String(),
            'generated_by'    => $this->whenLoaded('generatedByUser', fn () => [
                'id'        => $this->generatedByUser->id,
                'full_name' => $this->generatedByUser->full_name,
            ]),
            'reconciliation'  => $this->whenLoaded('reconciliation', fn () => [
                'id'                 => $this->reconciliation->id,
                'reconciliation_no'  => $this->reconciliation->reconciliation_no,
                'status'             => $this->reconciliation->status,
                'consignment_no'     => $this->reconciliation->consignment?->consignment_no,
                'client_name'        => $this->reconciliation->consignment?->client?->client_name,
            ]),
            'items_count'     => $this->whenLoaded(
                'usageSummaryItems',
                fn () => $this->usageSummaryItems->count(),
                $this->usageSummaryItems_count ?? null,
            ),
            'items'           => $this->whenLoaded('usageSummaryItems', fn () =>
                $this->usageSummaryItems->map(fn ($item) => [
                    'id'                       => $item->id,
                    'product'                  => [
                        'id'           => $item->product?->id,
                        'ref_num'      => $item->product?->ref_num,
                        'product_name' => $item->product?->product_name,
                        'uom'          => $item->product?->uom,
                    ],
                    'lot_number'               => $item->lot?->lot_number,
                    'batch_code'               => $item->lot?->supplier_batch_code,
                    'expiry_date'              => $item->lot?->expiry_date?->format('Y-m-d'),
                    'qty_consigned'            => $item->qty_consigned,
                    'qty_returned'             => $item->qty_returned,
                    'qty_used'                 => $item->qty_used,
                    'qty_disposed'             => $item->qty_disposed,
                    'qty_returned_to_supplier' => $item->qty_returned_to_supplier,
                ])
            ),
            'created_at'      => $this->created_at?->toIso8601String(),
            'updated_at'      => $this->updated_at?->toIso8601String(),
        ];
    }
}
