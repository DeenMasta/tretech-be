<?php

namespace App\Services\Consignment;

use App\Exceptions\BusinessLogicException;
use App\Models\Consignment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ConsignmentService
{
    /**
     * @param array<string, mixed> $filters
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $search    = (string) ($filters['search'] ?? '');
        $status    = (string) ($filters['status'] ?? '');
        $clientId  = $filters['client_id'] ?? null;
        $fromDate  = $filters['from_date'] ?? null;
        $toDate    = $filters['to_date'] ?? null;
        $hasReturnSession = $filters['has_return_session'] ?? null;

        return Consignment::query()
            ->with([
                'client:id,client_name',
                'picUser:id,full_name',
                'consignmentItems.lot:id,lot_number',
            ])
            ->withCount('consignmentItems')
            ->when($search !== '', function ($query) use ($search) {
                $query->where('consignment_no', 'like', "%{$search}%");
            })
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($clientId !== null, fn ($q) => $q->where('client_id', (int) $clientId))
            ->when($fromDate !== null, fn ($q) => $q->whereDate('consignment_at', '>=', $fromDate))
            ->when($toDate !== null, fn ($q) => $q->whereDate('consignment_at', '<=', $toDate))
            ->when($hasReturnSession !== null, function ($q) use ($hasReturnSession) {
                $hasReturnSession = filter_var($hasReturnSession, FILTER_VALIDATE_BOOLEAN);
                if ($hasReturnSession) {
                    $q->has('returnSession');
                } else {
                    $q->doesntHave('returnSession');
                }
            })
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): Consignment
    {
        return Consignment::query()->create([
            ...$data,
            'consignment_no' => $this->generateConsignmentNo(),
            'status'         => 'draft',
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Consignment $consignment, array $data): Consignment
    {
        $consignment->fill($data)->save();

        return $consignment->refresh();
    }

    public function delete(Consignment $consignment): void
    {
        $this->ensureDraft($consignment);

        // Delete associated items first (optional, as DB cascades usually handle it, but good practice)
        $consignment->consignmentItems()->delete();
        
        $consignment->delete();
    }

    public function ensureDraft(Consignment $consignment): void
    {
        if ($consignment->status !== 'draft') {
            throw new BusinessLogicException('Only draft consignments can be modified.');
        }
    }

    public function ensureConfirmed(Consignment $consignment): void
    {
        if ($consignment->status !== 'confirmed') {
            throw new BusinessLogicException('Only confirmed consignments can be edited post-confirmation.');
        }
    }

    private function generateConsignmentNo(): string
    {
        $yearPart = now()->format('y');
        $prefix = "TCN{$yearPart}-";

        $lastConsignment = Consignment::query()
            ->where('consignment_no', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->first();

        $nextSequence = 1;
        if ($lastConsignment) {
            $lastSequence = (int) substr($lastConsignment->consignment_no, strlen($prefix));
            $nextSequence = max(1, $lastSequence + 1);
        }

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $sequenceStr = str_pad((string) $nextSequence, 4, '0', STR_PAD_LEFT);
            $no = "{$prefix}{$sequenceStr}";

            if (!Consignment::query()->where('consignment_no', $no)->exists()) {
                return $no;
            }
            
            $nextSequence++;
        }

        throw new BusinessLogicException('Unable to generate a unique consignment number. Please retry.');
    }
}
