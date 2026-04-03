<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('role_code_snapshot', 100)->nullable();
            $table->string('auditable_type', 100);
            $table->unsignedBigInteger('auditable_id');
            $table->string('action_type', 100);
            $table->text('description')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('device_id')->nullable();
            $table->json('before_json')->nullable();
            $table->json('after_json')->nullable();
            $table->timestamp('server_timestamp');
            $table->timestamp('created_at')->useCurrent();

            $table->index('user_id', 'idx_audit_logs_user_id');
            $table->index(['auditable_type', 'auditable_id'], 'idx_audit_logs_auditable_type_auditable_id');
            $table->index('action_type', 'idx_audit_logs_action_type');
            $table->index('server_timestamp', 'idx_audit_logs_server_timestamp');

            $table->foreign('user_id', 'fk_audit_logs_user_id')
                ->references('id')->on('users')
                ->onUpdate('cascade')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
