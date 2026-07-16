<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stock_in_items', function (Blueprint $table) {
            $table->unique(['stock_in_id', 'product_id', 'scanned_lot_number'], 'uq_stock_in_items_session_product_lot');
            $table->dropUnique('uq_stock_in_items_stock_in_id_scanned_lot_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_in_items', function (Blueprint $table) {
            $table->unique(['stock_in_id', 'scanned_lot_number'], 'uq_stock_in_items_stock_in_id_scanned_lot_number');
            $table->dropUnique('uq_stock_in_items_session_product_lot');
        });
    }
};
