<?php

namespace App\Services\StockIn;

use App\Exceptions\BusinessLogicException;
use App\Models\StockIn;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class StockInSessionService
{
    /**
     * @param array<string, mixed> $filters
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $search = (string) ($filters['search'] ?? '');
        $status = (string) ($filters['status'] ?? '');
        $supplierId = $filters['supplier_id'] ?? null;
        $fromDate = $filters['from_date'] ?? null;
        $toDate = $filters['to_date'] ?? null;

        return StockIn::query()
            ->with(['supplier:id,supplier_name', 'picUser:id,full_name'])
            ->withCount('stockInItems')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('session_no', 'like', "%{$search}%")
                        ->orWhere('do_number', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($supplierId !== null, fn ($query) => $query->where('supplier_id', (int) $supplierId))
            ->when($fromDate !== null, fn ($query) => $query->whereDate('stock_in_at', '>=', $fromDate))
            ->when($toDate !== null, fn ($query) => $query->whereDate('stock_in_at', '<=', $toDate))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): StockIn
    {
        $payload = [
            ...$data,
            'session_no' => $this->generateSessionNo(),
            'status' => 'draft',
        ];

        return StockIn::query()->create($payload);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(StockIn $stockIn, array $data): StockIn
    {
        $this->ensureDraft($stockIn);

        $stockIn->fill($data)->save();

        return $stockIn->refresh();
    }

    public function ensureDraft(StockIn $stockIn): void
    {
        if ($stockIn->status !== 'draft') {
            throw new BusinessLogicException('Only draft stock-in sessions can be modified.');
        }
    }

    private function generateSessionNo(): string
    {
        $datePart = now()->format('Ymd');

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $sequence = str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            $sessionNo = "SI-{$datePart}-{$sequence}";

            if (!StockIn::query()->where('session_no', $sessionNo)->exists()) {
                return $sessionNo;
            }
        }

        throw new BusinessLogicException('Unable to generate a unique stock-in session number.');
    }
}
