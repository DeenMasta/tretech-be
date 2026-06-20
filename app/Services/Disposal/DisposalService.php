<?php

namespace App\Services\Disposal;

use App\Exceptions\BusinessLogicException;
use App\Models\Disposal;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DisposalService
{
    /**
     * @param array<string, mixed> $filters
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $search   = (string) ($filters['search'] ?? '');
        $status   = (string) ($filters['status'] ?? '');
        $fromDate = $filters['from_date'] ?? null;
        $toDate   = $filters['to_date'] ?? null;

        return Disposal::query()
            ->with(['picUser:id,full_name', 'completedByUser:id,full_name'])
            ->withCount('disposalItems')
            ->when($search !== '', fn ($q) => $q->where('disposal_no', 'like', "%{$search}%"))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($fromDate !== null, fn ($q) => $q->whereDate('disposed_at', '>=', $fromDate))
            ->when($toDate !== null, fn ($q) => $q->whereDate('disposed_at', '<=', $toDate))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data, User $actor): Disposal
    {
        return Disposal::query()->create([
            ...$data,
            'disposal_no' => $this->generateDisposalNo(),
            'status'      => 'draft',
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Disposal $disposal, array $data): Disposal
    {
        $this->ensureDraft($disposal);

        $disposal->fill($data)->save();

        return $disposal->refresh();
    }

    public function ensureDraft(Disposal $disposal): void
    {
        if ($disposal->status !== 'draft') {
            throw new BusinessLogicException('Only draft disposals can be modified.');
        }
    }

    private function generateDisposalNo(): string
    {
        $yearPart = now()->format('y');
        $prefix = "TDS{$yearPart}-";

        $lastDisposal = Disposal::query()
            ->where('disposal_no', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->first();

        $nextSequence = 1;
        if ($lastDisposal) {
            $lastSequence = (int) substr($lastDisposal->disposal_no, strlen($prefix));
            $nextSequence = max(1, $lastSequence + 1);
        }

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $sequenceStr = str_pad((string) $nextSequence, 4, '0', STR_PAD_LEFT);
            $no = "{$prefix}{$sequenceStr}";

            if (!Disposal::query()->where('disposal_no', $no)->exists()) {
                return $no;
            }
            
            $nextSequence++;
        }

        throw new BusinessLogicException('Unable to generate a unique disposal number. Please retry.');
    }
}
