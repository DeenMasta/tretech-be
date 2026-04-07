<?php

namespace App\Http\Controllers\Api\V1\Reporting;

use App\Http\Controllers\Controller;
use App\Services\Reporting\ConsignmentReportService;
use App\Services\Reporting\DisposalReportService;
use App\Services\Reporting\ExpiryDashboardService;
use App\Services\Reporting\ExportService;
use App\Services\Reporting\ReturnsAnalysisService;
use App\Services\Reporting\StockInReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly StockInReportService     $stockInReport,
        private readonly ConsignmentReportService $consignmentReport,
        private readonly ReturnsAnalysisService   $returnsAnalysis,
        private readonly DisposalReportService    $disposalReport,
        private readonly ExpiryDashboardService   $expiryDashboard,
        private readonly ExportService            $exportService,
    ) {
    }

    // -----------------------------------------------------------------------
    // GET /api/v1/reports/stock-in
    // -----------------------------------------------------------------------
    public function stockIn(Request $request): JsonResponse
    {
        $filters = $request->only(['from_date', 'to_date', 'supplier_id', 'product_id']);
        $result  = $this->stockInReport->getReport($filters);

        return $this->successResponse($result, 'Stock-in report generated successfully');
    }

    // -----------------------------------------------------------------------
    // GET /api/v1/reports/consignments
    // -----------------------------------------------------------------------
    public function consignments(Request $request): JsonResponse
    {
        $filters = $request->only(['from_date', 'to_date', 'client_id', 'product_id', 'status']);
        $result  = $this->consignmentReport->getReport($filters);

        return $this->successResponse($result, 'Consignment report generated successfully');
    }

    // -----------------------------------------------------------------------
    // GET /api/v1/reports/returns-analysis
    // -----------------------------------------------------------------------
    public function returnsAnalysis(Request $request): JsonResponse
    {
        $filters = $request->only(['from_date', 'to_date', 'client_id']);
        $result  = $this->returnsAnalysis->getReport($filters);

        return $this->successResponse($result, 'Returns analysis report generated successfully');
    }

    // -----------------------------------------------------------------------
    // GET /api/v1/reports/disposals
    // -----------------------------------------------------------------------
    public function disposals(Request $request): JsonResponse
    {
        $filters = $request->only(['from_date', 'to_date', 'supplier_id', 'product_id', 'disposal_category']);
        $result  = $this->disposalReport->getReport($filters);

        return $this->successResponse($result, 'Disposal report generated successfully');
    }

    // -----------------------------------------------------------------------
    // GET /api/v1/reports/expiry
    // -----------------------------------------------------------------------
    public function expiry(Request $request): JsonResponse
    {
        $filters = $request->only(['supplier_id', 'product_id', 'window']);
        $result  = $this->expiryDashboard->getReport($filters);

        return $this->successResponse($result, 'Expiry dashboard generated successfully');
    }

    // -----------------------------------------------------------------------
    // POST /api/v1/reports/{type}/export
    // Params (body or query): format=csv|xlsx|pdf  + any report-specific filters
    // -----------------------------------------------------------------------
    public function export(Request $request, string $type): BinaryFileResponse|StreamedResponse|\Illuminate\Http\Response
    {
        $allowedTypes = ['stock-in', 'consignments', 'returns-analysis', 'disposals', 'expiry'];

        if (!in_array($type, $allowedTypes, true)) {
            abort(404, "Report type '{$type}' not found.");
        }

        $format = strtolower($request->input('format', 'xlsx'));

        if (!in_array($format, ExportService::FORMATS, true)) {
            abort(422, "Unsupported format '{$format}'. Allowed: " . implode(', ', ExportService::FORMATS));
        }

        // Resolve rows from the correct service
        $filters = $request->except(['format']);

        [$rows, $summary] = match ($type) {
            'stock-in'         => [$this->stockInReport->getExportRows($filters),      $this->stockInReport->getReport($filters)['summary']],
            'consignments'     => [$this->consignmentReport->getExportRows($filters),  $this->consignmentReport->getReport($filters)['summary']],
            'returns-analysis' => [$this->returnsAnalysis->getExportRows($filters),    $this->returnsAnalysis->getReport($filters)['summary']],
            'disposals'        => [$this->disposalReport->getExportRows($filters),     $this->disposalReport->getReport($filters)['summary']],
            'expiry'           => [$this->expiryDashboard->getExportRows($filters),    []],
        };

        return $this->exportService->download($type, $format, $rows, $summary);
    }
}
