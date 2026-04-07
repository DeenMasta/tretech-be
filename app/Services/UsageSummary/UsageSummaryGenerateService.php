<?php

namespace App\Services\UsageSummary;

use App\Enums\AuditAction;
use App\Exceptions\BusinessLogicException;
use App\Models\ConsignmentItem;
use App\Models\Lot;
use App\Models\ReconciliationItem;
use App\Models\Reconciliation;
use App\Models\UsageSummary;
use App\Models\UsageSummaryItem;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;

class UsageSummaryGenerateService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {
    }

    /**
     * Generate (or regenerate) a UsageSummary from a finalized Reconciliation.
     *
     * If a summary already exists for this reconciliation it is deleted and re-created,
     * supporting the "manual regenerate" endpoint.
     */
    public function generate(Reconciliation $reconciliation, User $actor): UsageSummary
    {
        return DB::transaction(function () use ($reconciliation, $actor) {
            /** @var Reconciliation $locked */
            $locked = Reconciliation::query()
                ->lockForUpdate()
                ->findOrFail($reconciliation->id);

            if ($locked->status !== 'finalized') {
                throw new BusinessLogicException(
                    "Usage summary can only be generated from a finalized reconciliation (current: {$locked->status})."
                );
            }

            // ------------------------------------------------------------------
            // Drop any existing summary for this reconciliation (idempotent)
            // ------------------------------------------------------------------
            UsageSummary::query()
                ->where('reconciliation_id', $locked->id)
                ->delete();

            // ------------------------------------------------------------------
            // Build item rows from reconciliation items + consignment items
            // ------------------------------------------------------------------
            $reconciliationItems = ReconciliationItem::query()
                ->where('reconciliation_id', $locked->id)
                ->with('lot:id,lot_number,product_id,supplier_batch_code,expiry_date')
                ->get();

            $consignedLotIds = ConsignmentItem::query()
                ->where('consignment_id', $locked->consignment_id)
                ->pluck('lot_id');

            // Count disposals / supplier returns from lot statuses
            $disposedLotIds          = $reconciliationItems
                ->where('result', 'disposed')
                ->pluck('lot_id');
            $returnedToSupplierLotIds = $reconciliationItems
                ->where('result', 'returned_to_supplier')
                ->pluck('lot_id');

            // Group by product_id for aggregation
            $consignedByProduct = $consignedLotIds
                ->map(fn ($id) => Lot::select('product_id')->find($id))
                ->filter()
                ->groupBy('product_id')
                ->map->count();

            $usedByProduct = $reconciliationItems
                ->where('result', 'used')
                ->groupBy(fn ($ri) => $ri->lot?->product_id)
                ->map->count();

            $returnedByProduct = $reconciliationItems
                ->where('result', 'returned')
                ->groupBy(fn ($ri) => $ri->lot?->product_id)
                ->map->count();

            $disposedByProduct = $reconciliationItems
                ->where('result', 'disposed')
                ->groupBy(fn ($ri) => $ri->lot?->product_id)
                ->map->count();

            $supplierReturnedByProduct = $reconciliationItems
                ->where('result', 'returned_to_supplier')
                ->groupBy(fn ($ri) => $ri->lot?->product_id)
                ->map->count();

            // All unique product IDs involved
            $productIds = collect()
                ->merge($consignedByProduct->keys())
                ->merge($usedByProduct->keys())
                ->merge($returnedByProduct->keys())
                ->unique()
                ->filter()
                ->values();

            // ------------------------------------------------------------------
            // Create summary header
            // ------------------------------------------------------------------
            $summaryNo = $this->generateSummaryNo();

            /** @var UsageSummary $summary */
            $summary = UsageSummary::query()->create([
                'reconciliation_id'    => $locked->id,
                'summary_no'           => $summaryNo,
                'generated_at'         => now(),
                'generated_by_user_id' => $actor->id,
                'status'               => 'generated',
            ]);

            // ------------------------------------------------------------------
            // Create one UsageSummaryItem per product
            // ------------------------------------------------------------------
            foreach ($productIds as $productId) {
                // Pick one lot_id that belongs to this product (for reference)
                $representativeLot = $reconciliationItems
                    ->first(fn ($ri) => $ri->lot?->product_id === $productId);

                UsageSummaryItem::query()->create([
                    'usage_summary_id'         => $summary->id,
                    'product_id'               => $productId,
                    'lot_id'                   => $representativeLot?->lot_id,
                    'qty_consigned'            => $consignedByProduct[$productId] ?? 0,
                    'qty_returned'             => $returnedByProduct[$productId] ?? 0,
                    'qty_used'                 => $usedByProduct[$productId] ?? 0,
                    'qty_disposed'             => $disposedByProduct[$productId] ?? 0,
                    'qty_returned_to_supplier' => $supplierReturnedByProduct[$productId] ?? 0,
                ]);
            }

            $this->auditLogService->logModelAction(
                auditableType: UsageSummary::class,
                auditableId:   $summary->id,
                actionType:    AuditAction::USAGE_SUMMARY_GENERATED,
                actor:         $actor,
                description:   "Usage summary {$summaryNo} generated from reconciliation {$locked->reconciliation_no}.",
                after: [
                    'summary_no'        => $summaryNo,
                    'product_count'     => $productIds->count(),
                    'reconciliation_no' => $locked->reconciliation_no,
                ],
            );

            return $summary->refresh()->load([
                'reconciliation:id,reconciliation_no',
                'generatedByUser:id,full_name',
                'usageSummaryItems.product:id,ref_num,product_name,uom',
            ]);
        });
    }

    private function generateSummaryNo(): string
    {
        $date     = now()->format('Ymd');
        $prefix   = "US-{$date}-";
        $lastSeq  = UsageSummary::query()
            ->where('summary_no', 'like', "{$prefix}%")
            ->orderByDesc('summary_no')
            ->value('summary_no');

        $next = $lastSeq
            ? (int) substr($lastSeq, strrpos($lastSeq, '-') + 1) + 1
            : 1;

        return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    }
}
