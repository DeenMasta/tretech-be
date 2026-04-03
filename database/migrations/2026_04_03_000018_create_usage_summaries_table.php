<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_summaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reconciliation_id')->unique('uq_usage_summaries_reconciliation_id');
            $table->string('summary_no')->unique('uq_usage_summaries_summary_no');
            $table->timestamp('generated_at');
            $table->unsignedBigInteger('generated_by_user_id');
            $table->string('status', 50);
            $table->timestamps();

            $table->index('status', 'idx_usage_summaries_status');
            $table->index('generated_by_user_id', 'idx_usage_summaries_generated_by_user_id');

            $table->foreign('reconciliation_id', 'fk_usage_summaries_reconciliation_id')
                ->references('id')->on('reconciliations')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreign('generated_by_user_id', 'fk_usage_summaries_generated_by_user_id')
                ->references('id')->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_summaries');
    }
};
