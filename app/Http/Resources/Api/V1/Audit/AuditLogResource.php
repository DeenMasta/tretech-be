<?php

namespace App\Http\Resources\Api\V1\Audit;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'action_type'        => $this->action_type,
            'description'        => $this->description,
            'auditable_type'     => $this->auditable_type,
            'auditable_id'       => $this->auditable_id,
            'user_id'            => $this->user_id,
            'role_code_snapshot' => $this->role_code_snapshot,
            'ip_address'         => $this->ip_address,
            'device_id'          => $this->device_id,
            'before_json'        => $this->before_json,
            'after_json'         => $this->after_json,
            'server_timestamp'   => $this->server_timestamp
                ? \Carbon\Carbon::parse($this->server_timestamp)->toIso8601String()
                : null,
            'created_at'         => $this->created_at
                ? \Carbon\Carbon::parse($this->created_at)->toIso8601String()
                : null,
            'user'               => $this->whenLoaded('user', fn() => [
                'id'         => $this->user->id,
                'full_name'  => $this->user->full_name,
                'email'      => $this->user->email,
            ]),
        ];
    }
}
