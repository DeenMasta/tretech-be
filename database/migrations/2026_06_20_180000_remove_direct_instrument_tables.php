<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (Schema::hasColumn('return_session_set_instrument_items', 'set_instrument_id')) {
            if ($driver === 'mysql') {
                DB::statement('ALTER TABLE `return_session_set_instrument_items` DROP FOREIGN KEY `fk_rssii_set_instrument_id`');
            } else {
                Schema::table('return_session_set_instrument_items', function (Blueprint $table) {
                    $table->dropForeign(['set_instrument_id']);
                });
            }

            Schema::table('return_session_set_instrument_items', function (Blueprint $table) {
                $table->dropIndex('idx_rssii_set_instrument_id');
                $table->dropColumn('set_instrument_id');
            });
        }

        if (Schema::hasColumn('reconciliation_set_instrument_results', 'set_instrument_id')) {
            if ($driver === 'mysql') {
                DB::statement('ALTER TABLE `reconciliation_set_instrument_results` DROP FOREIGN KEY `fk_recon_set_results_set_instrument_id`');
            } else {
                Schema::table('reconciliation_set_instrument_results', function (Blueprint $table) {
                    $table->dropForeign(['set_instrument_id']);
                });
            }

            Schema::table('reconciliation_set_instrument_results', function (Blueprint $table) {
                $table->dropIndex('idx_recon_set_results_set_instrument_id');
                $table->dropColumn('set_instrument_id');
            });
        }

        Schema::dropIfExists('set_instrument_instances');
        Schema::dropIfExists('set_instruments');
    }

    public function down(): void
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
            $table->unique(['instrument_set_id', 'code'], 'uq_set_instruments_set_code');
            $table->foreign('instrument_set_id', 'fk_set_instruments_set_id')
                ->references('id')->on('instrument_sets')
                ->cascadeOnDelete();
        });

        Schema::create('set_instrument_instances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lot_id');
            $table->unsignedBigInteger('instrument_set_id');
            $table->unsignedBigInteger('set_instrument_id');
            $table->unsignedBigInteger('stock_in_id');
            $table->unsignedBigInteger('stock_in_item_id');
            $table->string('instance_number')->unique('uq_set_instrument_instances_number');
            $table->string('status', 50)->default('available');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index('lot_id', 'idx_set_instr_instances_lot_id');
            $table->index('instrument_set_id', 'idx_set_instr_instances_set_id');
            $table->index('set_instrument_id', 'idx_set_instr_instances_item_id');
            $table->index('stock_in_id', 'idx_set_instr_instances_stock_in_id');
            $table->index('stock_in_item_id', 'idx_set_instr_instances_stock_in_item_id');
            $table->index('status', 'idx_set_instr_instances_status');

            $table->foreign('lot_id', 'fk_set_instr_instances_lot_id')
                ->references('id')->on('lots')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreign('instrument_set_id', 'fk_set_instr_instances_set_id')
                ->references('id')->on('instrument_sets')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreign('set_instrument_id', 'fk_set_instr_instances_item_id')
                ->references('id')->on('set_instruments')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreign('stock_in_id', 'fk_set_instr_instances_stock_in_id')
                ->references('id')->on('stock_ins')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreign('stock_in_item_id', 'fk_set_instr_instances_stock_in_item_id')
                ->references('id')->on('stock_in_items')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });

        Schema::table('reconciliation_set_instrument_results', function (Blueprint $table) {
            if (!Schema::hasColumn('reconciliation_set_instrument_results', 'set_instrument_id')) {
                $table->unsignedBigInteger('set_instrument_id')->nullable()->after('reconciliation_item_id');
                $table->index('set_instrument_id', 'idx_recon_set_results_set_instrument_id');
                $table->foreign('set_instrument_id', 'fk_recon_set_results_set_instrument_id')
                    ->references('id')->on('set_instruments')
                    ->onUpdate('cascade')
                    ->onDelete('restrict');
            }
        });

        Schema::table('return_session_set_instrument_items', function (Blueprint $table) {
            if (!Schema::hasColumn('return_session_set_instrument_items', 'set_instrument_id')) {
                $table->unsignedBigInteger('set_instrument_id')->nullable()->after('return_session_item_id');
                $table->index('set_instrument_id', 'idx_rssii_set_instrument_id');
                $table->foreign('set_instrument_id', 'fk_rssii_set_instrument_id')
                    ->references('id')->on('set_instruments')
                    ->onUpdate('cascade')
                    ->onDelete('restrict');
            }
        });
    }
};
