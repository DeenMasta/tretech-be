<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->string('consignment_no')->unique('uq_consignments_consignment_no');
            $table->timestamp('consignment_at');
            $table->unsignedBigInteger('pic_user_id');
            $table->string('status', 50);
            $table->text('remarks')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->unsignedBigInteger('confirmed_by_user_id')->nullable();
            $table->boolean('edited_after_confirmation')->default(false);
            $table->timestamp('last_post_confirm_edit_at')->nullable();
            $table->unsignedBigInteger('last_post_confirm_edit_by_user_id')->nullable();
            $table->text('last_post_confirm_edit_reason')->nullable();
            $table->timestamps();

            $table->index('client_id', 'idx_consignments_client_id');
            $table->index('status', 'idx_consignments_status');
            $table->index('consignment_at', 'idx_consignments_consignment_at');
            $table->index('pic_user_id', 'idx_consignments_pic_user_id');
            $table->index('confirmed_by_user_id', 'idx_consignments_confirmed_by_user_id');
            $table->index('last_post_confirm_edit_by_user_id', 'idx_consignments_last_post_confirm_edit_by_user_id');

            $table->foreign('client_id', 'fk_consignments_client_id')
                ->references('id')->on('clients')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreign('pic_user_id', 'fk_consignments_pic_user_id')
                ->references('id')->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreign('confirmed_by_user_id', 'fk_consignments_confirmed_by_user_id')
                ->references('id')->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreign('last_post_confirm_edit_by_user_id', 'fk_consignments_last_post_confirm_edit_by_user_id')
                ->references('id')->on('users')
                ->onUpdate('cascade')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consignments');
    }
};
