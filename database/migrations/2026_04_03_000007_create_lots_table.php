<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('instrument_set_id')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->string('lot_number')->unique('uq_lots_lot_number');

            $table->boolean('is_system_generated_lot')->default(false);
            $table->string('supplier_batch_code');
            $table->date('expiry_date')->nullable();
            $table->string('status', 100);
            $table->string('current_location_type', 100)->nullable();
            $table->unsignedBigInteger('current_location_id')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index('product_id', 'idx_lots_product_id');
            $table->index('instrument_set_id', 'idx_lots_instrument_set_id');
            $table->index('supplier_id', 'idx_lots_supplier_id');
            $table->index('status', 'idx_lots_status');
            $table->index(['current_location_type', 'current_location_id'], 'idx_lots_location');
            $table->index('supplier_batch_code', 'idx_lots_supplier_batch_code');
            $table->index('expiry_date', 'idx_lots_expiry_date');


            $table->foreign('product_id', 'fk_lots_product_id')
                ->references('id')->on('products')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreign('instrument_set_id', 'fk_lots_instrument_set_id')
                ->references('id')->on('instrument_sets')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreign('supplier_id', 'fk_lots_supplier_id')
                ->references('id')->on('suppliers')
                ->onUpdate('cascade')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lots');
    }
};
