<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Allow stock-in items to represent either a product receipt (existing
 * behaviour) or a set-instance receipt (new). When entry_kind = 'set':
 *   - product_id is NULL
 *   - instrument_set_id points to the registered InstrumentSet
 *   - scanned_lot_number / supplier_batch_code / expiry_date are not required
 *   - finalize will mint a Lot tagged with that instrument_set_id and a
 *     system-generated set-instance number used as the lot_number.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('stock_in_items', function (Blueprint $table) {
            $table->dropForeign('fk_stock_in_items_product_id');
        });

        DB::statement('ALTER TABLE `stock_in_items` MODIFY `product_id` BIGINT UNSIGNED NULL');
        DB::statement("ALTER TABLE `stock_in_items` MODIFY `supplier_batch_code` VARCHAR(255) NULL");

        Schema::table('stock_in_items', function (Blueprint $table) {
            $table->string('entry_kind', 20)->default('product')->after('stock_in_id');
            $table->unsignedBigInteger('instrument_set_id')->nullable()->after('product_id');

            $table->index('entry_kind', 'idx_stock_in_items_entry_kind');
            $table->index('instrument_set_id', 'idx_stock_in_items_instrument_set_id');

            $table->foreign('product_id', 'fk_stock_in_items_product_id')
                ->references('id')->on('products')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->foreign('instrument_set_id', 'fk_stock_in_items_instrument_set_id')
                ->references('id')->on('instrument_sets')
                ->onUpdate('cascade')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('stock_in_items', function (Blueprint $table) {
            $table->dropForeign('fk_stock_in_items_product_id');
            $table->dropForeign('fk_stock_in_items_instrument_set_id');
            $table->dropIndex('idx_stock_in_items_entry_kind');
            $table->dropIndex('idx_stock_in_items_instrument_set_id');
            $table->dropColumn('entry_kind');
            $table->dropColumn('instrument_set_id');
        });

        DB::statement('ALTER TABLE `stock_in_items` MODIFY `product_id` BIGINT UNSIGNED NOT NULL');
        DB::statement("ALTER TABLE `stock_in_items` MODIFY `supplier_batch_code` VARCHAR(255) NOT NULL");

        Schema::table('stock_in_items', function (Blueprint $table) {
            $table->foreign('product_id', 'fk_stock_in_items_product_id')
                ->references('id')->on('products')
                ->onUpdate('cascade')
                ->onDelete('restrict');
        });
    }
};
