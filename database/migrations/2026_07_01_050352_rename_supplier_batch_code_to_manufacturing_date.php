<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lots', function (Blueprint $table) {
            $table->dropIndex('idx_lots_supplier_batch_code');
            $table->dropColumn('supplier_batch_code');
            $table->date('manufacturing_date')->nullable()->after('is_system_generated_lot');
            $table->index('manufacturing_date', 'idx_lots_manufacturing_date');
        });

        Schema::table('stock_in_items', function (Blueprint $table) {
            $table->dropColumn('supplier_batch_code');
            $table->date('manufacturing_date')->nullable()->after('scanned_lot_number');
        });
    }

    public function down(): void
    {
        Schema::table('lots', function (Blueprint $table) {
            $table->dropIndex('idx_lots_manufacturing_date');
            $table->dropColumn('manufacturing_date');
            $table->string('supplier_batch_code')->nullable();
            $table->index('supplier_batch_code', 'idx_lots_supplier_batch_code');
        });

        Schema::table('stock_in_items', function (Blueprint $table) {
            $table->dropColumn('manufacturing_date');
            $table->string('supplier_batch_code')->nullable();
        });
    }
};
