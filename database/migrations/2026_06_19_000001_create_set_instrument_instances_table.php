<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('set_instrument_instances');
    }
};
