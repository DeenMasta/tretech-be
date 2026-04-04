<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminRole = Role::query()->where('role_code', 'admin')->first();

        if (!$adminRole) {
            return;
        }

        $adminEmail = (string) config('app.admin.email', 'admin@tretech.com');
        $adminName = (string) config('app.admin.name', 'TRETECH Admin');
        $adminPassword = (string) config('app.admin.password', 'Admin123!');

        User::query()->updateOrCreate(
            ['email' => $adminEmail],
            [
                'role_id' => $adminRole->id,
                'full_name' => $adminName,
                'password_hash' => $adminPassword,
                'is_active' => true,
            ]
        );
    }
}
