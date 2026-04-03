<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_session_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('return_session_id');
            $table->unsignedBigInteger('lot_id');
            $table->timestamp('returned_at');
            $table->unsignedBigInteger('returned_by_user_id');
            $table->text('source_qr_payload')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['return_session_id', 'lot_id'], 'uq_return_session_items_return_session_id_lot_id');
            $table->index('lot_id', 'idx_return_session_items_lot_id');
            $table->index('returned_by_user_id', 'idx_return_session_items_returned_by_user_id');

            $table->foreign('return_session_id', 'fk_return_session_items_return_session_id')
                ->references('id')->on('return_sessions')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreign('lot_id', 'fk_return_session_items_lot_id')
                ->references('id')->on('lots')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreign('returned_by_user_id', 'fk_return_session_items_returned_by_user_id')
                ->references('id')->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_session_items');
    }
};
