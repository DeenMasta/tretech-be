<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE `lots` MODIFY `supplier_batch_code` VARCHAR(255) NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to NOT NULL
        // Note: This may fail if there are existing rows with NULL supplier_batch_code
        DB::statement('ALTER TABLE `lots` MODIFY `supplier_batch_code` VARCHAR(255) NOT NULL');
    }
};
