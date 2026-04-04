<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $roleId = Role::query()->where('role_code', 'logistic_staff')->value('id')
            ?? Role::query()->where('role_code', 'admin')->value('id')
            ?? Role::query()->value('id');

        return [
            'role_id' => $roleId,
            'full_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password_hash' => static::$password ??= Hash::make('password123'),
            'is_active' => true,
            'last_login_at' => null,
        ];
    }
}
