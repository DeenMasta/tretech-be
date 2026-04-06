<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['source', 'source_id', 'message', 'details'])]
class ErrorLog extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'details' => 'json',
        ];
    }

    public $timestamps = false;
    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;
}
