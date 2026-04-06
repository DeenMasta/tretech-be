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

        return Consignment::query()
            ->with(['client:id,client_name', 'picUser:id,full_name'])
            ->withCount('consignmentItems')
            ->when($search !== '', function ($query) use ($search) {
                $query->where('consignment_no', 'like', "%{$search}%");
            })
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($clientId !== null, fn ($q) => $q->where('client_id', (int) $clientId))
            ->when($fromDate !== null, fn ($q) => $q->whereDate('consignment_at', '>=', $fromDate))
            ->when($toDate !== null, fn ($q) => $q->whereDate('consignment_at', '<=', $toDate))
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
        $this->ensureDraft($consignment);

        $consignment->fill($data)->save();

        return $consignment->refresh();
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
        $datePart = now()->format('Ymd');

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $sequence = str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            $no = "CN-{$datePart}-{$sequence}";

            if (!Consignment::query()->where('consignment_no', $no)->exists()) {
                return $no;
            }
        }

        throw new BusinessLogicException('Unable to generate a unique consignment number. Please retry.');
    }
}
