<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill existing instrument master-data records so they follow the
     * same lot-tracking rule as newly created and updated products.
     */
    public function up(): void
    {
        DB::table('products')
            ->whereRaw('LOWER(TRIM(product_type)) = ?', ['instrument'])
            ->update(['requires_lot' => true]);
    }

    /**
     * Existing values cannot be safely restored because the prior setting is
     * not known.
     */
    public function down(): void {}
};
