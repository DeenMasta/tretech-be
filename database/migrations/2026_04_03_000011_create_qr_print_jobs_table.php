<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_print_jobs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lot_id');
            $table->unsignedBigInteger('qr_label_id');
            $table->string('action_type', 20);
            $table->text('reprint_reason')->nullable();
            $table->string('status', 20);
            $table->string('printer_name')->nullable();
            $table->string('device_id')->nullable();
            $table->text('tspl_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('requested_by_user_id');
            $table->timestamp('requested_at');
            $table->timestamp('printed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index('lot_id', 'idx_qr_print_jobs_lot_id');
            $table->index('qr_label_id', 'idx_qr_print_jobs_qr_label_id');
            $table->index('status', 'idx_qr_print_jobs_status');
            $table->index('requested_at', 'idx_qr_print_jobs_requested_at');
            $table->index('requested_by_user_id', 'idx_qr_print_jobs_requested_by_user_id');

            $table->foreign('lot_id', 'fk_qr_print_jobs_lot_id')
                ->references('id')->on('lots')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreign('qr_label_id', 'fk_qr_print_jobs_qr_label_id')
                ->references('id')->on('qr_labels')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreign('requested_by_user_id', 'fk_qr_print_jobs_requested_by_user_id')
                ->references('id')->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_print_jobs');
    }
};
