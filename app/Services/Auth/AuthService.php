<?php

namespace App\Services\Auth;

use App\Exceptions\ForbiddenException;
use App\Exceptions\UnauthorizedException;
use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    /**
     * Authenticate user and issue a Sanctum token.
     *
     * @param array{email:string,password:string,device_name?:string|null} $credentials
     * @return array<string, mixed>
     */
    public function login(array $credentials, string $ipAddress, ?string $deviceId = null): array
    {
        $user = User::query()
            ->with(['role.permissions'])
            ->where('email', $credentials['email'])
            ->first();

        if (!$user || !Hash::check($credentials['password'], $user->password_hash)) {
            $this->logAttempt(
                user: $user,
                email: $credentials['email'],
                ipAddress: $ipAddress,
                deviceId: $deviceId,
                wasSuccessful: false,
                failureReason: 'invalid_credentials'
            );

            throw new UnauthorizedException('Invalid email or password');
        }

        if (!$user->is_active) {
            $this->logAttempt(
                user: $user,
                email: $credentials['email'],
                ipAddress: $ipAddress,
                deviceId: $deviceId,
                wasSuccessful: false,
                failureReason: 'user_inactive'
            );

            throw new ForbiddenException('User account is inactive');
        }

        $user->forceFill([
            'last_login_at' => now(),
        ])->save();

        $tokenName = $credentials['device_name'] ?? $deviceId ?? 'api-client';
        $token = $user->createToken($tokenName);

        $this->logAttempt(
            user: $user,
            email: $credentials['email'],
            ipAddress: $ipAddress,
            deviceId: $deviceId,
            wasSuccessful: true,
            failureReason: null
        );

        return [
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'user' => $this->transformUser($user),
            'permissions' => $user->getPermissionCodes(),
        ];
    }

    /**
     * Revoke current access token.
     */
    public function logout(User $user): void
    {
        $currentToken = $user->currentAccessToken();

        if ($currentToken?->id) {
            $user->tokens()->whereKey($currentToken->id)->delete();
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function me(User $user): array
    {
        $user->loadMissing('role.permissions');

        return [
            'user' => $this->transformUser($user),
            'permissions' => $user->getPermissionCodes(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function permissions(User $user): array
    {
        $user->loadMissing('role.permissions');

        return [
            'role' => $user->getRoleCode(),
            'permissions' => $user->getPermissionCodes(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transformUser(User $user): array
    {
        return [
            'id' => $user->id,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'role' => $user->getRoleCode(),
            'is_active' => $user->is_active,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
        ];
    }

    private function logAttempt(
        ?User $user,
        string $email,
        string $ipAddress,
        ?string $deviceId,
        bool $wasSuccessful,
        ?string $failureReason
    ): void {
        LoginAttempt::query()->create([
            'user_id' => $user?->id,
            'email' => $email,
            'ip_address' => $ipAddress,
            'device_id' => $deviceId,
            'was_successful' => $wasSuccessful,
            'failure_reason' => $failureReason,
            'attempted_at' => now(),
        ]);
    }
}
