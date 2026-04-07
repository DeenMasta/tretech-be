<?php

namespace App\Services\SupplierReturn;

use App\Exceptions\BusinessLogicException;
use App\Models\SupplierReturn;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SupplierReturnService
{
    /**
     * @param array<string, mixed> $filters
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $search     = (string) ($filters['search'] ?? '');
        $status     = (string) ($filters['status'] ?? '');
        $supplierId = $filters['supplier_id'] ?? null;
        $fromDate   = $filters['from_date'] ?? null;
        $toDate     = $filters['to_date'] ?? null;

        return SupplierReturn::query()
            ->with(['supplier:id,supplier_name', 'picUser:id,full_name', 'completedByUser:id,full_name'])
            ->withCount('supplierReturnItems')
            ->when($search !== '', fn ($q) => $q->where('supplier_return_no', 'like', "%{$search}%"))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($supplierId !== null, fn ($q) => $q->where('supplier_id', (int) $supplierId))
            ->when($fromDate !== null, fn ($q) => $q->whereDate('returned_at', '>=', $fromDate))
            ->when($toDate !== null, fn ($q) => $q->whereDate('returned_at', '<=', $toDate))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data, User $actor): SupplierReturn
    {
        return SupplierReturn::query()->create([
            ...$data,
            'supplier_return_no' => $this->generateSupplierReturnNo(),
            'status'             => 'draft',
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(SupplierReturn $supplierReturn, array $data): SupplierReturn
    {
        $this->ensureDraft($supplierReturn);

        $supplierReturn->fill($data)->save();

        return $supplierReturn->refresh();
    }

    public function ensureDraft(SupplierReturn $supplierReturn): void
    {
        if ($supplierReturn->status !== 'draft') {
            throw new BusinessLogicException('Only draft supplier returns can be modified.');
        }
    }

    private function generateSupplierReturnNo(): string
    {
        $datePart = now()->format('Ymd');

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $sequence = str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            $no = "SR-{$datePart}-{$sequence}";

            if (!SupplierReturn::query()->where('supplier_return_no', $no)->exists()) {
                return $no;
            }
        }

        return 'SR-' . now()->format('YmdHis') . '-' . substr((string) microtime(true), -4);
    }
}
