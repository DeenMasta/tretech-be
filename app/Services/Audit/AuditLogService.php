<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;

class AuditLogService
{
    /**
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
    ): void {
        AuditLog::query()->create([
            'user_id' => $actor?->id,
            'role_code_snapshot' => $actor?->getRoleCode(),
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'action_type' => $actionType,
            'description' => $description,
            'ip_address' => $ipAddress,
            'device_id' => $deviceId,
            'before_json' => $before,
            'after_json' => $after,
            'server_timestamp' => now(),
        ]);
    }
}
