<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * set_instruments
 * ----------------------------------------------------------------------------
 * Stores instruments that belong to an InstrumentSet directly, without
 * being registered as Products. This complements `instrument_set_items`
 * (which links existing products to a set) by allowing simple, descriptive
 * members that only exist inside the set.
 *
 * Each row is uniquely identifiable inside its parent set via either:
 *   - the optional `code` column (engraving / asset tag), or
 *   - the row id (if `code` is null).
 *
 * These rows are referenced by `reconciliation_set_instrument_results` so
 * reconciliation can mark individual instruments inside a set instance as
 * returned / missing / damaged.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('set_instruments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('instrument_set_id');
            $table->string('code')->nullable();
            $table->string('name');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('remarks')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['instrument_set_id', 'sort_order'], 'idx_set_instruments_set_sort');
            $table->index('is_active', 'idx_set_instruments_is_active');

            // MySQL allows multiple NULLs in a composite unique, so this lets
            // unnamed (codeless) members coexist while preventing duplicate codes.
            $table->unique(['instrument_set_id', 'code'], 'uq_set_instruments_set_code');

            $table->foreign('instrument_set_id', 'fk_set_instruments_set_id')
                ->references('id')->on('instrument_sets')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('set_instruments');
    }
};
