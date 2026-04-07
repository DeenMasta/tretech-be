<?php

namespace Tests\Feature\Api\V1;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

class AuthTest extends FeatureTestCase
{
    /**
     * Create a user whose password_hash is a real bcrypt hash and whose email
     * uses a real domain so the email:rfc,dns rule passes in tests.
     */
    private function makeLoginableUser(array $permissionCodes = [], bool $active = true): User
    {
        $role = Role::query()->create([
            'role_code' => 'auth_role_' . str()->lower(str()->random(8)),
            'role_name' => 'Auth Role ' . str()->random(4),
        ]);

        if ($permissionCodes !== []) {
            $ids = Permission::query()
                ->whereIn('permission_code', $permissionCodes)
                ->pluck('id')
                ->all();
            $role->permissions()->sync($ids);
        }

        return User::query()->create([
            'role_id'       => $role->id,
            'full_name'     => 'Auth Tester',
            'email'         => 'auth_' . str()->lower(str()->random(8)) . '@gmail.com',
            'password_hash' => Hash::make('Password123!'),
            'is_active'     => $active,
        ]);
    }

    // -------------------------------------------------------------------------
    // Login
    // -------------------------------------------------------------------------

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = $this->makeLoginableUser();

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'Password123!',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJsonStructure(['data' => ['token', 'user']]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = $this->makeLoginableUser();

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'WrongPassword!',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_login_fails_with_unknown_email(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'nobody@gmail.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_login_fails_with_inactive_user(): void
    {
        $user = $this->makeLoginableUser([], false);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'Password123!',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_login_requires_email_and_password(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Logout
    // -------------------------------------------------------------------------

    public function test_authenticated_user_can_logout(): void
    {
        $user = $this->makeUserWithPermissions([]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_guest_cannot_logout(): void
    {
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Me
    // -------------------------------------------------------------------------

    public function test_authenticated_user_can_get_own_profile(): void
    {
        $user = $this->makeUserWithPermissions([]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', $user->email);
    }

    public function test_guest_cannot_access_me(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Permissions
    // -------------------------------------------------------------------------

    public function test_authenticated_user_can_get_permissions(): void
    {
        $user = $this->makeUserWithPermissions(['products.view']);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/auth/permissions');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['permissions']]);

        $permissions = $response->json('data.permissions');
        $this->assertContains('products.view', $permissions);
    }

    public function test_guest_cannot_access_permissions(): void
    {
        $response = $this->getJson('/api/v1/auth/permissions');

        $response->assertStatus(401);
    }

    public function test_user_with_no_permissions_gets_empty_list(): void
    {
        $user = $this->makeUserWithPermissions([]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/auth/permissions');

        $response->assertOk();
        $this->assertEmpty($response->json('data.permissions'));
    }
}
