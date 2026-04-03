<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('consignment_id')->unique('uq_return_sessions_consignment_id');
            $table->string('return_session_no')->unique('uq_return_sessions_return_session_no');
            $table->unsignedBigInteger('pic_user_id');
            $table->string('status', 50);
            $table->text('remarks')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('completed_by_user_id')->nullable();
            $table->timestamps();

            $table->index('status', 'idx_return_sessions_status');
            $table->index('pic_user_id', 'idx_return_sessions_pic_user_id');
            $table->index('completed_by_user_id', 'idx_return_sessions_completed_by_user_id');

            $table->foreign('consignment_id', 'fk_return_sessions_consignment_id')
                ->references('id')->on('consignments')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreign('pic_user_id', 'fk_return_sessions_pic_user_id')
                ->references('id')->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreign('completed_by_user_id', 'fk_return_sessions_completed_by_user_id')
                ->references('id')->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_sessions');
    }
};
