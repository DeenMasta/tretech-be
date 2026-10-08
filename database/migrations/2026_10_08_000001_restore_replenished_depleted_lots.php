<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('lots')
            ->where('quantity_available', '>', 0)
            ->whereNotIn('status', ['available', 'holding'])
            ->update([
                'status' => 'available',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // The previous status cannot be reconstructed safely from inventory balances.
    }
};
