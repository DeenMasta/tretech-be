<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disposals', function (Blueprint $table) {
            $table->id();
            $table->string('disposal_no')->unique('uq_disposals_disposal_no');
            $table->timestamp('disposed_at');
            $table->unsignedBigInteger('pic_user_id');
            $table->string('status', 50);
            $table->text('remarks')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('completed_by_user_id')->nullable();
            $table->timestamps();

            $table->index('pic_user_id', 'idx_disposals_pic_user_id');
            $table->index('status', 'idx_disposals_status');
            $table->index('disposed_at', 'idx_disposals_disposed_at');
            $table->index('completed_by_user_id', 'idx_disposals_completed_by_user_id');

            $table->foreign('pic_user_id', 'fk_disposals_pic_user_id')
                ->references('id')->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreign('completed_by_user_id', 'fk_disposals_completed_by_user_id')
                ->references('id')->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disposals');
    }
};
