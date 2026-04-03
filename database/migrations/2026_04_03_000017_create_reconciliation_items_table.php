<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliation_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reconciliation_id');
            $table->unsignedBigInteger('lot_id');
            $table->string('result', 100);
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['reconciliation_id', 'lot_id'], 'uq_reconciliation_items_reconciliation_id_lot_id');
            $table->index('lot_id', 'idx_reconciliation_items_lot_id');
            $table->index('result', 'idx_reconciliation_items_result');

            $table->foreign('reconciliation_id', 'fk_reconciliation_items_reconciliation_id')
                ->references('id')->on('reconciliations')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreign('lot_id', 'fk_reconciliation_items_lot_id')
                ->references('id')->on('lots')
                ->onUpdate('cascade')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_items');
    }
};
