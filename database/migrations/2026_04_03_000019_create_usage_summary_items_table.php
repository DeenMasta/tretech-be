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
            $table->unsignedBigInteger('lot_id');
            $table->unsignedBigInteger('product_id');
            $table->string('product_ref_num_snapshot');
            $table->string('lot_number_snapshot');
            $table->date('expiry_date_snapshot')->nullable();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('consignment_id');
            $table->timestamps();

            $table->unique(['usage_summary_id', 'lot_id'], 'uq_usage_summary_items_usage_summary_id_lot_id');
            $table->index('lot_id', 'idx_usage_summary_items_lot_id');
            $table->index('product_id', 'idx_usage_summary_items_product_id');
            $table->index('client_id', 'idx_usage_summary_items_client_id');
            $table->index('consignment_id', 'idx_usage_summary_items_consignment_id');

            $table->foreign('usage_summary_id', 'fk_usage_summary_items_usage_summary_id')
                ->references('id')->on('usage_summaries')
                ->onUpdate('cascade')
                ->onDelete('restrict');
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
