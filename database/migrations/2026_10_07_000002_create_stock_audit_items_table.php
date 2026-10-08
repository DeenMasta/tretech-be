<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_audit_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_audit_id')->constrained('stock_audits')->cascadeOnDelete();
            $table->foreignId('lot_id')->nullable()->constrained('lots')->restrictOnDelete();
            $table->json('snapshot');
            $table->unsignedInteger('expected_quantity')->default(0);
            $table->unsignedInteger('counted_quantity')->nullable();
            $table->integer('variance_quantity')->nullable();
            $table->string('result', 30)->default('pending');
            $table->string('count_method', 20)->nullable();
            $table->text('source_qr_payload')->nullable();
            $table->timestamp('counted_at')->nullable();
            $table->foreignId('counted_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('remarks')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['stock_audit_id', 'lot_id'], 'uq_stock_audit_items_audit_lot');
            $table->index(['stock_audit_id', 'result'], 'idx_stock_audit_items_result');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_audit_items');
    }
};
