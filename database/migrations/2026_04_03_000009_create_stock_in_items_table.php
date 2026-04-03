<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_in_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stock_in_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('lot_id')->nullable();
            $table->string('scanned_lot_number')->nullable();
            $table->string('supplier_batch_code');
            $table->date('expiry_date')->nullable();
            $table->string('lot_entry_mode', 20)->default('scan');
            $table->string('expiry_entry_mode', 20)->default('scan');
            $table->boolean('missing_lot_flag')->default(false);
            $table->text('source_barcode')->nullable();
            $table->text('entry_override_reason')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['stock_in_id', 'scanned_lot_number'], 'uq_stock_in_items_stock_in_id_scanned_lot_number');
            $table->index('product_id', 'idx_stock_in_items_product_id');
            $table->index('lot_id', 'idx_stock_in_items_lot_id');
            $table->index('missing_lot_flag', 'idx_stock_in_items_missing_lot_flag');

            $table->foreign('stock_in_id', 'fk_stock_in_items_stock_in_id')
                ->references('id')->on('stock_ins')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreign('product_id', 'fk_stock_in_items_product_id')
                ->references('id')->on('products')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreign('lot_id', 'fk_stock_in_items_lot_id')
                ->references('id')->on('lots')
                ->onUpdate('cascade')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_in_items');
    }
};
