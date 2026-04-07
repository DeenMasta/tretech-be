<?php

namespace App\Services\UsageSummary;

use App\Models\UsageSummary;
use App\Models\UsageSummaryPushLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ErpPushService
{
    private const MAX_RETRIES  = 3;
    private const BASE_DELAY_S = 2; // seconds — exponential: 2, 4, 8

    /**
     * POST the usage summary payload to the configured ERP endpoint.
     * Logs every attempt to usage_summary_push_logs.
     *
     * @return UsageSummaryPushLog  The log record for this attempt.
     * @throws \RuntimeException    If the push fails and is not retryable.
     */
    public function push(UsageSummary $summary, ?int $pushedByUserId = null): UsageSummaryPushLog
    {
        $erpUrl        = config('services.erp.push_url');
        $erpApiKey     = config('services.erp.api_key', '');
        $idempotencyKey = Str::uuid()->toString();

        $payload = $this->buildPayload($summary);

        $pushedAt = now();
        $retryCount = 0;

        try {
            $response = Http::withHeaders([
                'Authorization'  => "Bearer {$erpApiKey}",
                'Idempotency-Key' => $idempotencyKey,
                'Content-Type'   => 'application/json',
                'Accept'         => 'application/json',
            ])
                ->timeout(30)
                ->post($erpUrl, $payload);

            $status     = $response->successful() ? 'success' : 'failed';
            $httpStatus = $response->status();
            $responseBody = $this->sanitizeResponse($response->json() ?? []);
            $errorMessage = $response->successful() ? null : "HTTP {$httpStatus}";

            // If 5xx, mark as retryable
            if ($response->serverError()) {
                $status       = 'failed_retryable';
                $errorMessage = "Server error HTTP {$httpStatus}";
            }
        } catch (\Throwable $e) {
            $status        = 'failed_retryable';
            $httpStatus    = 0;
            $responseBody  = [];
            $errorMessage  = $e->getMessage();
        }

        // Determine next retry at (for scheduler to pick up)
        $nextRetryAt = null;
        if ($status === 'failed_retryable') {
            $retryCount  = $summary->usageSummaryPushLogs()
                ->where('status', 'failed_retryable')
                ->count() + 1;
            if ($retryCount < self::MAX_RETRIES) {
                $delaySeconds = self::BASE_DELAY_S ** $retryCount;
                $nextRetryAt  = now()->addSeconds($delaySeconds);
            }
            // If max retries exhausted, keep status as failed (no next retry)
            if ($retryCount >= self::MAX_RETRIES) {
                $status = 'failed';
            }
        }

        return UsageSummaryPushLog::query()->create([
            'usage_summary_id'   => $summary->id,
            'push_url'           => $erpUrl,
            'status'             => $status,
            'http_status_code'   => $httpStatus ?? 0,
            'request_payload'    => $payload,
            'response_body'      => $responseBody ?? [],
            'error_message'      => $errorMessage,
            'pushed_at'          => $pushedAt,
            'next_retry_at'      => $nextRetryAt,
            'retry_count'        => $retryCount,
            'pushed_by_user_id'  => $pushedByUserId,
        ]);
    }

    /**
     * Build the ERP payload from the usage summary.
     * Pricing fields are intentionally excluded as per SRS.
     */
    private function buildPayload(UsageSummary $summary): array
    {
        $summary->load([
            'reconciliation.consignment.client:id,client_name',
            'usageSummaryItems.product:id,ref_num,product_name,uom',
            'usageSummaryItems.lot:id,lot_number,supplier_batch_code,expiry_date',
        ]);

        $consignment = $summary->reconciliation?->consignment;

        return [
            'summary_no'        => $summary->summary_no,
            'generated_at'      => $summary->generated_at?->toIso8601String(),
            'client_name'       => $consignment?->client?->client_name,
            'consignment_no'    => $consignment?->consignment_no,
            'reconciliation_no' => $summary->reconciliation?->reconciliation_no,
            'items'             => $summary->usageSummaryItems->map(fn ($item) => [
                'product_ref'              => $item->product?->ref_num,
                'product_name'             => $item->product?->product_name,
                'uom'                      => $item->product?->uom,
                'lot_number'               => $item->lot?->lot_number,
                'batch_code'               => $item->lot?->supplier_batch_code,
                'expiry_date'              => $item->lot?->expiry_date?->format('Y-m-d'),
                'qty_consigned'            => $item->qty_consigned,
                'qty_returned'             => $item->qty_returned,
                'qty_used'                 => $item->qty_used,
                'qty_disposed'             => $item->qty_disposed,
                'qty_returned_to_supplier' => $item->qty_returned_to_supplier,
            ])->toArray(),
        ];
    }

    private function sanitizeResponse(array $body): array
    {
        // Truncate large responses to prevent DB column overflow
        $json = json_encode($body);
        if (strlen($json) > 65535) {
            return ['_truncated' => true, 'preview' => substr($json, 0, 500)];
        }
        return $body;
    }
}
