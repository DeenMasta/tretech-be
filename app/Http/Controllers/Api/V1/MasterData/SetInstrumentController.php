<?php

namespace App\Http\Controllers\Api\V1\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MasterData\StoreSetInstrumentRequest;
use App\Http\Requests\Api\V1\MasterData\UpdateSetInstrumentRequest;
use App\Http\Resources\Api\V1\MasterData\SetInstrumentResource;
use App\Models\InstrumentSet;
use App\Models\SetInstrument;
use App\Services\Audit\AuditLogService;
use App\Services\MasterData\SetInstrumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Manages instruments registered directly under an instrument set
 * (without going through the products catalog).
 */
class SetInstrumentController extends Controller
{
    public function __construct(
        private readonly SetInstrumentService $setInstrumentService,
        private readonly AuditLogService $auditLogService
    ) {
    }

    public function index(InstrumentSet $instrumentSet): JsonResponse
    {
        $items = $this->setInstrumentService->listBySet($instrumentSet);

        return $this->successResponse(
            SetInstrumentResource::collection($items),
            'Set instruments fetched successfully'
        );
    }

    public function store(StoreSetInstrumentRequest $request, InstrumentSet $instrumentSet): JsonResponse
    {
        $instrument = $this->setInstrumentService->create($instrumentSet, $request->validated());

        $this->auditLogService->logModelAction(
            auditableType: SetInstrument::class,
            auditableId: $instrument->id,
            actionType: 'create',
            actor: $request->user(),
            description: "Added instrument '{$instrument->name}' to instrument set {$instrumentSet->set_name}",
            ipAddress: (string) $request->ip(),
            deviceId: $request->header('X-Device-Id'),
            before: null,
            after: $instrument->toArray()
        );

        return $this->successResponse(new SetInstrumentResource($instrument), 'Set instrument created successfully', 201);
    }

    public function update(UpdateSetInstrumentRequest $request, InstrumentSet $instrumentSet, SetInstrument $setInstrument): JsonResponse
    {
        $before = $setInstrument->toArray();
        $updated = $this->setInstrumentService->update($instrumentSet, $setInstrument, $request->validated());

        $this->auditLogService->logModelAction(
            auditableType: SetInstrument::class,
            auditableId: $updated->id,
            actionType: 'update',
            actor: $request->user(),
            description: "Updated instrument {$updated->id} in instrument set {$instrumentSet->set_name}",
            ipAddress: (string) $request->ip(),
            deviceId: $request->header('X-Device-Id'),
            before: $before,
            after: $updated->toArray()
        );

        return $this->successResponse(new SetInstrumentResource($updated), 'Set instrument updated successfully');
    }

    public function destroy(Request $request, InstrumentSet $instrumentSet, SetInstrument $setInstrument): JsonResponse
    {
        $before = $setInstrument->toArray();
        $id = $setInstrument->id;
        $name = $setInstrument->name;

        $this->setInstrumentService->delete($instrumentSet, $setInstrument);

        $this->auditLogService->logModelAction(
            auditableType: SetInstrument::class,
            auditableId: $id,
            actionType: 'delete',
            actor: $request->user(),
            description: "Removed instrument '{$name}' from instrument set {$instrumentSet->set_name}",
            ipAddress: (string) $request->ip(),
            deviceId: $request->header('X-Device-Id'),
            before: $before,
            after: null
        );

        return $this->successResponse(null, 'Set instrument deleted successfully');
    }
}
