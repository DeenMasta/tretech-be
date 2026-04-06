<?php

namespace App\Services\MasterData;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserService
{
    /**
     * @param array<string, mixed> $filters
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $search = (string) ($filters['search'] ?? '');
        $isActive = $filters['is_active'] ?? null;
        $roleId = $filters['role_id'] ?? null;

        return User::query()
            ->with('role')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('full_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($roleId !== null, fn ($query) => $query->where('role_id', $roleId))
            ->when($isActive !== null, fn ($query) => $query->where('is_active', (bool) $isActive))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): User
    {
        $payload = $this->normalizePayload($data);

        return User::query()->create($payload)->load('role');
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(User $user, array $data): User
    {
        $payload = $this->normalizePayload($data);

        $user->fill($payload)->save();

        return $user->refresh()->load('role');
    }

    public function delete(User $user): void
    {
        $user->delete();
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizePayload(array $data): array
    {
        if (array_key_exists('password', $data)) {
            if ($data['password']) {
                $data['password_hash'] = $data['password'];
            }
            unset($data['password']);
        }

        return $data;
    }
}
