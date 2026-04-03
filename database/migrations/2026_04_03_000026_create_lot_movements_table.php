<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lot_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lot_id');
            $table->string('movement_type', 100);
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('from_status', 100)->nullable();
            $table->string('to_status', 100)->nullable();
            $table->string('from_location_type', 100)->nullable();
            $table->unsignedBigInteger('from_location_id')->nullable();
            $table->string('to_location_type', 100)->nullable();
            $table->unsignedBigInteger('to_location_id')->nullable();
            $table->timestamp('performed_at');
            $table->unsignedBigInteger('performed_by_user_id');
            $table->text('remarks')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('lot_id', 'idx_lot_movements_lot_id');
            $table->index('movement_type', 'idx_lot_movements_movement_type');
            $table->index(['reference_type', 'reference_id'], 'idx_lot_movements_reference_type_reference_id');
            $table->index('performed_at', 'idx_lot_movements_performed_at');
            $table->index('performed_by_user_id', 'idx_lot_movements_performed_by_user_id');

            $table->foreign('lot_id', 'fk_lot_movements_lot_id')
                ->references('id')->on('lots')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreign('performed_by_user_id', 'fk_lot_movements_performed_by_user_id')
                ->references('id')->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lot_movements');
    }
};
