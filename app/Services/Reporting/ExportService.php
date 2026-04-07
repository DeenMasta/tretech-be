<?php

namespace App\Services\Reporting;

use App\Exports\ConsignmentExport;
use App\Exports\DisposalExport;
use App\Exports\ExpiryExport;
use App\Exports\StockInExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportService
{
    /** Supported export formats */
    public const FORMATS = ['csv', 'xlsx', 'pdf'];

    /** Map of report type → [exportClass, viewName, titleForPdf] */
    private array $exportMap = [
        'stock-in'        => [StockInExport::class,      'reports.stock-in',        'Stock-In Report'],
        'consignments'    => [ConsignmentExport::class,  'reports.consignments',    'Consignment Report'],
        'returns-analysis'=> [ConsignmentExport::class,  'reports.returns-analysis','Returns Analysis Report'],
        'disposals'       => [DisposalExport::class,     'reports.disposals',       'Disposal & Loss Report'],
        'expiry'          => [ExpiryExport::class,       'reports.expiry',          'Expiry Dashboard Report'],
    ];

    /**
     * Generate and stream a download response.
     *
     * @param  string  $type    One of: stock-in, consignments, returns-analysis, disposals, expiry
     * @param  string  $format  One of: csv, xlsx, pdf
     * @param  array   $rows    Flat associative array rows (header keys = column headers)
     * @param  array   $summary Optional summary data for PDF header
     * @return BinaryFileResponse|StreamedResponse
     */
    public function download(string $type, string $format, array $rows, array $summary = []): mixed
    {
        $map          = $this->exportMap[$type] ?? null;
        $exportClass  = $map[0] ?? StockInExport::class;
        $viewName     = $map[1] ?? 'reports.generic';
        $title        = $map[2] ?? ucfirst($type) . ' Report';
        $fileName     = $type . '_report_' . now()->format('Ymd_His');

        if ($format === 'pdf') {
            return $this->downloadPdf($viewName, $title, $rows, $summary, $fileName);
        }

        /** @var \App\Exports\BaseExport $export */
        $export = new $exportClass($rows);

        $writerType = $format === 'xlsx'
            ? \Maatwebsite\Excel\Excel::XLSX
            : \Maatwebsite\Excel\Excel::CSV;

        return Excel::download($export, "{$fileName}.{$format}", $writerType);
    }

    private function downloadPdf(
        string $viewName,
        string $title,
        array  $rows,
        array  $summary,
        string $fileName
    ): \Illuminate\Http\Response {
        $headers  = !empty($rows) ? array_keys($rows[0]) : [];

        $pdf = Pdf::loadView('exports.generic-table', [
            'title'   => $title,
            'headers' => $headers,
            'rows'    => $rows,
            'summary' => $summary,
        ])->setPaper('a4', 'landscape');

        return $pdf->download("{$fileName}.pdf");
    }
}
