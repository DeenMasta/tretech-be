<?php

namespace App\Http\Controllers\Api\V1\ReturnSession;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ReturnSession\ScanReturnItemRequest;
use App\Http\Requests\Api\V1\ReturnSession\StoreReturnSessionRequest;
use App\Http\Resources\Api\V1\ReturnSession\ReturnSessionItemResource;
use App\Http\Resources\Api\V1\ReturnSession\ReturnSessionResource;
use App\Models\ReturnSession;
use App\Models\ReturnSessionItem;
use App\Services\Return\ReturnScanService;
use App\Services\Return\ReturnSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReturnSessionController extends Controller
{
    public function __construct(
        private readonly ReturnSessionService $returnSessionService,
        private readonly ReturnScanService $returnScanService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->integer('per_page', 15), 100));

        $paginator = $this->returnSessionService->paginate(
            $request->only(['status', 'consignment_id', 'from_date', 'to_date']),
            $perPage
        );

        return $this->paginatedResponse(
            items: ReturnSessionResource::collection($paginator->items())->resolve(),
            total: $paginator->total(),
            perPage: $paginator->perPage(),
            currentPage: $paginator->currentPage(),
            message: 'Return sessions fetched successfully'
        );
    }

    public function store(StoreReturnSessionRequest $request): JsonResponse
    {
        $session = $this->returnSessionService->create($request->validated(), $request->user());

        return $this->successResponse(new ReturnSessionResource($session), 'Return session created successfully', 201);
    }

    public function show(ReturnSession $returnSession): JsonResponse
    {
        $returnSession->load([
            'consignment:id,consignment_no',
            'picUser:id,full_name',
            'completedByUser:id,full_name',
            'returnSessionItems.lot.product:id,ref_num,product_name',
            'returnSessionItems.setInstrumentItems.product:id,ref_num,product_name',
        ])->loadCount('returnSessionItems');

        return $this->successResponse(new ReturnSessionResource($returnSession), 'Return session fetched successfully');
    }

    public function scan(ScanReturnItemRequest $request, ReturnSession $returnSession): JsonResponse
    {
        $item = $this->returnScanService->scan($returnSession, $request->validated(), $request->user());

        return $this->successResponse(new ReturnSessionItemResource($item), 'Item scanned successfully', 201);
    }

    public function removeItem(Request $request, ReturnSession $returnSession, ReturnSessionItem $returnSessionItem): JsonResponse
    {
        $this->returnScanService->removeItem($returnSession, $returnSessionItem, $request->user());

        return $this->successResponse(null, 'Item removed from return session successfully');
    }

    public function complete(Request $request, ReturnSession $returnSession): JsonResponse
    {
        $completed = $this->returnSessionService->complete($returnSession, $request->user());

        return $this->successResponse(new ReturnSessionResource($completed), 'Return session completed successfully');
    }
}
