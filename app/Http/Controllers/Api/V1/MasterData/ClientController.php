<?php

namespace App\Http\Controllers\Api\V1\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MasterData\StoreClientRequest;
use App\Http\Requests\Api\V1\MasterData\UpdateClientRequest;
use App\Http\Resources\Api\V1\MasterData\ClientResource;
use App\Models\Client;
use App\Services\Audit\AuditLogService;
use App\Services\MasterData\ClientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function __construct(
        private readonly ClientService $clientService,
        private readonly AuditLogService $auditLogService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->integer('per_page', 15), 100));
        $paginator = $this->clientService->paginate($request->only(['search', 'client_type', 'is_active']), $perPage);

        return $this->paginatedResponse(
            items: ClientResource::collection($paginator->items())->resolve(),
            total: $paginator->total(),
            perPage: $paginator->perPage(),
            currentPage: $paginator->currentPage(),
            message: 'Clients fetched successfully'
        );
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        $client = $this->clientService->create($request->validated());

        $this->auditLogService->logModelAction(
            auditableType: Client::class,
            auditableId: $client->id,
            actionType: 'create',
            actor: $request->user(),
            description: "Created client {$client->client_name}",
            ipAddress: (string) $request->ip(),
            deviceId: $request->header('X-Device-Id'),
            before: null,
            after: $client->toArray()
        );

        return $this->successResponse(new ClientResource($client), 'Client created successfully', 201);
    }

    public function show(Client $client): JsonResponse
    {
        return $this->successResponse(new ClientResource($client), 'Client fetched successfully');
    }

    public function update(UpdateClientRequest $request, Client $client): JsonResponse
    {
        $before = $client->toArray();
        $updated = $this->clientService->update($client, $request->validated());

        $this->auditLogService->logModelAction(
            auditableType: Client::class,
            auditableId: $updated->id,
            actionType: 'update',
            actor: $request->user(),
            description: "Updated client {$updated->client_name}",
            ipAddress: (string) $request->ip(),
            deviceId: $request->header('X-Device-Id'),
            before: $before,
            after: $updated->toArray()
        );

        return $this->successResponse(new ClientResource($updated), 'Client updated successfully');
    }

    public function destroy(Request $request, Client $client): JsonResponse
    {
        $before = $client->toArray();
        $id = $client->id;
        $name = $client->client_name;

        $this->clientService->delete($client);

        $this->auditLogService->logModelAction(
            auditableType: Client::class,
            auditableId: $id,
            actionType: 'delete',
            actor: $request->user(),
            description: "Deleted client {$name}",
            ipAddress: (string) $request->ip(),
            deviceId: $request->header('X-Device-Id'),
            before: $before,
            after: null
        );

        return $this->successResponse(null, 'Client deleted successfully');
    }
}
