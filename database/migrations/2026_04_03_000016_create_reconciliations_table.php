<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('consignment_id')->unique('uq_reconciliations_consignment_id');
            $table->unsignedBigInteger('return_session_id')->unique('uq_reconciliations_return_session_id');
            $table->string('reconciliation_no')->unique('uq_reconciliations_reconciliation_no');
            $table->unsignedBigInteger('pic_user_id');
            $table->string('status', 50);
            $table->text('remarks')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('completed_by_user_id')->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->unsignedBigInteger('reopened_by_user_id')->nullable();
            $table->text('reopen_reason')->nullable();
            $table->timestamps();

            $table->index('status', 'idx_reconciliations_status');
            $table->index('pic_user_id', 'idx_reconciliations_pic_user_id');
            $table->index('completed_by_user_id', 'idx_reconciliations_completed_by_user_id');
            $table->index('reopened_by_user_id', 'idx_reconciliations_reopened_by_user_id');

            $table->foreign('consignment_id', 'fk_reconciliations_consignment_id')
                ->references('id')->on('consignments')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreign('return_session_id', 'fk_reconciliations_return_session_id')
                ->references('id')->on('return_sessions')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreign('pic_user_id', 'fk_reconciliations_pic_user_id')
                ->references('id')->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreign('completed_by_user_id', 'fk_reconciliations_completed_by_user_id')
                ->references('id')->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreign('reopened_by_user_id', 'fk_reconciliations_reopened_by_user_id')
                ->references('id')->on('users')
                ->onUpdate('cascade')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliations');
    }
};
