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
            $table->string('push_url', 500)->nullable();
            $table->string('status', 30);
            $table->integer('http_status_code')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_body')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('pushed_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->unsignedInteger('retry_count')->default(0);
            $table->unsignedBigInteger('pushed_by_user_id')->nullable();
            $table->timestamps();

            $table->index('usage_summary_id', 'idx_usage_summary_push_logs_usage_summary_id');
            $table->index('status', 'idx_usage_summary_push_logs_status');
            $table->index('next_retry_at', 'idx_usage_summary_push_logs_next_retry_at');

            $table->foreign('usage_summary_id', 'fk_usage_summary_push_logs_usage_summary_id')
                ->references('id')->on('usage_summaries')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('pushed_by_user_id', 'fk_usage_summary_push_logs_pushed_by_user_id')
                ->references('id')->on('users')
                ->onUpdate('cascade')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_summary_push_logs');
    }
};
