<?php

namespace App\Http\Controllers\Api\V1\QrLabel;

use App\Exceptions\ResourceNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\QrLabel\QrLabelResource;
use App\Models\Lot;
use App\Models\QrLabel;
use App\Services\QrLabel\QrPayloadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QrLabelController extends Controller
{
    public function __construct(private readonly QrPayloadService $qrPayloadService)
    {
    }

    /**
     * GET /qr-labels/{lot}
     *
     * Return the persisted QR label for a lot.
     * If no label exists yet the payload is generated on-the-fly and the
     * label is created (idempotent — safe to call multiple times).
     */
    public function show(Request $request, Lot $lot): JsonResponse
    {
        $label = $this->qrPayloadService->createLabelForLot($lot, $request->user());
        $label->load('lot:id,lot_number,manufacturing_date,expiry_date,status');

        return $this->successResponse(new QrLabelResource($label), 'QR label fetched successfully');
    }

    /**
     * GET /qr-labels/{lot}/preview
     *
     * Return the canonical payload string and TSPL commands for a lot
     * WITHOUT persisting anything. Useful so the Flutter app can render
     * a preview before the user confirms printing.
     */
    public function preview(Lot $lot): JsonResponse
    {
        $lot->loadMissing('product:id,ref_num,product_name');

        $payload = $this->qrPayloadService->generatePayload($lot);
        $tspl    = $this->qrPayloadService->buildTsplPayload($payload, $lot);

        return $this->successResponse([
            'lot_id'       => $lot->id,
            'lot_number'   => $lot->lot_number,
            'qr_payload'   => $payload,
            'tspl_payload' => $tspl,
        ], 'QR label preview generated');
    }
}
