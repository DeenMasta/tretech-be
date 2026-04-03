<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_ins', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supplier_id');
            $table->string('session_no')->unique('uq_stock_ins_session_no');
            $table->string('do_number');
            $table->timestamp('stock_in_at');
            $table->unsignedBigInteger('pic_user_id');
            $table->string('status', 50);
            $table->text('remarks')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->unsignedBigInteger('confirmed_by_user_id')->nullable();
            $table->timestamps();

            $table->index('supplier_id', 'idx_stock_ins_supplier_id');
            $table->index('status', 'idx_stock_ins_status');
            $table->index('stock_in_at', 'idx_stock_ins_stock_in_at');
            $table->index('pic_user_id', 'idx_stock_ins_pic_user_id');
            $table->index('confirmed_by_user_id', 'idx_stock_ins_confirmed_by_user_id');

            $table->foreign('supplier_id', 'fk_stock_ins_supplier_id')
                ->references('id')->on('suppliers')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreign('pic_user_id', 'fk_stock_ins_pic_user_id')
                ->references('id')->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreign('confirmed_by_user_id', 'fk_stock_ins_confirmed_by_user_id')
                ->references('id')->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_ins');
    }
};
