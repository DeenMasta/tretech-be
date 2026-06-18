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
use Illuminate\Support\Facades\DB;

class ReconciliationFinalizeService
{
    public function __construct(
        private readonly AuditLogService             $auditLogService,
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
            // 1. Collect consigned items
            // ----------------------------------------------------------------
            $consignedItems = ConsignmentItem::query()
                ->where('consignment_id', $locked->consignment_id)
                ->get();

            if ($consignedItems->isEmpty()) {
                throw new BusinessLogicException('No consigned items found for this reconciliation.');
            }

            $consignedKeys = [];
            foreach ($consignedItems as $ci) {
                if ($ci->lot_id) {
                    $consignedKeys[] = 'lot_' . $ci->lot_id;
                } elseif ($ci->instrument_set_id) {
                    $consignedKeys[] = 'set_' . $ci->instrument_set_id;
                } elseif ($ci->product_id) {
                    $consignedKeys[] = 'prod_' . $ci->product_id;
                }
            }

            // ----------------------------------------------------------------
            // 2. Collect returned items
            // ----------------------------------------------------------------
            $returnSessionItems = ReturnSessionItem::query()
                ->with('setInstrumentItems')
                ->where('return_session_id', $locked->return_session_id)
                ->get();
            
            $returnedKeys = [];
            $returnSessionItemsByKey = collect();
            foreach ($returnSessionItems as $rsi) {
                if ($rsi->lot_id) {
                    $key = 'lot_' . $rsi->lot_id;
                    $returnedKeys[] = $key;
                    $returnSessionItemsByKey->put($key, $rsi);
                } elseif ($rsi->instrument_set_id) {
                    $key = 'set_' . $rsi->instrument_set_id;
                    $returnedKeys[] = $key;
                    $returnSessionItemsByKey->put($key, $rsi);
                } elseif ($rsi->product_id) {
                    $key = 'prod_' . $rsi->product_id;
                    $returnedKeys[] = $key;
                    $returnSessionItemsByKey->put($key, $rsi);
                }
            }

            // ----------------------------------------------------------------
            // 3. Compute used = consigned − returned
            // ----------------------------------------------------------------
            $returnedSet = array_flip($returnedKeys);
            $usedKeys  = array_values(
                array_filter($consignedKeys, fn ($key) => !isset($returnedSet[$key]))
            );

            // ----------------------------------------------------------------
            // 4. Remove any previous reconciliation items (re-finalization after reopen)
            // ----------------------------------------------------------------
            ReconciliationItem::query()
                ->where('reconciliation_id', $locked->id)
                ->delete();

            // ----------------------------------------------------------------
            // 5. Process RETURNED items
            // ----------------------------------------------------------------
            foreach ($returnedKeys as $key) {
                $returnItem = $returnSessionItemsByKey->get($key);

                if (str_starts_with($key, 'lot_')) {
                    $lotId = (int) substr($key, 4);
                    $lot = Lot::query()->lockForUpdate()->findOrFail($lotId);

                    LotMovement::query()->create([
                        'lot_id'               => $lot->id,
                        'movement_type'        => 'returned',
                        'reference_type'       => Reconciliation::class,
                        'reference_id'         => $locked->id,
                        'from_status'          => $lot->status,
                        'to_status'            => 'holding',
                        'from_location_type'   => $lot->current_location_type,
                        'from_location_id'     => $lot->current_location_id,
                        'to_location_type'     => 'warehouse',
                        'to_location_id'       => null,
                        'performed_at'         => now(),
                        'performed_by_user_id' => $actor->id,
                        'remarks'              => "Returned via reconciliation {$locked->reconciliation_no}",
                    ]);

                    \App\Models\LotHolding::query()->create([
                        'lot_id'              => $lot->id,
                        'holding_reason'      => 'Pending inspection after return',
                        'assigned_at'         => now(),
                        'assigned_by_user_id' => $actor->id,
                    ]);

                    $lot->fill([
                        'status'               => 'holding',
                        'current_location_type' => 'warehouse',
                        'current_location_id'   => null,
                    ])->save();

                    $reconItem = ReconciliationItem::query()->create([
                        'reconciliation_id' => $locked->id,
                        'lot_id'            => $lot->id,
                        'result'            => 'returned',
                        'remarks'           => null,
                    ]);

                    if ($lot->isSetInstance() && $returnItem) {
                        $instrumentSet = $lot->instrumentSet()->with(['items.product', 'setInstruments'])->first();
                        $this->processSetInstrumentResults($reconItem, $returnItem, $instrumentSet);
                    }
                } elseif (str_starts_with($key, 'set_')) {
                    $setId = (int) substr($key, 4);
                    $reconItem = ReconciliationItem::query()->create([
                        'reconciliation_id' => $locked->id,
                        'instrument_set_id' => $setId,
                        'result'            => 'returned',
                        'remarks'           => null,
                    ]);
                    
                    $instrumentSet = \App\Models\InstrumentSet::with(['items.product', 'setInstruments'])->find($setId);
                    $this->processSetInstrumentResults($reconItem, $returnItem, $instrumentSet);
                } elseif (str_starts_with($key, 'prod_')) {
                    $productId = (int) substr($key, 5);
                    ReconciliationItem::query()->create([
                        'reconciliation_id' => $locked->id,
                        'product_id'        => $productId,
                        'result'            => 'returned',
                        'remarks'           => null,
                    ]);
                }
            }

            // ----------------------------------------------------------------
            // 6. Process USED items
            // ----------------------------------------------------------------
            foreach ($usedKeys as $key) {
                if (str_starts_with($key, 'lot_')) {
                    $lotId = (int) substr($key, 4);
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

                    $reconItem = ReconciliationItem::query()->create([
                        'reconciliation_id' => $locked->id,
                        'lot_id'            => $lot->id,
                        'result'            => 'used',
                        'remarks'           => null,
                    ]);

                    if ($lot->isSetInstance()) {
                        $instrumentSet = $lot->instrumentSet()->with(['items.product', 'setInstruments'])->first();
                        $this->processUsedSetInstrumentResults($reconItem, $instrumentSet);
                    }
                } elseif (str_starts_with($key, 'set_')) {
                    $setId = (int) substr($key, 4);
                    $reconItem = ReconciliationItem::query()->create([
                        'reconciliation_id' => $locked->id,
                        'instrument_set_id' => $setId,
                        'result'            => 'used',
                        'remarks'           => null,
                    ]);
                    
                    $instrumentSet = \App\Models\InstrumentSet::with(['items.product', 'setInstruments'])->find($setId);
                    $this->processUsedSetInstrumentResults($reconItem, $instrumentSet);
                } elseif (str_starts_with($key, 'prod_')) {
                    $productId = (int) substr($key, 5);
                    ReconciliationItem::query()->create([
                        'reconciliation_id' => $locked->id,
                        'product_id'        => $productId,
                        'result'            => 'used',
                        'remarks'           => null,
                    ]);
                }
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
                    count($usedKeys),
                    count($returnedKeys)
                ),
                after: [
                    'status'         => 'finalized',
                    'completed_at'   => now()->toIso8601String(),
                    'total_consigned' => count($consignedKeys),
                    'total_returned' => count($returnedKeys),
                    'total_used'     => count($usedKeys),
                ],
            );

