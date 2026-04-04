<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['usage_summary_id', 'push_url', 'status', 'http_status_code', 'request_payload', 'response_body', 'error_message', 'pushed_at', 'next_retry_at', 'retry_count', 'pushed_by_user_id'])]
class UsageSummaryPushLog extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'request_payload' => 'json',
            'response_body' => 'json',
            'pushed_at' => 'datetime',
            'next_retry_at' => 'datetime',
        ];
    }

    public function usageSummary(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\UsageSummary');
    }

    public function pushedByUser(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\User', 'pushed_by_user_id');
    }
}
