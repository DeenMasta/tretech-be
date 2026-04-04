<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'role_code_snapshot', 'auditable_type', 'auditable_id', 'action_type', 'description', 'ip_address', 'device_id', 'before_json', 'after_json', 'server_timestamp'])]
class AuditLog extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'before_json' => 'json',
            'after_json' => 'json',
            'server_timestamp' => 'datetime',
        ];
    }

    public $timestamps = false;

    public function user(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\User');
    }
}
