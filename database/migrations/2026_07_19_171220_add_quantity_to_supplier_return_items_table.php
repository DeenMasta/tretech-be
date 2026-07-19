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
        Schema::table('supplier_return_items', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->default(1)->after('lot_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supplier_return_items', function (Blueprint $table) {
            $table->dropColumn('quantity');
        });
    }
};
