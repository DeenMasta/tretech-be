<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // lots — composite for inventory filtering by status+expiry (expiry report, near-expiry queries)
        Schema::table('lots', function (Blueprint $table) {
            $table->index(['status', 'expiry_date'], 'idx_lots_status_expiry_date');
            $table->index(['status', 'current_location_type'], 'idx_lots_status_location_type');
        });

        // lot_movements — composite for timeline queries per lot
        Schema::table('lot_movements', function (Blueprint $table) {
            $table->index(['lot_id', 'performed_at'], 'idx_lot_movements_lot_id_performed_at');
        });

        // login_attempts — composite for brute-force detection (ip + failed + recent)
        Schema::table('login_attempts', function (Blueprint $table) {
            $table->index(['ip_address', 'was_successful', 'attempted_at'], 'idx_login_attempts_ip_success_at');
            $table->index(['email', 'was_successful', 'attempted_at'], 'idx_login_attempts_email_success_at');
        });

        // audit_logs — composite for user-scoped time-range queries
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['user_id', 'server_timestamp'], 'idx_audit_logs_user_id_timestamp');
            $table->index(['action_type', 'server_timestamp'], 'idx_audit_logs_action_type_timestamp');
        });
    }

    public function down(): void
    {
        Schema::table('lots', function (Blueprint $table) {
            $table->dropIndex('idx_lots_status_expiry_date');
            $table->dropIndex('idx_lots_status_location_type');
        });

        Schema::table('lot_movements', function (Blueprint $table) {
            $table->dropIndex('idx_lot_movements_lot_id_performed_at');
        });

        Schema::table('login_attempts', function (Blueprint $table) {
            $table->dropIndex('idx_login_attempts_ip_success_at');
            $table->dropIndex('idx_login_attempts_email_success_at');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('idx_audit_logs_user_id_timestamp');
            $table->dropIndex('idx_audit_logs_action_type_timestamp');
        });
    }
};
