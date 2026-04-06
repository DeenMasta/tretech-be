<?php

namespace App\Services\MasterData;

use App\Models\Client;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ClientService
{
    /**
     * @param array<string, mixed> $filters
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $search = (string) ($filters['search'] ?? '');
        $isActive = $filters['is_active'] ?? null;
        $clientType = $filters['client_type'] ?? null;

        return Client::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('client_name', 'like', "%{$search}%")
                        ->orWhere('client_type', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($clientType !== null && $clientType !== '', fn ($query) => $query->where('client_type', $clientType))
            ->when($isActive !== null, fn ($query) => $query->where('is_active', (bool) $isActive))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): Client
    {
        return Client::query()->create($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Client $client, array $data): Client
    {
        $client->fill($data)->save();

        return $client->refresh();
    }

    public function delete(Client $client): void
    {
        $client->delete();
    }
}
