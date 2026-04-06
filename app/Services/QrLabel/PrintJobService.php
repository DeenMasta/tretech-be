<?php

namespace App\Services\QrLabel;

use App\Enums\PrintJobActionType;
use App\Enums\PrintJobStatus;
use App\Exceptions\BusinessLogicException;
use App\Exceptions\ResourceNotFoundException;
use App\Models\Lot;
use App\Models\QrLabel;
use App\Models\QrPrintJob;
use App\Models\User;

class PrintJobService
{
    public function __construct(private readonly QrPayloadService $qrPayloadService)
    {
    }

    /**
     * Create a new "print" job for a lot.
     * Used automatically during finalization AND when the mobile app requests
     * to print a label for an already-finalized lot.
     */
    public function createPrintJob(
        Lot $lot,
        User $actor,
        ?string $printerName = null,
        ?string $deviceId = null
    ): QrPrintJob {
        $label = $this->resolveLabel($lot, $actor);

        $tspl = $this->qrPayloadService->buildTsplPayload($label->qr_payload, $lot);

        return QrPrintJob::query()->create([
            'lot_id'               => $lot->id,
            'qr_label_id'          => $label->id,
            'action_type'          => PrintJobActionType::Print->value,
            'status'               => PrintJobStatus::Queued->value,
            'printer_name'         => $printerName,
            'device_id'            => $deviceId,
            'tspl_payload'         => $tspl,
            'requested_by_user_id' => $actor->id,
            'requested_at'         => now(),
        ]);
    }

    /**
     * Create a reprint job. Reason is mandatory.
     */
    public function createReprintJob(
        Lot $lot,
        User $actor,
        string $reason,
        ?string $printerName = null,
        ?string $deviceId = null
    ): QrPrintJob {
        $label = $this->resolveLabel($lot, $actor);

        $tspl = $this->qrPayloadService->buildTsplPayload($label->qr_payload, $lot);

        return QrPrintJob::query()->create([
            'lot_id'               => $lot->id,
            'qr_label_id'          => $label->id,
            'action_type'          => PrintJobActionType::Reprint->value,
            'reprint_reason'       => $reason,
            'status'               => PrintJobStatus::Queued->value,
            'printer_name'         => $printerName,
            'device_id'            => $deviceId,
            'tspl_payload'         => $tspl,
            'requested_by_user_id' => $actor->id,
            'requested_at'         => now(),
        ]);
    }

    /**
     * Mark a queued job as printed (called by Flutter app after successful BLE print).
     */
    public function markPrinted(QrPrintJob $job): QrPrintJob
    {
        if ($job->status !== PrintJobStatus::Queued->value) {
            throw new BusinessLogicException("Only queued print jobs can be marked as printed (current status: {$job->status}).");
        }

        $job->fill([
            'status'     => PrintJobStatus::Printed->value,
            'printed_at' => now(),
        ])->save();

        return $job->refresh();
    }

    /**
     * Mark a queued job as failed (called by Flutter app on BLE error).
     */
    public function markFailed(QrPrintJob $job, string $errorMessage): QrPrintJob
    {
        if ($job->status !== PrintJobStatus::Queued->value) {
            throw new BusinessLogicException("Only queued print jobs can be marked as failed (current status: {$job->status}).");
        }

        $job->fill([
            'status'        => PrintJobStatus::Failed->value,
            'error_message' => $errorMessage,
            'failed_at'     => now(),
        ])->save();

        return $job->refresh();
    }

    /**
     * Paginate print jobs with optional filters.
     *
     * @param array<string, mixed> $filters
     */
    public function paginate(array $filters, int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = QrPrintJob::query()
            ->with([
                'lot:id,lot_number,product_id',
                'lot.product:id,ref_num,product_name',
                'requestedByUser:id,full_name',
            ]);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['action_type'])) {
            $query->where('action_type', $filters['action_type']);
        }

        if (!empty($filters['lot_id'])) {
            $query->where('lot_id', $filters['lot_id']);
        }

        if (!empty($filters['device_id'])) {
            $query->where('device_id', $filters['device_id']);
        }

        if (!empty($filters['from_date'])) {
            $query->whereDate('requested_at', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate('requested_at', '<=', $filters['to_date']);
        }

        return $query->latest('requested_at')->paginate($perPage);
    }

    /**
     * Ensure a QrLabel exists for the lot; create it if absent.
     */
    private function resolveLabel(Lot $lot, User $actor): QrLabel
    {
        return $this->qrPayloadService->createLabelForLot($lot, $actor);
    }
}
