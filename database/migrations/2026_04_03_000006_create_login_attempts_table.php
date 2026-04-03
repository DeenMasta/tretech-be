<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('email');
            $table->string('ip_address', 45)->nullable();
            $table->string('device_id')->nullable();
            $table->boolean('was_successful')->default(false);
            $table->string('failure_reason')->nullable();
            $table->timestamp('attempted_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index('user_id', 'idx_login_attempts_user_id');
            $table->index('email', 'idx_login_attempts_email');
            $table->index('ip_address', 'idx_login_attempts_ip_address');
            $table->index('attempted_at', 'idx_login_attempts_attempted_at');
            $table->foreign('user_id', 'fk_login_attempts_user_id')
                ->references('id')->on('users')
                ->onUpdate('cascade')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
    }
};
