<?php

namespace App\Services\Consignment;

use App\Enums\AuditAction;
use App\Exceptions\BusinessLogicException;
use App\Models\Consignment;
use App\Models\ConsignmentItem;
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
                ->with(['consignmentItems.lot'])
                ->findOrFail($consignment->id);

            if ($locked->status !== 'draft') {
                throw new BusinessLogicException('Only draft consignments can be confirmed.');
            }

            if ($locked->consignmentItems->isEmpty()) {
                throw new BusinessLogicException('Cannot confirm an empty consignment note.');
            }

            foreach ($locked->consignmentItems as $item) {
                // Set-type items track the instrument set reference only — no lot validation needed.
                if ($item->isSetEntry()) {
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
            }

            foreach ($locked->consignmentItems as $item) {
                // Set-type items have no associated lot — skip lot movement.
                if ($item->isSetEntry()) {
                    continue;
                }

                $lot = $item->lot;

                $fromStatus = $lot->status;

                $lot->fill(['status' => 'supplied'])->save();

                LotMovement::query()->create([
                    'lot_id'              => $lot->id,
                    'movement_type'       => 'consigned',
                    'reference_type'      => Consignment::class,
                    'reference_id'        => $locked->id,
                    'from_status'         => $fromStatus,
                    'to_status'           => 'supplied',
                    'from_location_type'  => $lot->current_location_type,
                    'from_location_id'    => $lot->current_location_id,
                    'to_location_type'    => 'client',
                    'to_location_id'      => $locked->client_id,
                    'performed_at'        => now(),
                    'performed_by_user_id' => $actor->id,
                    'remarks'             => "Consigned via {$locked->consignment_no}",
                ]);

                $lot->fill([
                    'current_location_type' => 'client',
                    'current_location_id'   => $locked->client_id,
                ])->save();
            }

            $locked->fill([
                'status'                => 'confirmed',
                'confirmed_at'          => now(),
                'confirmed_by_user_id'  => $actor->id,
            ])->save();

            $this->auditLogService->logModelAction(
                auditableType: Consignment::class,
                auditableId:   $locked->id,
                actionType:    AuditAction::CONSIGNMENT_CONFIRMED,
                actor:         $actor,
                description:   sprintf(
                    'Consignment %s confirmed — %d lot(s) supplied.',
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
}
