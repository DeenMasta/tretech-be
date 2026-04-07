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
        $datePart = now()->format('Ymd');

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $sequence = str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            $no = "DIS-{$datePart}-{$sequence}";

            if (!Disposal::query()->where('disposal_no', $no)->exists()) {
                return $no;
            }
        }

        // Fallback: microsecond suffix ensures uniqueness
        return 'DIS-' . now()->format('YmdHis') . '-' . substr((string) microtime(true), -4);
    }
}
