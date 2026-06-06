<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardSummaryService $dashboardSummaryService)
    {
    }

    public function summary(Request $request): JsonResponse
    {
        $summary = $this->dashboardSummaryService->getSummary(
            $request->only(['date_from', 'date_to'])
        );

        return $this->successResponse($summary, 'Dashboard summary fetched successfully');
    }
}
