<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consignment_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('consignment_id');
            $table->unsignedBigInteger('lot_id');
            $table->timestamp('issued_at');
            $table->unsignedBigInteger('issued_by_user_id');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['consignment_id', 'lot_id'], 'uq_consignment_items_consignment_id_lot_id');
            $table->index('lot_id', 'idx_consignment_items_lot_id');
            $table->index('issued_by_user_id', 'idx_consignment_items_issued_by_user_id');

            $table->foreign('consignment_id', 'fk_consignment_items_consignment_id')
                ->references('id')->on('consignments')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreign('lot_id', 'fk_consignment_items_lot_id')
                ->references('id')->on('lots')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreign('issued_by_user_id', 'fk_consignment_items_issued_by_user_id')
                ->references('id')->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consignment_items');
    }
};
