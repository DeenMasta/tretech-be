<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('ref_num')->unique('uq_products_ref_num');
            $table->string('product_name');
            $table->string('product_type')->nullable();
            $table->string('category')->nullable();
            $table->string('uom', 100)->nullable();
            $table->boolean('requires_expiry')->default(false);
            $table->boolean('requires_lot')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active', 'idx_products_is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
