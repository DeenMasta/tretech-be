<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_summary_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('usage_summary_id');
            $table->unsignedBigInteger('lot_id')->nullable();
            $table->unsignedBigInteger('product_id');
            $table->unsignedInteger('qty_consigned')->default(0);
            $table->unsignedInteger('qty_returned')->default(0);
            $table->unsignedInteger('qty_used')->default(0);
            $table->unsignedInteger('qty_disposed')->default(0);
            $table->unsignedInteger('qty_returned_to_supplier')->default(0);
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('consignment_id')->nullable();
            $table->timestamps();

            $table->unique(['usage_summary_id', 'lot_id'], 'uq_usage_summary_items_usage_summary_id_lot_id');
            $table->index('lot_id', 'idx_usage_summary_items_lot_id');
            $table->index('product_id', 'idx_usage_summary_items_product_id');
            $table->index('client_id', 'idx_usage_summary_items_client_id');
            $table->index('consignment_id', 'idx_usage_summary_items_consignment_id');

            $table->foreign('usage_summary_id', 'fk_usage_summary_items_usage_summary_id')
                ->references('id')->on('usage_summaries')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreign('lot_id', 'fk_usage_summary_items_lot_id')
                ->references('id')->on('lots')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreign('product_id', 'fk_usage_summary_items_product_id')
                ->references('id')->on('products')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreign('client_id', 'fk_usage_summary_items_client_id')
                ->references('id')->on('clients')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreign('consignment_id', 'fk_usage_summary_items_consignment_id')
                ->references('id')->on('consignments')
                ->onUpdate('cascade')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_summary_items');
    }
};
