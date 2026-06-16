<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['client_name', 'client_type', 'phone', 'email', 'address', 'is_active'])]
class Client extends Model
{
    use HasFactory;

    public function consignments(): HasMany
    {
        return $this->hasMany('App\\Models\\Consignment');
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
