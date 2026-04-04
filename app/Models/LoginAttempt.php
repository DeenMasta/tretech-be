<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'email', 'ip_address', 'device_id', 'was_successful', 'failure_reason', 'attempted_at'])]
class LoginAttempt extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'was_successful' => 'boolean',
            'attempted_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
