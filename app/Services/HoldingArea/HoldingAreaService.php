<?php

namespace App\Services\HoldingArea;

use App\Enums\AuditAction;
use App\Exceptions\BusinessLogicException;
use App\Models\Lot;
use App\Models\LotHolding;
use App\Models\LotMovement;
use App\Models\QrLabel;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\QrLabel\PrintJobService;
use App\Services\QrLabel\QrPayloadService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class HoldingAreaService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly QrPayloadService $qrPayloadService,
        private readonly PrintJobService $printJobService,
    ) {
    }

    /**
     * Paginate lots currently in holding status.
     *
     * @param array<string, mixed> $filters  Supported keys: search, supplier_id, product_id, from_date, to_date
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $search     = (string) ($filters['search'] ?? '');
        $supplierId = $filters['supplier_id'] ?? null;
        $productId  = $filters['product_id'] ?? null;
        $fromDate   = $filters['from_date'] ?? null;
        $toDate     = $filters['to_date'] ?? null;

        return Lot::query()
            ->where('status', 'holding')
            ->with([
                'product:id,ref_num,product_name',
                'supplier:id,supplier_name',
                'lotHolding.assignedByUser:id,full_name',
            ])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q2) use ($search) {
                    $q2->where('lot_number', 'like', "%{$search}%")
                       ->orWhere('supplier_batch_code', 'like', "%{$search}%");
                });
            })
            ->when($supplierId !== null, fn ($q) => $q->where('supplier_id', (int) $supplierId))
            ->when($productId !== null, fn ($q) => $q->where('product_id', (int) $productId))
            ->when($fromDate !== null, fn ($q) => $q->whereDate('received_at', '>=', $fromDate))
            ->when($toDate !== null, fn ($q) => $q->whereDate('received_at', '<=', $toDate))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Admin-only: assign a real lot number to a holding unit.
     *
     * Steps:
     *  1. Lock lot and validate it is still `holding`
     *  2. Validate new lot_number global uniqueness
     *  3. Update lot (lot_number, status → available, is_system_generated_lot → false)
     *  4. Close the LotHolding record (released_at, corrected_lot_number, resolution_reason)
     *  5. Write a LotMovement (holding_released)
     *  6. Regenerate QR label with the new lot number
     *  7. Queue a new print job for the updated label
     *  8. Write audit log
     *
     * @param array<string, mixed> $data  Expects: lot_number, resolution_reason, remarks?
     */
    public function assignLot(Lot $lot, array $data, User $actor): Lot
    {
        return DB::transaction(function () use ($lot, $data, $actor) {
            /** @var Lot $locked */
            $locked = Lot::query()->lockForUpdate()->findOrFail($lot->id);

            if ($locked->status !== 'holding') {
                throw new BusinessLogicException(
                    "Lot [{$locked->lot_number}] is not in holding status (current: {$locked->status})."
                );
            }

            $newLotNumber = trim((string) $data['lot_number']);

            // Uniqueness check: no other lot may have this number
            $conflict = Lot::query()
                ->where('lot_number', $newLotNumber)
                ->where('id', '!=', $locked->id)
                ->exists();

            if ($conflict) {
                throw new BusinessLogicException(
                    "Lot number [{$newLotNumber}] already exists in inventory."
                );
            }

            $oldLotNumber = $locked->lot_number;
            $fromStatus   = $locked->status;

            // 3. Update the lot record
            $locked->fill([
                'lot_number'              => $newLotNumber,
                'is_system_generated_lot' => false,
                'status'                  => 'available',
            ])->save();

            // 4. Close the LotHolding record (most-recent open record for this lot)
            LotHolding::query()
                ->where('lot_id', $locked->id)
                ->whereNull('released_at')
                ->update([
                    'released_at'           => now(),
                    'released_by_user_id'   => $actor->id,
                    'corrected_lot_number'  => $newLotNumber,
                    'resolution_reason'     => $data['resolution_reason'],
                    'remarks'               => $data['remarks'] ?? null,
                ]);

            // 5. LotMovement
            LotMovement::query()->create([
                'lot_id'               => $locked->id,
                'movement_type'        => 'holding_released',
                'reference_type'       => Lot::class,
                'reference_id'         => $locked->id,
                'from_status'          => $fromStatus,
                'to_status'            => 'available',
                'from_location_type'   => $locked->current_location_type,
                'from_location_id'     => $locked->current_location_id,
                'to_location_type'     => 'warehouse',
                'to_location_id'       => null,
                'performed_at'         => now(),
                'performed_by_user_id' => $actor->id,
                'remarks'              => "Lot number assigned: {$newLotNumber}. Reason: {$data['resolution_reason']}",
            ]);

            // Ensure location is set to warehouse now
            $locked->fill(['current_location_type' => 'warehouse', 'current_location_id' => null])->save();

            // 6. Regenerate QR label — update existing payload or create fresh
            $existingLabel = QrLabel::query()->where('lot_id', $locked->id)->first();
            if ($existingLabel !== null) {
                $existingLabel->update([
                    'qr_payload'           => $this->qrPayloadService->generatePayload($locked),
                    'generated_at'         => now(),
                    'generated_by_user_id' => $actor->id,
                ]);
            } else {
                $this->qrPayloadService->createLabelForLot($locked, $actor);
            }

            // 7. Queue a print job for the new label
            $this->printJobService->createPrintJob(lot: $locked, actor: $actor);

            // 8. Audit log
            $this->auditLogService->logEloquent(
                model:       $locked,
                actionType:  AuditAction::LOT_ASSIGNED,
                actor:       $actor,
                description: "Lot number assigned to holding unit: [{$oldLotNumber}] → [{$newLotNumber}].",
                before:      ['lot_number' => $oldLotNumber, 'status' => $fromStatus],
                after:       ['lot_number' => $newLotNumber, 'status' => 'available'],
            );

            return $locked->refresh()->load([
                'product:id,ref_num,product_name',
                'supplier:id,supplier_name',
                'lotHolding',
            ]);
        });
    }
}
