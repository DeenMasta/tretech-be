<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['client_id', 'consignment_no', 'consignment_at', 'pic_user_id', 'status', 'surgeon_name', 'case_name', 'case_date', 'remarks', 'confirmed_at', 'confirmed_by_user_id', 'edited_after_confirmation', 'last_post_confirm_edit_at', 'last_post_confirm_edit_by_user_id', 'last_post_confirm_edit_reason'])]
class Consignment extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'consignment_at' => 'datetime',
            'case_date' => 'date',
            'confirmed_at' => 'datetime',
            'last_post_confirm_edit_at' => 'datetime',
            'edited_after_confirmation' => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\Client');
    }

    public function picUser(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\User', 'pic_user_id');
    }

    public function confirmedByUser(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\User', 'confirmed_by_user_id');
    }

    public function lastPostConfirmEditByUser(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\User', 'last_post_confirm_edit_by_user_id');
    }

    public function consignmentItems(): HasMany
    {
        return $this->hasMany('App\\Models\\ConsignmentItem');
    }

    public function returnSession(): HasOne
    {
        return $this->hasOne('App\\Models\\ReturnSession');
    }

    public function reconciliation(): HasOne
    {
        return $this->hasOne('App\\Models\\Reconciliation');
    }

    /**
     * Component lots consumed to supply generic instrument sets in this
     * consignment.
     */
    public function componentConsignmentMovements(): HasMany
    {
        return $this->hasMany('App\\Models\\LotMovement', 'reference_id')
            ->where('reference_type', self::class)
            ->where('movement_type', 'consigned')
            ->where('remarks', 'like', 'Set component consigned via%');
    }
}
