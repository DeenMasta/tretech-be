<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_labels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lot_id')->unique('uq_qr_labels_lot_id');
            $table->text('qr_payload');
            $table->timestamp('generated_at');
            $table->unsignedBigInteger('generated_by_user_id');
            $table->timestamps();

            $table->index('generated_by_user_id', 'idx_qr_labels_generated_by_user_id');

            $table->foreign('lot_id', 'fk_qr_labels_lot_id')
                ->references('id')->on('lots')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreign('generated_by_user_id', 'fk_qr_labels_generated_by_user_id')
                ->references('id')->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_labels');
    }
};
