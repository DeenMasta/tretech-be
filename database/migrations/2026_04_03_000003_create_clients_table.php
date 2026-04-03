<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('client_name');
            $table->string('client_type', 100);
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('client_name', 'idx_clients_client_name');
            $table->index('client_type', 'idx_clients_client_type');
            $table->index('is_active', 'idx_clients_is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
