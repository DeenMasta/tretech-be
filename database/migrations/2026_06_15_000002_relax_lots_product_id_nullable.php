<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Make lots.product_id nullable so a Lot can represent either:
 *   - a product instance (product_id set, instrument_set_id null), or
 *   - a set instance    (instrument_set_id set, product_id null).
 *
 * The mutual-exclusion rule (exactly one of the two FKs must be set) is
 * enforced at the application layer (Lot model + services), since portable
 * CHECK constraints aren't reliable across MySQL versions.
 *
 * Existing rows are unaffected — they all have product_id populated.
 */
return new class extends Migration {
    public function up(): void
    {
        // We need to drop the FK before changing nullability on MySQL.
        Schema::table('lots', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign('fk_lots_product_id');
            }
        });

        // Use native schema change for sqlite tests, raw DDL for MySQL.
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('lots', function (Blueprint $table) {
                $table->unsignedBigInteger('product_id')->nullable()->change();
            });
        } else {
            DB::statement('ALTER TABLE `lots` MODIFY `product_id` BIGINT UNSIGNED NULL');
        }

        Schema::table('lots', function (Blueprint $table) {
            $table->foreign('product_id', 'fk_lots_product_id')
                ->references('id')->on('products')
                ->onUpdate('cascade')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        // Roll back to NOT NULL. Any rows where product_id is null must be
        // cleaned up manually first; we deliberately do not coerce them here.
        Schema::table('lots', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                // Drop existing foreign key
                $table->dropForeign('fk_lots_product_id');
            }
        });

        if (DB::getDriverName() === 'sqlite') {
            Schema::table('lots', function (Blueprint $table) {
                $table->unsignedBigInteger('product_id')->nullable(false)->change();
            });
        } else {
            DB::statement('ALTER TABLE `lots` MODIFY `product_id` BIGINT UNSIGNED NOT NULL');
        }

        Schema::table('lots', function (Blueprint $table) {
            $table->foreign('product_id', 'fk_lots_product_id')
                ->references('id')->on('products')
                ->onUpdate('cascade')
                ->onDelete('restrict');
        });
    }
};
