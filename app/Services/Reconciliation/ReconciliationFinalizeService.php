<?php

namespace App\Services\Reconciliation;

use App\Enums\AuditAction;
use App\Exceptions\BusinessLogicException;
use App\Models\ConsignmentItem;
use App\Models\Lot;
use App\Models\LotMovement;
use App\Models\Reconciliation;
use App\Models\ReconciliationItem;
use App\Models\ReturnSessionItem;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\UsageSummary\UsageSummaryGenerateService;
use Illuminate\Support\Facades\DB;

class ReconciliationFinalizeService
{
    public function __construct(
        private readonly AuditLogService             $auditLogService,
        private readonly UsageSummaryGenerateService $usageSummaryGenerateService,
    ) {
    }

    /**
     * Finalize reconciliation:
     *   Used = Consigned − Returned
     *
     * - Returned lots → status `available`, location `warehouse`
     * - Used lots     → status `used` (locked permanently)
     * - All movements recorded; reconciliation items created.
     */
    public function finalize(Reconciliation $reconciliation, User $actor): Reconciliation
    {
        return DB::transaction(function () use ($reconciliation, $actor) {
            /** @var Reconciliation $locked */
            $locked = Reconciliation::query()
                ->lockForUpdate()
                ->findOrFail($reconciliation->id);

            if (!in_array($locked->status, ['pending', 'reopened'], true)) {
                throw new BusinessLogicException(
                    "Only pending or reopened reconciliations can be finalized (current: {$locked->status})."
                );
            }

            // ----------------------------------------------------------------
            // 1. Collect consigned lot IDs
            // ----------------------------------------------------------------
            $consignedLotIds = ConsignmentItem::query()
                ->where('consignment_id', $locked->consignment_id)
                ->pluck('lot_id')
                ->all();

            if (empty($consignedLotIds)) {
                throw new BusinessLogicException('No consigned lots found for this reconciliation.');
            }

            // ----------------------------------------------------------------
            // 2. Collect returned lot IDs
            // ----------------------------------------------------------------
            $returnedLotIds = ReturnSessionItem::query()
                ->where('return_session_id', $locked->return_session_id)
                ->pluck('lot_id')
                ->all();

            // ----------------------------------------------------------------
            // 3. Compute used = consigned − returned
            // ----------------------------------------------------------------
            $returnedSet = array_flip($returnedLotIds);
            $usedLotIds  = array_values(
                array_filter($consignedLotIds, fn ($id) => !isset($returnedSet[$id]))
            );

            // ----------------------------------------------------------------
            // 4. Remove any previous reconciliation items (re-finalization after reopen)
            // ----------------------------------------------------------------
            ReconciliationItem::query()
                ->where('reconciliation_id', $locked->id)
                ->delete();

            // ----------------------------------------------------------------
            // 5. Process RETURNED lots → back to available
            // ----------------------------------------------------------------
            foreach ($returnedLotIds as $lotId) {
                $lot = Lot::query()->lockForUpdate()->findOrFail($lotId);

                LotMovement::query()->create([
                    'lot_id'               => $lot->id,
                    'movement_type'        => 'returned',
                    'reference_type'       => Reconciliation::class,
                    'reference_id'         => $locked->id,
                    'from_status'          => $lot->status,
                    'to_status'            => 'available',
                    'from_location_type'   => $lot->current_location_type,
                    'from_location_id'     => $lot->current_location_id,
                    'to_location_type'     => 'warehouse',
                    'to_location_id'       => null,
                    'performed_at'         => now(),
                    'performed_by_user_id' => $actor->id,
                    'remarks'              => "Returned via reconciliation {$locked->reconciliation_no}",
                ]);

                $lot->fill([
                    'status'               => 'available',
                    'current_location_type' => 'warehouse',
                    'current_location_id'   => null,
                ])->save();

                ReconciliationItem::query()->create([
                    'reconciliation_id' => $locked->id,
                    'lot_id'            => $lot->id,
                    'result'            => 'returned',
                    'remarks'           => null,
                ]);
            }

            // ----------------------------------------------------------------
            // 6. Process USED lots → status `used` (permanently locked)
            // ----------------------------------------------------------------
            foreach ($usedLotIds as $lotId) {
                $lot = Lot::query()->lockForUpdate()->findOrFail($lotId);

                LotMovement::query()->create([
                    'lot_id'               => $lot->id,
                    'movement_type'        => 'used',
                    'reference_type'       => Reconciliation::class,
                    'reference_id'         => $locked->id,
                    'from_status'          => $lot->status,
                    'to_status'            => 'used',
                    'from_location_type'   => $lot->current_location_type,
                    'from_location_id'     => $lot->current_location_id,
                    'to_location_type'     => $lot->current_location_type,
                    'to_location_id'       => $lot->current_location_id,
                    'performed_at'         => now(),
                    'performed_by_user_id' => $actor->id,
                    'remarks'              => "Marked used via reconciliation {$locked->reconciliation_no}",
                ]);

                $lot->fill(['status' => 'used'])->save();

                ReconciliationItem::query()->create([
                    'reconciliation_id' => $locked->id,
                    'lot_id'            => $lot->id,
                    'result'            => 'used',
                    'remarks'           => null,
                ]);
            }

            // ----------------------------------------------------------------
            // 7. Set reconciliation to finalized
            // ----------------------------------------------------------------
            $locked->fill([
                'status'               => 'finalized',
                'completed_at'         => now(),
                'completed_by_user_id' => $actor->id,
            ])->save();

            $this->auditLogService->logModelAction(
                auditableType: Reconciliation::class,
                auditableId:   $locked->id,
                actionType:    AuditAction::RECONCILIATION_FINALIZED,
                actor:         $actor,
                description:   sprintf(
                    'Reconciliation %s finalized — %d used, %d returned.',
                    $locked->reconciliation_no,
                    count($usedLotIds),
                    count($returnedLotIds)
                ),
                after: [
                    'status'         => 'finalized',
                    'completed_at'   => now()->toIso8601String(),
                    'total_consigned' => count($consignedLotIds),
                    'total_returned' => count($returnedLotIds),
                    'total_used'     => count($usedLotIds),
                ],
            );

            $refreshed = $locked->refresh()->load([
                'consignment:id,consignment_no',
                'returnSession:id,return_session_no',
                'picUser:id,full_name',
                'completedByUser:id,full_name',
                'reconciliationItems.lot:id,lot_number,status',
            ]);

            // Auto-generate usage summary now that reconciliation is finalized
            $this->usageSummaryGenerateService->generate($refreshed, $actor);

            return $refreshed;
        });
    }
}
