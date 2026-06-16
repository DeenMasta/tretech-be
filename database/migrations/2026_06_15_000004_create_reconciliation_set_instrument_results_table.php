<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * reconciliation_set_instrument_results
 * ----------------------------------------------------------------------------
 * Per-instrument reconciliation outcome for a set instance.
 *
 * Parent: reconciliation_items (one row per consigned lot).
 * Child:  one row per registered member of the set, capturing whether
 *         that individual instrument was returned, missing, damaged, etc.
 *
 * Each child row points at exactly one of:
 *   - set_instrument_id  : a non-product member registered via set_instruments
 *   - product_id         : a product member registered via instrument_set_items
 *
 * The result enum mirrors ReconciliationItem.result codes ('returned',
 * 'missing', 'damaged', etc.) and is stored as plain string for forward
 * compatibility.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('reconciliation_set_instrument_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reconciliation_item_id');
            $table->unsignedBigInteger('set_instrument_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedInteger('expected_quantity')->default(1);
            $table->unsignedInteger('returned_quantity')->default(0);
            $table->unsignedInteger('missing_quantity')->default(0);
            $table->unsignedInteger('damaged_quantity')->default(0);
            $table->string('result', 100);
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index('reconciliation_item_id', 'idx_recon_set_results_item_id');
            $table->index('set_instrument_id', 'idx_recon_set_results_set_instrument_id');
            $table->index('product_id', 'idx_recon_set_results_product_id');
            $table->index('result', 'idx_recon_set_results_result');

            $table->foreign('reconciliation_item_id', 'fk_recon_set_results_item_id')
                ->references('id')->on('reconciliation_items')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('set_instrument_id', 'fk_recon_set_results_set_instrument_id')
                ->references('id')->on('set_instruments')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->foreign('product_id', 'fk_recon_set_results_product_id')
                ->references('id')->on('products')
                ->onUpdate('cascade')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_set_instrument_results');
    }
};
