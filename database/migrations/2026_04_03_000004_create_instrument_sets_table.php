<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instrument_sets', function (Blueprint $table) {
            $table->id();
            $table->string('set_code')->nullable()->unique('uq_instrument_sets_set_code');
            $table->string('set_name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active', 'idx_instrument_sets_is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instrument_sets');
    }
};
