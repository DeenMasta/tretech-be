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
            $table->unsignedInteger('used_quantity')->default(0)->after('quantity');
            $table->unsignedInteger('damaged_quantity')->default(0)->after('used_quantity');
            $table->unsignedInteger('missing_quantity')->default(0)->after('damaged_quantity');
        });

        Schema::table('reconciliation_items', function (Blueprint $table) {
            $table->unsignedInteger('damaged_quantity')->default(0)->after('returned_quantity');
            $table->unsignedInteger('missing_quantity')->default(0)->after('damaged_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('return_session_items', function (Blueprint $table) {
            $table->dropColumn(['used_quantity', 'damaged_quantity', 'missing_quantity']);
        });

        Schema::table('reconciliation_items', function (Blueprint $table) {
            $table->dropColumn(['damaged_quantity', 'missing_quantity']);
        });
    }
};
