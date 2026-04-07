<?php

namespace Tests\Unit\Services;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Base test case for service-layer unit tests that need database access.
 */
abstract class ServiceTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    /**
     * Create a minimal user with an empty role (no permissions).
     */
    protected function makeActor(?string $email = null): User
    {
        $role = Role::query()->create([
            'role_code' => 'unit_test_' . str()->lower(str()->random(8)),
            'role_name' => 'Unit Test Role',
        ]);

        return User::query()->create([
            'role_id'       => $role->id,
            'full_name'     => 'Test Actor',
            'email'         => $email ?? 'actor_' . str()->lower(str()->random(8)) . '@unit.test',
            'password_hash' => 'secret',
            'is_active'     => true,
        ]);
    }
}