            $refreshed = $locked->refresh()->load([
                'consignment:id,consignment_no',
                'returnSession:id,return_session_no',
                'picUser:id,full_name',
                'completedByUser:id,full_name',
                'reconciliationItems.lot:id,lot_number,status',
            ]);

            return $refreshed;
        });
    }

    private function processSetInstrumentResults($reconItem, $returnItem, $instrumentSet): void
    {
        $returnedMap = [];
        foreach ($returnItem->setInstrumentItems as $sii) {
            $key = $sii->set_instrument_id ? 'si_'.$sii->set_instrument_id : 'p_'.$sii->product_id;
            $returnedMap[$key] = $sii->returned_quantity;
        }

        // Process Non-Product Instruments
        if ($instrumentSet && $instrumentSet->setInstruments) {
            foreach ($instrumentSet->setInstruments as $si) {
                $expected = $si->pivot->quantity;
                $returned = $returnedMap['si_'.$si->id] ?? 0;
                $used = max(0, $expected - $returned);

                $reconItem->setInstrumentResults()->create([
                    'set_instrument_id' => $si->id,
                    'product_id'        => null,
                    'expected_quantity' => $expected,
                    'returned_quantity' => $returned,
                    'used_quantity'     => $used,
                    'missing_quantity'  => 0,
                    'damaged_quantity'  => 0,
                    'result'            => $used > 0 ? 'partial' : 'returned',
                ]);
            }
        }

        // Process Product Instruments
        if ($instrumentSet && $instrumentSet->items) {
            foreach ($instrumentSet->items as $productItem) {
                $expected = $productItem->quantity;
                $returned = $returnedMap['p_'.$productItem->product_id] ?? 0;
                $used = max(0, $expected - $returned);

                $reconItem->setInstrumentResults()->create([
                    'set_instrument_id' => null,
                    'product_id'        => $productItem->product_id,
                    'expected_quantity' => $expected,
                    'returned_quantity' => $returned,
                    'used_quantity'     => $used,
                    'missing_quantity'  => 0,
                    'damaged_quantity'  => 0,
                    'result'            => $used > 0 ? 'partial' : 'returned',
                ]);
            }
        }
    }

    private function processUsedSetInstrumentResults($reconItem, $instrumentSet): void
    {
        if ($instrumentSet && $instrumentSet->setInstruments) {
            foreach ($instrumentSet->setInstruments as $si) {
                $expected = $si->pivot->quantity;
                $reconItem->setInstrumentResults()->create([
                    'set_instrument_id' => $si->id,
                    'product_id'        => null,
                    'expected_quantity' => $expected,
                    'returned_quantity' => 0,
                    'used_quantity'     => $expected,
                    'missing_quantity'  => 0,
                    'damaged_quantity'  => 0,
                    'result'            => 'used',
                ]);
            }
        }

        if ($instrumentSet && $instrumentSet->items) {
            foreach ($instrumentSet->items as $productItem) {
                $expected = $productItem->quantity;
                $reconItem->setInstrumentResults()->create([
                    'set_instrument_id' => null,
                    'product_id'        => $productItem->product_id,
                    'expected_quantity' => $expected,
                    'returned_quantity' => 0,
                    'used_quantity'     => $expected,
                    'missing_quantity'  => 0,
                    'damaged_quantity'  => 0,
                    'result'            => 'used',
                ]);
            }
        }
    }
}
