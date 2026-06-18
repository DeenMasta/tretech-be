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
        Schema::table('consignment_items', function (Blueprint $table) {
            // entry_kind distinguishes lot-based items from instrument-set items.
            // Default to 'lot' so existing rows are unaffected.
            $table->string('entry_kind', 20)->default('lot')->after('id');

            // Nullable FK to instrument_sets — populated only when entry_kind = 'set'.
            $table->unsignedBigInteger('instrument_set_id')->nullable()->after('entry_kind');

            // Allow lot_id to be nullable for set-type items (no lot yet).
            $table->foreignId('lot_id')->nullable()->change();

            $table->foreign('instrument_set_id')
                  ->references('id')
                  ->on('instrument_sets')
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consignment_items', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign(['instrument_set_id']);
            }
            $table->dropColumn(['entry_kind', 'instrument_set_id']);

            // Restore lot_id as NOT NULL.
            $table->foreignId('lot_id')->nullable(false)->change();
        });
    }
};
