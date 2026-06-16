<?php

namespace App\Http\Controllers\Api\V1\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MasterData\StoreInstrumentSetRequest;
use App\Http\Requests\Api\V1\MasterData\UpdateInstrumentSetRequest;
use App\Http\Resources\Api\V1\MasterData\InstrumentSetResource;
use App\Models\InstrumentSet;
use App\Services\Audit\AuditLogService;
use App\Services\MasterData\InstrumentSetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InstrumentSetController extends Controller
{
    public function __construct(
        private readonly InstrumentSetService $instrumentSetService,
        private readonly AuditLogService $auditLogService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->integer('per_page', 15), 100));
        $paginator = $this->instrumentSetService->paginate($request->only(['search', 'is_active']), $perPage);

        return $this->paginatedResponse(
            items: InstrumentSetResource::collection($paginator->items())->resolve(),
            total: $paginator->total(),
            perPage: $paginator->perPage(),
            currentPage: $paginator->currentPage(),
            message: 'Instrument sets fetched successfully'
        );
    }

    public function store(StoreInstrumentSetRequest $request): JsonResponse
    {
        $instrumentSet = $this->instrumentSetService->create($request->validated());

        $this->auditLogService->logModelAction(
            auditableType: InstrumentSet::class,
            auditableId: $instrumentSet->id,
            actionType: 'create',
            actor: $request->user(),
            description: "Created instrument set {$instrumentSet->set_name}",
            ipAddress: (string) $request->ip(),
            deviceId: $request->header('X-Device-Id'),
            before: null,
            after: $instrumentSet->toArray()
        );

        return $this->successResponse(new InstrumentSetResource($instrumentSet), 'Instrument set created successfully', 201);
    }

    public function show(InstrumentSet $instrumentSet): JsonResponse
    {
        $instrumentSet->loadCount(['instrumentSetItems', 'setInstruments']);
        $instrumentSet->load([
            'instrumentSetItems' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
            'instrumentSetItems.product:id,ref_num,product_name,is_active',
            'setInstruments' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
        ]);

        return $this->successResponse(new InstrumentSetResource($instrumentSet), 'Instrument set fetched successfully');
    }

    public function update(UpdateInstrumentSetRequest $request, InstrumentSet $instrumentSet): JsonResponse
    {
        $before = $instrumentSet->toArray();
        $updated = $this->instrumentSetService->update($instrumentSet, $request->validated());

        $this->auditLogService->logModelAction(
            auditableType: InstrumentSet::class,
            auditableId: $updated->id,
            actionType: 'update',
            actor: $request->user(),
            description: "Updated instrument set {$updated->set_name}",
            ipAddress: (string) $request->ip(),
            deviceId: $request->header('X-Device-Id'),
            before: $before,
            after: $updated->toArray()
        );

        return $this->successResponse(new InstrumentSetResource($updated), 'Instrument set updated successfully');
    }

    public function destroy(Request $request, InstrumentSet $instrumentSet): JsonResponse
    {
        $before = $instrumentSet->toArray();
        $id = $instrumentSet->id;
        $name = $instrumentSet->set_name;

        $this->instrumentSetService->delete($instrumentSet);

        $this->auditLogService->logModelAction(
            auditableType: InstrumentSet::class,
            auditableId: $id,
            actionType: 'delete',
            actor: $request->user(),
            description: "Deleted instrument set {$name}",
            ipAddress: (string) $request->ip(),
            deviceId: $request->header('X-Device-Id'),
            before: $before,
            after: null
        );

        return $this->successResponse(null, 'Instrument set deleted successfully');
    }
}
