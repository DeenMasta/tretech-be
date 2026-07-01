<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['product_id', 'instrument_set_id', 'supplier_id', 'lot_number', 'is_system_generated_lot', 'manufacturing_date', 'expiry_date', 'status', 'current_location_type', 'current_location_id', 'remarks', 'received_at', 'quantity', 'quantity_available', 'quantity_consigned'])]
class Lot extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'received_at' => 'datetime',
            'is_system_generated_lot' => 'boolean',
            'quantity' => 'integer',
            'quantity_available' => 'integer',
            'quantity_consigned' => 'integer',
        ];
    }

    public function hasAvailableStock(int $qty = 1): bool
    {
        return $this->quantity_available >= $qty;
    }

    public function isFullyDepleted(): bool
    {
        return $this->quantity_available === 0;
    }

    /**
     * A Lot represents either a product instance or a set instance.
     * Use these helpers instead of inspecting the FK columns directly.
     */
    public function isSetInstance(): bool
    {
        return $this->instrument_set_id !== null && $this->product_id === null;
    }

    public function isProductInstance(): bool
    {
        return $this->product_id !== null && $this->instrument_set_id === null;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\Product');
    }

    public function instrumentSet(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\InstrumentSet');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo('App\\Models\\Supplier');
    }

    public function qrLabel(): HasOne
    {
        return $this->hasOne('App\\Models\\QrLabel');
    }

    public function qrPrintJobs(): HasMany
    {
        return $this->hasMany('App\\Models\\QrPrintJob');
    }

    public function stockInItems(): HasMany
    {
        return $this->hasMany('App\\Models\\StockInItem');
    }

    public function consignmentItems(): HasMany
    {
        return $this->hasMany('App\\Models\\ConsignmentItem');
    }

    public function returnSessionItems(): HasMany
    {
        return $this->hasMany('App\\Models\\ReturnSessionItem');
    }

    public function disposalItems(): HasMany
    {
        return $this->hasMany('App\\Models\\DisposalItem');
    }

    public function supplierReturnItems(): HasMany
    {
        return $this->hasMany('App\\Models\\SupplierReturnItem');
    }

    public function lotMovements(): HasMany
    {
        return $this->hasMany('App\\Models\\LotMovement');
    }

    public function lotHolding(): HasOne
    {
        return $this->hasOne('App\\Models\\LotHolding');
    }
}
