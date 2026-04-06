<?php

namespace App\Http\Controllers\Api\V1\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MasterData\StoreUserRequest;
use App\Http\Requests\Api\V1\MasterData\UpdateUserRequest;
use App\Http\Resources\Api\V1\MasterData\UserResource;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\MasterData\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
        private readonly AuditLogService $auditLogService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->integer('per_page', 15), 100));
        $paginator = $this->userService->paginate($request->only(['search', 'role_id', 'is_active']), $perPage);

        return $this->paginatedResponse(
            items: UserResource::collection($paginator->items())->resolve(),
            total: $paginator->total(),
            perPage: $paginator->perPage(),
            currentPage: $paginator->currentPage(),
            message: 'Users fetched successfully'
        );
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->userService->create($request->validated());

        $this->auditLogService->logModelAction(
            auditableType: User::class,
            auditableId: $user->id,
            actionType: 'create',
            actor: $request->user(),
            description: "Created user {$user->email}",
            ipAddress: (string) $request->ip(),
            deviceId: $request->header('X-Device-Id'),
            before: null,
            after: $user->toArray()
        );

        return $this->successResponse(new UserResource($user), 'User created successfully', 201);
    }

    public function show(User $user): JsonResponse
    {
        $user->loadMissing('role');

        return $this->successResponse(new UserResource($user), 'User fetched successfully');
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $before = $user->toArray();
        $updated = $this->userService->update($user, $request->validated());

        $this->auditLogService->logModelAction(
            auditableType: User::class,
            auditableId: $updated->id,
            actionType: 'update',
            actor: $request->user(),
            description: "Updated user {$updated->email}",
            ipAddress: (string) $request->ip(),
            deviceId: $request->header('X-Device-Id'),
            before: $before,
            after: $updated->toArray()
        );

        return $this->successResponse(new UserResource($updated), 'User updated successfully');
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $before = $user->toArray();
        $id = $user->id;
        $email = $user->email;

        $this->userService->delete($user);

        $this->auditLogService->logModelAction(
            auditableType: User::class,
            auditableId: $id,
            actionType: 'delete',
            actor: $request->user(),
            description: "Deleted user {$email}",
            ipAddress: (string) $request->ip(),
            deviceId: $request->header('X-Device-Id'),
            before: $before,
            after: null
        );

        return $this->successResponse(null, 'User deleted successfully');
    }
}
