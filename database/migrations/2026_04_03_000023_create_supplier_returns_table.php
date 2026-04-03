<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_returns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supplier_id');
            $table->string('supplier_return_no')->unique('uq_supplier_returns_supplier_return_no');
            $table->timestamp('returned_at');
            $table->unsignedBigInteger('pic_user_id');
            $table->string('reference_no')->nullable();
            $table->string('status', 50);
            $table->text('remarks')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('completed_by_user_id')->nullable();
            $table->timestamps();

            $table->index('supplier_id', 'idx_supplier_returns_supplier_id');
            $table->index('pic_user_id', 'idx_supplier_returns_pic_user_id');
            $table->index('status', 'idx_supplier_returns_status');
            $table->index('returned_at', 'idx_supplier_returns_returned_at');
            $table->index('completed_by_user_id', 'idx_supplier_returns_completed_by_user_id');

            $table->foreign('supplier_id', 'fk_supplier_returns_supplier_id')
                ->references('id')->on('suppliers')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreign('pic_user_id', 'fk_supplier_returns_pic_user_id')
                ->references('id')->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreign('completed_by_user_id', 'fk_supplier_returns_completed_by_user_id')
                ->references('id')->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_returns');
    }
};
