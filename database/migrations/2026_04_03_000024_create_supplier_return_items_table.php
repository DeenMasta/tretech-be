<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_return_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supplier_return_id');
            $table->unsignedBigInteger('lot_id');
            $table->string('return_reason');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['supplier_return_id', 'lot_id'], 'uq_supplier_return_items_supplier_return_id_lot_id');
            $table->index('lot_id', 'idx_supplier_return_items_lot_id');

            $table->foreign('supplier_return_id', 'fk_supplier_return_items_supplier_return_id')
                ->references('id')->on('supplier_returns')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreign('lot_id', 'fk_supplier_return_items_lot_id')
                ->references('id')->on('lots')
                ->onUpdate('cascade')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_return_items');
    }
};
