<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_in_items', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->default(1)->after('expiry_date');
        });

        Schema::table('consignment_items', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->default(1)->after('instrument_set_id');
        });

        Schema::table('lot_movements', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->default(1)->after('to_location_id');
        });

        Schema::table('return_session_items', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->default(1)->after('product_id');
        });

        Schema::table('reconciliation_items', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->default(1)->after('product_id');
            $table->unsignedInteger('used_quantity')->default(0)->after('quantity');
            $table->unsignedInteger('returned_quantity')->default(0)->after('used_quantity');
        });

        Schema::table('disposal_items', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->default(1)->after('lot_id');
        });
    }

    public function down(): void
    {
        Schema::table('stock_in_items', function (Blueprint $table) { $table->dropColumn('quantity'); });
        Schema::table('consignment_items', function (Blueprint $table) { $table->dropColumn('quantity'); });
        Schema::table('lot_movements', function (Blueprint $table) { $table->dropColumn('quantity'); });
        Schema::table('return_session_items', function (Blueprint $table) { $table->dropColumn('quantity'); });
        Schema::table('reconciliation_items', function (Blueprint $table) { $table->dropColumn(['quantity', 'used_quantity', 'returned_quantity']); });
        Schema::table('disposal_items', function (Blueprint $table) { $table->dropColumn('quantity'); });
    }
};
