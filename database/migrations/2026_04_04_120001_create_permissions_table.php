<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('permission_code')->unique(); // e.g., 'products.view'
            $table->string('permission_name'); // e.g., 'View Products'
            $table->string('module')->nullable(); // e.g., 'Master Data', 'Stock-In'
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('module');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
