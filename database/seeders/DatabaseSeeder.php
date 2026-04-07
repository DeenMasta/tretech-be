<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Core data — safe for all environments
        $this->call(PermissionSeeder::class);
        $this->call(AdminUserSeeder::class);
        $this->call(SampleMasterDataSeeder::class);

        // Scenario data — only for local / staging / UAT
        if (app()->environment(['local', 'staging', 'uat'])) {
            $this->call(UatSeeder::class);
        }
    }
}
