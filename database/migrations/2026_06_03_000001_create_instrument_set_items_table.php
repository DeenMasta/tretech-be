<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('instrument_set_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('instrument_set_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['instrument_set_id', 'product_id'], 'uq_instrument_set_items_set_product');
            $table->index(['instrument_set_id', 'sort_order'], 'idx_instrument_set_items_set_sort');

            $table->foreign('instrument_set_id', 'fk_instrument_set_items_set_id')
                ->references('id')->on('instrument_sets')
                ->cascadeOnDelete();

            $table->foreign('product_id', 'fk_instrument_set_items_product_id')
                ->references('id')->on('products')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instrument_set_items');
    }
};
