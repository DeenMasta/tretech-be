<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lots', function (Blueprint $table) {
            // Drop the old global unique lot number constraint
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropUnique('uq_lots_lot_number');
            } else {
                // SQLite doesn't support dropUnique natively in older versions, but let's assume modern Laravel handles it
                // Actually Laravel handles dropping unique indexes on SQLite starting 8.x
                $table->dropUnique('uq_lots_lot_number');
            }

            // Add quantity columns
            $table->unsignedInteger('quantity')->default(1)->after('received_at');
            $table->unsignedInteger('quantity_available')->default(1)->after('quantity');
            $table->unsignedInteger('quantity_consigned')->default(0)->after('quantity_available');

            // Add new unique constraint per product
            $table->unique(['product_id', 'lot_number'], 'uq_lots_product_id_lot_number');
        });
    }

    public function down(): void
    {
        Schema::table('lots', function (Blueprint $table) {
            $table->dropUnique('uq_lots_product_id_lot_number');
            
            $table->dropColumn(['quantity', 'quantity_available', 'quantity_consigned']);
            
            $table->unique('lot_number', 'uq_lots_lot_number');
        });
    }
};
