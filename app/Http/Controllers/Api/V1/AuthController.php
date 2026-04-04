<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $payload = $this->authService->login(
            credentials: $request->validated(),
            ipAddress: (string) $request->ip(),
            deviceId: $request->header('X-Device-Id')
        );

        return $this->successResponse($payload, 'Login successful');
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return $this->successResponse(null, 'Logout successful');
    }

    public function me(Request $request): JsonResponse
    {
        $payload = $this->authService->me($request->user());

        return $this->successResponse($payload, 'Authenticated user fetched successfully');
    }

    public function permissions(Request $request): JsonResponse
    {
        $payload = $this->authService->permissions($request->user());

        return $this->successResponse($payload, 'Permissions fetched successfully');
    }
}
