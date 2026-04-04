<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['error_type', 'error_message', 'stack_trace', 'context', 'logged_by_system', 'occurrence_timestamp'])]
class ErrorLog extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'context' => 'json',
            'occurrence_timestamp' => 'datetime',
        ];
    }

    public $timestamps = false;
}
