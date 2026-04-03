<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_summary_push_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('usage_summary_id');
            $table->integer('attempt_no');
            $table->string('idempotency_key');
            $table->string('endpoint_url', 500)->nullable();
            $table->string('status', 30);
            $table->json('request_payload_json');
            $table->integer('response_code')->nullable();
            $table->text('response_body')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('pushed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['usage_summary_id', 'attempt_no'], 'uq_usage_summary_push_logs_usage_summary_attempt');
            $table->index('status', 'idx_usage_summary_push_logs_status');
            $table->index('idempotency_key', 'idx_usage_summary_push_logs_idempotency_key');
            $table->index('next_retry_at', 'idx_usage_summary_push_logs_next_retry_at');

            $table->foreign('usage_summary_id', 'fk_usage_summary_push_logs_usage_summary_id')
                ->references('id')->on('usage_summaries')
                ->onUpdate('cascade')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_summary_push_logs');
    }
};
