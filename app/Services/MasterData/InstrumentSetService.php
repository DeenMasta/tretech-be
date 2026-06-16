<?php

namespace App\Services\MasterData;

use App\Models\InstrumentSet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class InstrumentSetService
{
    /**
     * @param array<string, mixed> $filters
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $search = (string) ($filters['search'] ?? '');
        $isActive = $filters['is_active'] ?? null;

        return InstrumentSet::query()
            ->withCount(['instrumentSetItems', 'setInstruments'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('set_code', 'like', "%{$search}%")
                        ->orWhere('set_name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($isActive !== null, fn ($query) => $query->where('is_active', (bool) $isActive))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): InstrumentSet
    {
        return InstrumentSet::query()->create($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(InstrumentSet $instrumentSet, array $data): InstrumentSet
    {
        $instrumentSet->fill($data)->save();

        return $instrumentSet->refresh();
    }

    public function delete(InstrumentSet $instrumentSet): void
    {
        $instrumentSet->delete();
    }
}
