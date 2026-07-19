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
            $usedItemCount = 0;
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
            // 3. Process Consigned Items (Handle used & returned quantities)
            // ----------------------------------------------------------------
            $returnSessionItemsByKey = collect();
            $returnSessionItems = ReturnSessionItem::query()
                ->with('setInstrumentItems')
                ->where('return_session_id', $locked->return_session_id)
                ->get();

            foreach ($returnSessionItems as $rsi) {
                if ($rsi->lot_id) {
                    $returnSessionItemsByKey->put('lot_' . $rsi->lot_id, $rsi);
                } elseif ($rsi->instrument_set_id) {
                    $returnSessionItemsByKey->put('set_' . $rsi->instrument_set_id, $rsi);
                } elseif ($rsi->product_id) {
                    $returnSessionItemsByKey->put('prod_' . $rsi->product_id, $rsi);
                }
            }

            ReconciliationItem::query()
                ->where('reconciliation_id', $locked->id)
                ->delete();

            foreach ($consignedItems as $ci) {
                $key = null;
                if ($ci->lot_id) {
                    $key = 'lot_' . $ci->lot_id;
                } elseif ($ci->instrument_set_id) {
                    $key = 'set_' . $ci->instrument_set_id;
                } elseif ($ci->product_id) {
                    $key = 'prod_' . $ci->product_id;
                }

                if (!$key) continue;

                $returnItem = $returnSessionItemsByKey->get($key);
                $consignedQty = $ci->quantity ?? 1;
                
                $returnedQty = 0;
                $usedQty = 0;
                $damagedQty = 0;
                $missingQty = 0;

                if ($returnItem) {
                    $returnedQty = $returnItem->quantity ?? 0;
                    $usedQty = $returnItem->used_quantity ?? 0;
                    $damagedQty = $returnItem->damaged_quantity ?? 0;
                    $missingQty = $returnItem->missing_quantity ?? 0;

                    // Fallback for non-instrument products where UI only sends returned quantity
                    if ($usedQty === 0 && $damagedQty === 0 && $missingQty === 0 && $returnedQty < $consignedQty) {
                        $usedQty = max(0, $consignedQty - $returnedQty);
                    }
                } else {
                    $returnedQty = 0;
                    $usedQty = $consignedQty;
                }

                $result = 'returned';
                if ($usedQty > 0 && $returnedQty > 0) {
                    $result = 'partial';
                } elseif ($usedQty > 0 && $returnedQty == 0) {
                    $result = 'used';
                }
                
                // If the entire lot is missing or damaged, result might be considered partial or just marked accordingly.
                // Keeping existing result logic mostly intact, but extending it if usedQty + damagedQty + missingQty > 0
                if (($usedQty + $damagedQty + $missingQty) > 0 && $returnedQty == 0) {
                    $result = 'used'; // Generic non-returned state
                } elseif (($usedQty + $damagedQty + $missingQty) > 0 && $returnedQty > 0) {
                    $result = 'partial';
                }

                if ($usedQty > 0) {
                    $usedItemCount++;
                }

                if ($ci->lot_id) {
                    $lot = Lot::query()->lockForUpdate()->findOrFail($ci->lot_id);

                    if ($returnedQty > 0) {
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
                            'quantity'             => $returnedQty,
                        ]);

                        \App\Models\LotHolding::query()->create([
                            'lot_id'              => $lot->id,
                            'holding_reason'      => 'Pending inspection after return',
                            'assigned_at'         => now(),
                            'assigned_by_user_id' => $actor->id,
                        ]);

                        $lot->quantity_available += $returnedQty;
                        $lot->quantity_consigned -= $returnedQty;

                        if ($lot->quantity_available > 0 && $lot->status === 'depleted') {
                            $lot->status = 'available';
                        }
                    }

                    if ($usedQty > 0) {
                        $lot->quantity_consigned -= $usedQty;
                        
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
                            'quantity'             => $usedQty,
                        ]);
                    }

                    if ($damagedQty > 0) {
                        $lot->quantity_consigned -= $damagedQty;
                        $lot->quantity_available += $damagedQty; // Wait, damaged goes back to warehouse but in different status? Let's assume it goes to 'damaged' status, but quantity is in warehouse.

                        LotMovement::query()->create([
                            'lot_id'               => $lot->id,
                            'movement_type'        => 'damaged',
                            'reference_type'       => Reconciliation::class,
                            'reference_id'         => $locked->id,
                            'from_status'          => $lot->status,
                            'to_status'            => 'damaged',
                            'from_location_type'   => $lot->current_location_type,
                            'from_location_id'     => $lot->current_location_id,
                            'to_location_type'     => 'warehouse',
                            'to_location_id'       => null,
                            'performed_at'         => now(),
                            'performed_by_user_id' => $actor->id,
                            'remarks'              => "Marked damaged via reconciliation {$locked->reconciliation_no}",
                            'quantity'             => $damagedQty,
                        ]);
                    }

                    if ($missingQty > 0) {
                        $lot->quantity_consigned -= $missingQty;
                        // Missing quantity is removed from inventory. It is conceptually depleted/lost.

                        LotMovement::query()->create([
                            'lot_id'               => $lot->id,
                            'movement_type'        => 'missing',
                            'reference_type'       => Reconciliation::class,
                            'reference_id'         => $locked->id,
                            'from_status'          => $lot->status,
                            'to_status'            => 'missing',
                            'from_location_type'   => $lot->current_location_type,
                            'from_location_id'     => $lot->current_location_id,
                            'to_location_type'     => $lot->current_location_type,
                            'to_location_id'       => $lot->current_location_id,
                            'performed_at'         => now(),
                            'performed_by_user_id' => $actor->id,
                            'remarks'              => "Marked missing via reconciliation {$locked->reconciliation_no}",
                            'quantity'             => $missingQty,
                        ]);
                    }

                    if ($returnedQty > 0) {
                        $lot->fill([
                            'status' => 'holding',
                            'current_location_type' => 'warehouse',
                            'current_location_id'   => null,
                        ]);
                    }
                    if ($usedQty > 0 && $returnedQty === 0 && $damagedQty === 0 && $missingQty === 0) {
                        $lot->fill(['status' => 'used']);
                    } elseif ($damagedQty > 0 && $returnedQty === 0 && $usedQty === 0 && $missingQty === 0) {
                        $lot->fill(['status' => 'damaged', 'current_location_type' => 'warehouse', 'current_location_id' => null]);
                    } elseif ($missingQty > 0 && $returnedQty === 0 && $usedQty === 0 && $damagedQty === 0) {
                        $lot->fill(['status' => 'missing']);
                    }
                    $lot->save();

                    $reconItem = ReconciliationItem::query()->create([
                        'reconciliation_id' => $locked->id,
                        'lot_id'            => $lot->id,
                        'result'            => $result,
                        'remarks'           => null,
                        'quantity'          => $consignedQty,
                        'returned_quantity' => $returnedQty,
                        'used_quantity'     => $usedQty,
                        'damaged_quantity'  => $damagedQty,
                        'missing_quantity'  => $missingQty,
                    ]);

                    if ($lot->isSetInstance()) {
                        $instrumentSet = $lot->instrumentSet()->with(['instrumentSetItems.product'])->first();
                        if ($returnedQty > 0 && $returnItem) {
                            $this->processSetInstrumentResults($reconItem, $returnItem, $instrumentSet);
                        } elseif ($usedQty > 0) {
                            $this->processUsedSetInstrumentResults($reconItem, $instrumentSet);
                        }
                    }

                } elseif ($ci->instrument_set_id) {
                    $reconItem = ReconciliationItem::query()->create([
                        'reconciliation_id' => $locked->id,
                        'instrument_set_id' => $ci->instrument_set_id,
                        'result'            => $result,
                        'remarks'           => null,
                        'quantity'          => $consignedQty,
                        'returned_quantity' => $returnedQty,
                        'used_quantity'     => $usedQty,
                    ]);
                    
                    $instrumentSet = \App\Models\InstrumentSet::with(['instrumentSetItems.product'])->find($ci->instrument_set_id);
                    if ($returnedQty > 0 && $returnItem) {
                        $this->processSetInstrumentResults($reconItem, $returnItem, $instrumentSet);
                    } else {
                        $this->processUsedSetInstrumentResults($reconItem, $instrumentSet);
                    }
                } elseif ($ci->product_id) {
                    ReconciliationItem::query()->create([
                        'reconciliation_id' => $locked->id,
                        'product_id'        => $ci->product_id,
                        'result'            => $result,
                        'remarks'           => null,
                        'quantity'          => $consignedQty,
                        'returned_quantity' => $returnedQty,
                        'used_quantity'     => $usedQty,
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
                    $usedItemCount,
                    count($returnedKeys)
                ),
                after: [
                    'status'         => 'finalized',
                    'completed_at'   => now()->toIso8601String(),
                    'total_consigned' => count($consignedKeys),
                    'total_returned' => count($returnedKeys),
                    'total_used'     => $usedItemCount,
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
            if ($sii->product_id) {
                $returnedMap[(int) $sii->product_id] = (int) $sii->returned_quantity;
            }
        }

        if ($instrumentSet && $instrumentSet->instrumentSetItems) {
            foreach ($instrumentSet->instrumentSetItems as $productItem) {
                $expected = $productItem->quantity;
                $returned = $returnedMap[(int) $productItem->product_id] ?? 0;
                $used = max(0, $expected - $returned);

                $reconItem->setInstrumentResults()->create([
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
        if ($instrumentSet && $instrumentSet->instrumentSetItems) {
            foreach ($instrumentSet->instrumentSetItems as $productItem) {
                $expected = $productItem->quantity;
                $reconItem->setInstrumentResults()->create([
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
