<?php

namespace App\Services\Disposal;

use App\Enums\AuditAction;
use App\Exceptions\BusinessLogicException;
use App\Models\Disposal;
use App\Models\DisposalItem;
use App\Models\Lot;
use App\Models\LotMovement;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;

class DisposalCompleteService
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {
    }

    /**
     * Complete a draft disposal atomically:
     *   - Validates at least one item exists
     *   - Sets each lot status to `disposed`
     *   - Creates a LotMovement per lot
     *   - Marks disposal as `completed`
     *   - Writes audit log
     */
    public function complete(Disposal $disposal, User $actor): Disposal
    {
        return DB::transaction(function () use ($disposal, $actor) {
            /** @var Disposal $locked */
            $locked = Disposal::query()
                ->lockForUpdate()
                ->findOrFail($disposal->id);

            if ($locked->status !== 'draft') {
                throw new BusinessLogicException(
                    "Only draft disposals can be completed (current status: {$locked->status})."
                );
            }

            $items = DisposalItem::query()
                ->where('disposal_id', $locked->id)
                ->get();

            if ($items->isEmpty()) {
                throw new BusinessLogicException('Cannot complete a disposal with no items.');
            }

            foreach ($items as $item) {
                /** @var Lot $lot */
                $lot = Lot::query()->lockForUpdate()->findOrFail($item->lot_id);

                if (in_array($lot->status, ['disposed', 'returned_to_supplier'], true)) {
                    throw new BusinessLogicException(
                        "Lot [{$lot->lot_number}] is already {$lot->status}; cannot dispose."
                    );
                }

                $fromStatus = $lot->status;

                if ($item->quantity > $lot->quantity_available) {
                    throw new BusinessLogicException(
                        "Cannot dispose {$item->quantity} unit(s) — only {$lot->quantity_available} available for lot [{$lot->lot_number}]."
                    );
                }

                $lot->quantity_available -= $item->quantity;
                
                if ($lot->isFullyDepleted()) {
                    $lot->status = 'disposed';
                }
                
                $lot->save();

                LotMovement::query()->create([
                    'lot_id'               => $lot->id,
                    'movement_type'        => 'disposed',
                    'reference_type'       => Disposal::class,
                    'reference_id'         => $locked->id,
                    'from_status'          => $fromStatus,
                    'to_status'            => $lot->status,
                    'from_location_type'   => $lot->current_location_type,
                    'from_location_id'     => $lot->current_location_id,
                    'to_location_type'     => $lot->current_location_type,
                    'to_location_id'       => $lot->current_location_id,
                    'performed_at'         => now(),
                    'performed_by_user_id' => $actor->id,
                    'remarks'              => "Disposed via disposal record {$locked->disposal_no}: {$item->disposal_category} — {$item->reason_text}",
                    'quantity'             => $item->quantity,
                ]);
            }

            $locked->fill([
                'status'                => 'completed',
                'completed_at'          => now(),
                'completed_by_user_id'  => $actor->id,
            ])->save();

            $this->auditLogService->logEloquent(
                model:       $locked,
                actionType:  AuditAction::DISPOSAL_COMPLETED,
                actor:       $actor,
                description: "Disposal {$locked->disposal_no} completed — {$items->count()} lot(s) disposed.",
                after:       ['item_count' => $items->count()],
            );

            return $locked->refresh();
        });
    }
}
