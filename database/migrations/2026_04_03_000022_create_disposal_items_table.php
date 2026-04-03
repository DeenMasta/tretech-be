<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disposal_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('disposal_id');
            $table->unsignedBigInteger('lot_id');
            $table->string('disposal_category', 100);
            $table->string('reason_text');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['disposal_id', 'lot_id'], 'uq_disposal_items_disposal_id_lot_id');
            $table->index('lot_id', 'idx_disposal_items_lot_id');
            $table->index('disposal_category', 'idx_disposal_items_disposal_category');

            $table->foreign('disposal_id', 'fk_disposal_items_disposal_id')
                ->references('id')->on('disposals')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreign('lot_id', 'fk_disposal_items_lot_id')
                ->references('id')->on('lots')
                ->onUpdate('cascade')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disposal_items');
    }
};
