<?php

namespace App\Http\Resources\Api\V1\Reconciliation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReconciliationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $componentLotNumbersByProduct = [];

        if ($this->relationLoaded('componentConsignmentMovements')) {
            $componentLotNumbersByProduct = $this->componentConsignmentMovements
                ->groupBy(fn ($movement) => $movement->lot?->product_id)
                ->map(fn ($movements) => $movements
                    ->pluck('lot.lot_number')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all())
                ->all();
        }

        // Compute summary from loaded items
        $summary = null;
        if ($this->relationLoaded('reconciliationItems')) {
            $items    = $this->reconciliationItems;
            $returned = $items->where('result', 'returned')->count();
            $used     = $items->where('result', 'used')->count();
            $summary  = [
                'total_consigned' => $returned + $used,
                'total_returned'  => $returned,
                'total_used'      => $used,
            ];

            foreach ($items as $reconciliationItem) {
                if (!$reconciliationItem->relationLoaded('setInstrumentResults')) {
                    continue;
                }

                $parentLotNumber = $reconciliationItem->relationLoaded('lot')
                    ? $reconciliationItem->lot?->lot_number
                    : null;

                foreach ($reconciliationItem->setInstrumentResults as $component) {
                    $lotNumbers = $componentLotNumbersByProduct[$component->product_id] ?? [];

                    // Physical set instances have one set lot rather than a
                    // dedicated lot for every component.
                    if ($lotNumbers === [] && $parentLotNumber) {
                        $lotNumbers = [$parentLotNumber];
                    }

                    $component->setAttribute('lot_numbers', $lotNumbers);
                }
            }
        }

        return [
            'id'                  => $this->id,
            'reconciliation_no'   => $this->reconciliation_no,
            'status'              => $this->status,
            'consignment_id'      => $this->consignment_id,
            'consignment'         => $this->whenLoaded('consignment', function () {
                return [
                    'id'             => $this->consignment?->id,
                    'consignment_no' => $this->consignment?->consignment_no,
                ];
            }),
            'return_session_id'   => $this->return_session_id,
            'return_session'      => $this->whenLoaded('returnSession', function () {
                return [
                    'id'                => $this->returnSession?->id,
                    'return_session_no' => $this->returnSession?->return_session_no,
                ];
            }),
            'pic_user_id'         => $this->pic_user_id,
            'pic_user'            => $this->whenLoaded('picUser', function () {
                return [
                    'id'        => $this->picUser?->id,
                    'full_name' => $this->picUser?->full_name,
                ];
            }),
            'remarks'              => $this->remarks,
            'completed_at'         => $this->completed_at?->toIso8601String(),
            'completed_by_user_id' => $this->completed_by_user_id,
            'completed_by_user'    => $this->whenLoaded('completedByUser', function () {
                return [
                    'id'        => $this->completedByUser?->id,
                    'full_name' => $this->completedByUser?->full_name,
                ];
            }),
            'reopened_at'          => $this->reopened_at?->toIso8601String(),
            'reopened_by_user_id'  => $this->reopened_by_user_id,
            'reopened_by_user'     => $this->whenLoaded('reopenedByUser', function () {
                return [
                    'id'        => $this->reopenedByUser?->id,
                    'full_name' => $this->reopenedByUser?->full_name,
                ];
            }),
            'reopen_reason'       => $this->reopen_reason,
            'summary'             => $summary,
            'items_count'         => $this->whenCounted('reconciliationItems'),
            'items'               => $this->whenLoaded('reconciliationItems', fn () =>
                ReconciliationItemResource::collection($this->reconciliationItems)
            ),
            'created_at'          => $this->created_at?->toIso8601String(),
            'updated_at'          => $this->updated_at?->toIso8601String(),
        ];
    }
}
