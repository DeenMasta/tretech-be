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
        Schema::table('consignments', function (Blueprint $table) {
            $table->string('surgeon_name')->nullable()->after('status');
            $table->string('case_name')->nullable()->after('surgeon_name');
            $table->date('case_date')->nullable()->after('case_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consignments', function (Blueprint $table) {
            $table->dropColumn(['surgeon_name', 'case_name', 'case_date']);
        });
    }
};
