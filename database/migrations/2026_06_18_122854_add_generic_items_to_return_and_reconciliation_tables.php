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
        Schema::table('return_session_items', function (Blueprint $table) {
            $table->unsignedBigInteger('instrument_set_id')->nullable()->after('lot_id');
            $table->unsignedBigInteger('product_id')->nullable()->after('instrument_set_id');
            
            $table->unsignedBigInteger('lot_id')->nullable()->change();
        });

        Schema::table('reconciliation_items', function (Blueprint $table) {
            $table->unsignedBigInteger('instrument_set_id')->nullable()->after('lot_id');
            $table->unsignedBigInteger('product_id')->nullable()->after('instrument_set_id');

            $table->unsignedBigInteger('lot_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('return_and_reconciliation_tables', function (Blueprint $table) {
            //
        });
    }
};
