<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('return_session_set_instrument_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('return_session_item_id');
            $table->unsignedBigInteger('set_instrument_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedInteger('returned_quantity')->default(0);
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index('return_session_item_id', 'idx_rssii_item_id');
            $table->index('set_instrument_id', 'idx_rssii_set_instrument_id');
            $table->index('product_id', 'idx_rssii_product_id');

            $table->foreign('return_session_item_id', 'fk_rssii_item_id')
                ->references('id')->on('return_session_items')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('set_instrument_id', 'fk_rssii_set_instrument_id')
                ->references('id')->on('set_instruments')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->foreign('product_id', 'fk_rssii_product_id')
                ->references('id')->on('products')
                ->onUpdate('cascade')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('return_session_set_instrument_items');
    }
};
