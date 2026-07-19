<?php

namespace App\Http\Controllers\Api\V1\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MasterData\StoreProductRequest;
use App\Http\Requests\Api\V1\MasterData\UpdateProductRequest;
use App\Http\Resources\Api\V1\MasterData\ProductResource;
use App\Models\Product;
use App\Services\Audit\AuditLogService;
use App\Services\MasterData\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly AuditLogService $auditLogService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->integer('per_page', 15), 100));
        $paginator = $this->productService->paginate($request->only(['search', 'is_active', 'product_type', 'sort']), $perPage);

        return $this->paginatedResponse(
            items: ProductResource::collection($paginator->items())->resolve(),
            total: $paginator->total(),
            perPage: $paginator->perPage(),
            currentPage: $paginator->currentPage(),
            message: 'Products fetched successfully'
        );
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->productService->create($request->validated());

        $this->auditLogService->logModelAction(
            auditableType: Product::class,
            auditableId: $product->id,
            actionType: 'create',
            actor: $request->user(),
            description: "Created product {$product->ref_num}",
            ipAddress: (string) $request->ip(),
            deviceId: $request->header('X-Device-Id'),
            before: null,
            after: $product->toArray()
        );

        return $this->successResponse(new ProductResource($product), 'Product created successfully', 201);
    }

    public function show(Product $product): JsonResponse
    {
        $product->loadSum(['lots as total_quantity_available' => function ($query) {
            $query->where('status', 'available');
        }], 'quantity_available');

        return $this->successResponse(new ProductResource($product), 'Product fetched successfully');
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $before = $product->toArray();
        $updated = $this->productService->update($product, $request->validated());

        $this->auditLogService->logModelAction(
            auditableType: Product::class,
            auditableId: $updated->id,
            actionType: 'update',
            actor: $request->user(),
            description: "Updated product {$updated->ref_num}",
            ipAddress: (string) $request->ip(),
            deviceId: $request->header('X-Device-Id'),
            before: $before,
            after: $updated->toArray()
        );

        return $this->successResponse(new ProductResource($updated), 'Product updated successfully');
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $before = $product->toArray();
        $id = $product->id;
        $refNum = $product->ref_num;

        $this->productService->delete($product);

        $this->auditLogService->logModelAction(
            auditableType: Product::class,
            auditableId: $id,
            actionType: 'delete',
            actor: $request->user(),
            description: "Deleted product {$refNum}",
            ipAddress: (string) $request->ip(),
            deviceId: $request->header('X-Device-Id'),
            before: $before,
            after: null
        );

        return $this->successResponse(null, 'Product deleted successfully');
    }
}
