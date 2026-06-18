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
        Schema::table('reconciliation_set_instrument_results', function (Blueprint $table) {
            $table->unsignedInteger('used_quantity')->default(0)->after('returned_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reconciliation_set_instrument_results', function (Blueprint $table) {
            $table->dropColumn('used_quantity');
        });
    }
};
