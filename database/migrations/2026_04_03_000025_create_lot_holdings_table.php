<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lot_holdings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lot_id');
            $table->string('holding_reason');
            $table->timestamp('assigned_at');
            $table->unsignedBigInteger('assigned_by_user_id');
            $table->timestamp('released_at')->nullable();
            $table->unsignedBigInteger('released_by_user_id')->nullable();
            $table->string('corrected_lot_number')->nullable();
            $table->text('resolution_reason')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index('lot_id', 'idx_lot_holdings_lot_id');
            $table->index('assigned_by_user_id', 'idx_lot_holdings_assigned_by_user_id');
            $table->index('released_by_user_id', 'idx_lot_holdings_released_by_user_id');
            $table->index('assigned_at', 'idx_lot_holdings_assigned_at');
            $table->index('released_at', 'idx_lot_holdings_released_at');

            $table->foreign('lot_id', 'fk_lot_holdings_lot_id')
                ->references('id')->on('lots')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreign('assigned_by_user_id', 'fk_lot_holdings_assigned_by_user_id')
                ->references('id')->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreign('released_by_user_id', 'fk_lot_holdings_released_by_user_id')
                ->references('id')->on('users')
                ->onUpdate('cascade')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lot_holdings');
    }
};
