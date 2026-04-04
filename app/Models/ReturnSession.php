<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['consignment_id', 'return_session_no', 'pic_user_id', 'status', 'remarks', 'started_at', 'completed_at', 'completed_by_user_id'])]
class ReturnSession extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function consignment(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\Consignment');
    }

    public function picUser(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\User', 'pic_user_id');
    }

    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\User', 'completed_by_user_id');
    }

    public function returnSessionItems(): HasMany
    {
        return $this->hasMany('App\\Models\\ReturnSessionItem');
    }

    public function reconciliation(): HasOne
    {
        return $this->hasOne('App\\Models\\Reconciliation');
    }
}
