<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('error_logs', function (Blueprint $table) {
            $table->id();
            $table->string('source');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->text('message');
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['source', 'created_at'], 'idx_error_logs_source_created_at');
            $table->index('source_id', 'idx_error_logs_source_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('error_logs');
    }
};
