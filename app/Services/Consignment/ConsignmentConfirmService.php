<?php

namespace App\Services\Consignment;

use App\Enums\AuditAction;
use App\Exceptions\BusinessLogicException;
use App\Models\Consignment;
use App\Models\ConsignmentItem;
use App\Models\InstrumentSet;
use App\Models\Lot;
use App\Models\LotMovement;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;

class ConsignmentConfirmService
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {
    }

    public function confirm(Consignment $consignment, User $actor): Consignment
    {
        return DB::transaction(function () use ($consignment, $actor) {
            /** @var Consignment $locked */
            $locked = Consignment::query()
                ->lockForUpdate()
                ->with([
                    'consignmentItems.lot',
                    'consignmentItems.instrumentSet.instrumentSetItems',
                ])
                ->findOrFail($consignment->id);

            if ($locked->status !== 'draft') {
                throw new BusinessLogicException('Only draft consignments can be confirmed.');
            }

            if ($locked->consignmentItems->isEmpty()) {
                throw new BusinessLogicException('Cannot confirm an empty consignment note.');
            }

            // --- VALIDATION PASS ---
            foreach ($locked->consignmentItems as $item) {
                if ($item->isSetEntry()) {
                    // Validate that each component product has enough available stock (FIFO check)
                    $this->validateSetComponentStock($item, $locked->client_id);
                    continue;
                }

                $lot = $item->lot;

                if ($lot === null) {
                    throw new BusinessLogicException("Consignment item #{$item->id} has no linked lot.");
                }

                if ($lot->status !== 'available') {
                    throw new BusinessLogicException(
                        "Lot {$lot->lot_number} is not available for consignment (current status: {$lot->status})."
                    );
                }

                if (!$lot->hasAvailableStock($item->quantity)) {
                    throw new BusinessLogicException(
                        "Lot {$lot->lot_number} does not have enough stock (requested: {$item->quantity}, available: {$lot->quantity_available})."
                    );
                }
            }

            // --- DEDUCTION PASS ---
            foreach ($locked->consignmentItems as $item) {
                if ($item->isSetEntry()) {
                    // Auto FIFO-deduct from component product lots
                    $this->deductSetComponentStock($item, $locked, $actor);
                    continue;
                }

                $lot        = $item->lot;
                $fromStatus = $lot->status;

                $lot->quantity_available -= $item->quantity;
                $lot->quantity_consigned += $item->quantity;

                if ($lot->isFullyDepleted()) {
                    $lot->status = 'depleted';
                    $lot->current_location_type = 'client';
                    $lot->current_location_id   = $locked->client_id;
                }

                LotMovement::query()->create([
                    'lot_id'               => $lot->id,
                    'movement_type'        => 'consigned',
                    'reference_type'       => Consignment::class,
                    'reference_id'         => $locked->id,
                    'from_status'          => $fromStatus,
                    'to_status'            => $lot->status,
                    'from_location_type'   => $lot->current_location_type,
                    'from_location_id'     => $lot->current_location_id,
                    'to_location_type'     => 'client',
                    'to_location_id'       => $locked->client_id,
                    'performed_at'         => now(),
                    'performed_by_user_id' => $actor->id,
                    'remarks'              => "Consigned via {$locked->consignment_no}",
                    'quantity'             => $item->quantity,
                ]);

                $lot->save();
            }

            $locked->fill([
                'status'               => 'confirmed',
                'confirmed_at'         => now(),
                'confirmed_by_user_id' => $actor->id,
            ])->save();

            $this->auditLogService->logModelAction(
                auditableType: Consignment::class,
                auditableId:   $locked->id,
                actionType:    AuditAction::CONSIGNMENT_CONFIRMED,
                actor:         $actor,
                description:   sprintf(
                    'Consignment %s confirmed — %d item(s) supplied.',
                    $locked->consignment_no,
                    $locked->consignmentItems->count()
                ),
                after: [
                    'status'       => 'confirmed',
                    'confirmed_at' => now()->toIso8601String(),
                    'total_items'  => $locked->consignmentItems->count(),
                ],
            );

            return $locked->refresh()->load([
                'client:id,client_name',
                'picUser:id,full_name',
                'confirmedByUser:id,full_name',
                'consignmentItems.lot:id,lot_number,status',
            ]);
        });
    }

    /**
     * Validate that all blueprint components of a Set consignment item have
     * enough available stock across their lots (FIFO order).
     */
    private function validateSetComponentStock(ConsignmentItem $item, ?int $clientId): void
    {
        $set     = $item->instrumentSet;
        $setQty  = $item->quantity ?? 1;

        if (!$set || $set->instrumentSetItems->isEmpty()) {
            throw new BusinessLogicException(
                "Instrument set has no component products defined and cannot be consigned."
            );
        }

        foreach ($set->instrumentSetItems as $setItem) {
            $required   = $setItem->quantity * $setQty;
            $productId  = $setItem->product_id;

            // Sum available stock across all lots for this product (FIFO)
            $totalAvailable = Lot::query()
                ->where('product_id', $productId)
                ->where('quantity_available', '>', 0)
                ->sum('quantity_available');

            if ($totalAvailable < $required) {
                throw new BusinessLogicException(
                    "Insufficient stock for product ID {$productId} in set '{$set->set_name}': "
                    . "need {$required}, available {$totalAvailable}."
                );
            }
        }
    }

    /**
     * FIFO-deduct component product stock when a Set consignment item is confirmed.
     * Deducts from the oldest available lots first.
     */
    private function deductSetComponentStock(ConsignmentItem $item, Consignment $consignment, User $actor): void
    {
        $set    = $item->instrumentSet;
        $setQty = $item->quantity ?? 1;

        foreach ($set->instrumentSetItems as $setItem) {
            $remaining = $setItem->quantity * $setQty;
            $productId = $setItem->product_id;

            // FIFO: oldest lots first (order by received_at, then id)
            $lots = Lot::query()
                ->where('product_id', $productId)
                ->where('quantity_available', '>', 0)
                ->lockForUpdate()
                ->orderBy('received_at')
                ->orderBy('id')
                ->get();

            foreach ($lots as $lot) {
                if ($remaining <= 0) {
                    break;
                }

                $deduct     = min($remaining, $lot->quantity_available);
                $fromStatus = $lot->status;

                $lot->quantity_available -= $deduct;
                $lot->quantity_consigned += $deduct;

                if ($lot->isFullyDepleted()) {
                    $lot->status = 'depleted';
                    $lot->current_location_type = 'client';
                    $lot->current_location_id   = $consignment->client_id;
                }

                LotMovement::query()->create([
                    'lot_id'               => $lot->id,
                    'movement_type'        => 'consigned',
                    'reference_type'       => Consignment::class,
                    'reference_id'         => $consignment->id,
                    'from_status'          => $fromStatus,
                    'to_status'            => $lot->status,
                    'from_location_type'   => $lot->current_location_type, // Captures previous location
                    'from_location_id'     => $lot->current_location_id,
                    'to_location_type'     => 'client',
                    'to_location_id'       => $consignment->client_id,
                    'performed_at'         => now(),
                    'performed_by_user_id' => $actor->id,
                    'remarks'              => "Set component consigned via {$consignment->consignment_no} (set: {$set->set_name})",
                    'quantity'             => $deduct,
                ]);

                $lot->save();

                $remaining -= $deduct;
            }
        }
    }
}
