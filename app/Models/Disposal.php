<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['disposal_no', 'disposed_at', 'pic_user_id', 'status', 'remarks', 'completed_at', 'completed_by_user_id'])]
class Disposal extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'disposed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function picUser(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\User', 'pic_user_id');
    }

    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\User', 'completed_by_user_id');
    }

    public function disposalItems(): HasMany
    {
        return $this->hasMany('App\\Models\\DisposalItem');
    }
}
