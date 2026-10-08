<?php

return [
    'pdf' => [
        // Full inventory PDFs include product totals and every registered lot.
        // This can require substantially more memory than a regular report.
        'memory_limit' => env('REPORT_PDF_MEMORY_LIMIT', '512M'),
        'time_limit' => (int) env('REPORT_PDF_TIME_LIMIT', 300),
    ],
];
