<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class AuditLogService
{
    /**
     * Log an action against any auditable model.
     *
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     */
    public function logModelAction(
        string $auditableType,
        int $auditableId,
        string $actionType,
        ?User $actor = null,
        ?string $description = null,
        ?string $ipAddress = null,
        ?string $deviceId = null,
        ?array $before = null,
        ?array $after = null
    ): AuditLog {
        return AuditLog::query()->create([
            'user_id'             => $actor?->id,
            'role_code_snapshot'  => $actor?->getRoleCode(),
            'auditable_type'      => $auditableType,
            'auditable_id'        => $auditableId,
            'action_type'         => $actionType,
            'description'         => $description,
            'ip_address'          => $ipAddress,
            'device_id'           => $deviceId,
            'before_json'         => $before,
            'after_json'          => $after,
            'server_timestamp'    => now(),
        ]);
    }

    /**
     * Convenience wrapper: log an action on an Eloquent model instance, capturing
     * its dirty attributes as the before/after diff when available.
     *
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     */
    public function logEloquent(
        Model $model,
        string $actionType,
        ?User $actor = null,
        ?string $description = null,
        ?Request $request = null,
        ?array $before = null,
        ?array $after = null
    ): AuditLog {
        return $this->logModelAction(
            auditableType: $model->getMorphClass(),
            auditableId:   (int) $model->getKey(),
            actionType:    $actionType,
            actor:         $actor,
            description:   $description,
            ipAddress:     $request?->ip(),
            deviceId:      $request?->header('X-Device-Id'),
            before:        $before,
            after:         $after,
        );
    }

    /**
     * Paginated list of audit logs with optional filters.
     *
     * @param array<string, mixed> $filters  Supported keys:
     *   user_id, auditable_type, auditable_id, action_type,
     *   from_date (Y-m-d), to_date (Y-m-d), ip_address, device_id
     */
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = AuditLog::query()->with('user:id,full_name,email')->latest('server_timestamp');

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['auditable_type'])) {
            $query->where('auditable_type', $filters['auditable_type']);
        }

        if (!empty($filters['auditable_id'])) {
            $query->where('auditable_id', $filters['auditable_id']);
        }

        if (!empty($filters['action_type'])) {
            $query->where('action_type', $filters['action_type']);
        }

        if (!empty($filters['ip_address'])) {
            $query->where('ip_address', $filters['ip_address']);
        }

        if (!empty($filters['device_id'])) {
            $query->where('device_id', $filters['device_id']);
        }

        if (!empty($filters['from_date'])) {
            $query->where('server_timestamp', '>=', $filters['from_date'] . ' 00:00:00');
        }

        if (!empty($filters['to_date'])) {
            $query->where('server_timestamp', '<=', $filters['to_date'] . ' 23:59:59');
        }

        return $query->paginate($perPage);
    }

    /**
     * Find a single audit log entry.
     */
    public function find(int $id): ?AuditLog
    {
        return AuditLog::query()->with('user:id,full_name,email')->find($id);
    }
}
