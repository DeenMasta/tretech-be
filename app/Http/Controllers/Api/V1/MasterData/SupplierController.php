<?php

namespace App\Http\Controllers\Api\V1\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MasterData\StoreSupplierRequest;
use App\Http\Requests\Api\V1\MasterData\UpdateSupplierRequest;
use App\Http\Resources\Api\V1\MasterData\SupplierResource;
use App\Models\Supplier;
use App\Services\Audit\AuditLogService;
use App\Services\MasterData\SupplierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function __construct(
        private readonly SupplierService $supplierService,
        private readonly AuditLogService $auditLogService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->integer('per_page', 15), 100));
        $paginator = $this->supplierService->paginate($request->only(['search', 'is_active']), $perPage);

        return $this->paginatedResponse(
            items: SupplierResource::collection($paginator->items())->resolve(),
            total: $paginator->total(),
            perPage: $paginator->perPage(),
            currentPage: $paginator->currentPage(),
            message: 'Suppliers fetched successfully'
        );
    }

    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $supplier = $this->supplierService->create($request->validated());

        $this->auditLogService->logModelAction(
            auditableType: Supplier::class,
            auditableId: $supplier->id,
            actionType: 'create',
            actor: $request->user(),
            description: "Created supplier {$supplier->supplier_name}",
            ipAddress: (string) $request->ip(),
            deviceId: $request->header('X-Device-Id'),
            before: null,
            after: $supplier->toArray()
        );

        return $this->successResponse(new SupplierResource($supplier), 'Supplier created successfully', 201);
    }

    public function show(Supplier $supplier): JsonResponse
    {
        return $this->successResponse(new SupplierResource($supplier), 'Supplier fetched successfully');
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): JsonResponse
    {
        $before = $supplier->toArray();
        $updated = $this->supplierService->update($supplier, $request->validated());

        $this->auditLogService->logModelAction(
            auditableType: Supplier::class,
            auditableId: $updated->id,
            actionType: 'update',
            actor: $request->user(),
            description: "Updated supplier {$updated->supplier_name}",
            ipAddress: (string) $request->ip(),
            deviceId: $request->header('X-Device-Id'),
            before: $before,
            after: $updated->toArray()
        );

        return $this->successResponse(new SupplierResource($updated), 'Supplier updated successfully');
    }

    public function destroy(Request $request, Supplier $supplier): JsonResponse
    {
        $before = $supplier->toArray();
        $id = $supplier->id;
        $name = $supplier->supplier_name;

        $this->supplierService->delete($supplier);

        $this->auditLogService->logModelAction(
            auditableType: Supplier::class,
            auditableId: $id,
            actionType: 'delete',
            actor: $request->user(),
            description: "Deleted supplier {$name}",
            ipAddress: (string) $request->ip(),
            deviceId: $request->header('X-Device-Id'),
            before: $before,
            after: null
        );

        return $this->successResponse(null, 'Supplier deleted successfully');
    }
}
