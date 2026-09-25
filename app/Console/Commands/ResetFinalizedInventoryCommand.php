<?php

namespace App\Console\Commands;

use App\Models\Consignment;
use App\Models\Disposal;
use App\Models\Reconciliation;
use App\Models\StockIn;
use App\Models\SupplierReturn;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetFinalizedInventoryCommand extends Command
{
    protected $signature = 'tretech:reset-finalized-inventory
                            {--force : Permanently perform the reset. Without this option the command only previews it.}';

    protected $description = 'Remove finalized stock-in inventory and its linked consignment transactions, while keeping master data.';

    /**
     * This is deliberately a hard-delete command. It is intended for a fresh
     * inventory restart, not as a normal stock correction tool.
     *
     * @var array<string, list<int>>
     */
    private array $ids = [];

    public function handle(): int
    {
        $summary = $this->buildPlan();

        $this->table(['Record', 'Will be removed'], collect($summary)
            ->map(fn (int $count, string $record) => [$record, $count])
            ->all());

        if (! $this->option('force')) {
            $this->warn('Preview only: no records were changed. Re-run with --force to permanently perform this reset.');

            return self::SUCCESS;
        }

        if ($summary['finalized stock-in sessions'] === 0) {
            $this->info('Nothing to reset. Draft stock-in sessions and all master data were left unchanged.');

            return self::SUCCESS;
        }

        if (! $this->confirm('This permanently deletes the listed inventory transactions. Continue?')) {
            $this->info('Reset cancelled.');

            return self::SUCCESS;
        }

        DB::transaction(function (): void {
            $this->deletePlan();
        });

        $this->info('Finalized inventory reset completed. Customers, suppliers, products, instrument sets, users, drafts, and audit logs were not deleted.');

        return self::SUCCESS;
    }

    /**
     * Work out every dependent record before deleting anything. A lot may be
     * created by a product receipt or by unpacking an instrument set, so both
     * stock-in items and stock-in movement records are considered.
     *
     * @return array<string, int>
     */
    private function buildPlan(): array
    {
        $this->ids['stockIns'] = DB::table('stock_ins')
            ->where('status', 'finalized')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $itemLotIds = DB::table('stock_in_items')
            ->whereIn('stock_in_id', $this->ids['stockIns'])
            ->whereNotNull('lot_id')
            ->pluck('lot_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $movementLotIds = DB::table('lot_movements')
            ->where('reference_type', StockIn::class)
            ->whereIn('reference_id', $this->ids['stockIns'])
            ->pluck('lot_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->ids['lots'] = array_values(array_unique([...$itemLotIds, ...$movementLotIds]));

        $directConsignmentIds = DB::table('consignment_items')
            ->whereIn('lot_id', $this->ids['lots'])
            ->pluck('consignment_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        // Generic instrument-set consignments do not carry a lot_id on their
        // item. Their component movements are the link to the stock-in lots.
        $movementConsignmentIds = DB::table('lot_movements')
            ->where('reference_type', Consignment::class)
            ->whereIn('lot_id', $this->ids['lots'])
            ->pluck('reference_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->ids['consignments'] = array_values(array_unique([
            ...$directConsignmentIds,
            ...$movementConsignmentIds,
        ]));
        $this->ids['returnSessions'] = DB::table('return_sessions')
            ->whereIn('consignment_id', $this->ids['consignments'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $this->ids['reconciliations'] = DB::table('reconciliations')
            ->where(function ($query): void {
                $query->whereIn('consignment_id', $this->ids['consignments'])
                    ->orWhereIn('return_session_id', $this->ids['returnSessions']);
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->ids['returnSessionItems'] = DB::table('return_session_items')
            ->whereIn('return_session_id', $this->ids['returnSessions'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $this->ids['reconciliationItems'] = DB::table('reconciliation_items')
            ->whereIn('reconciliation_id', $this->ids['reconciliations'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $this->ids['qrLabels'] = DB::table('qr_labels')
            ->whereIn('lot_id', $this->ids['lots'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $this->ids['disposalItems'] = DB::table('disposal_items')
            ->whereIn('lot_id', $this->ids['lots'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $this->ids['supplierReturnItems'] = DB::table('supplier_return_items')
            ->whereIn('lot_id', $this->ids['lots'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->ids['disposals'] = $this->emptyTransactionHeaders(
            'disposals',
            'disposal_items',
            'disposal_id',
            $this->ids['lots'],
        );
        $this->ids['supplierReturns'] = $this->emptyTransactionHeaders(
            'supplier_returns',
            'supplier_return_items',
            'supplier_return_id',
            $this->ids['lots'],
        );

        return [
            'finalized stock-in sessions' => count($this->ids['stockIns']),
            'stock-in items' => DB::table('stock_in_items')->whereIn('stock_in_id', $this->ids['stockIns'])->count(),
            'lots / stock records' => count($this->ids['lots']),
            'QR labels and print jobs' => DB::table('qr_labels')->whereIn('id', $this->ids['qrLabels'])->count()
                + DB::table('qr_print_jobs')->where(function ($query): void {
                    $query->whereIn('lot_id', $this->ids['lots'])
                        ->orWhereIn('qr_label_id', $this->ids['qrLabels']);
                })->count(),
            'consignments (entire records)' => count($this->ids['consignments']),
            'consignment items' => DB::table('consignment_items')->whereIn('consignment_id', $this->ids['consignments'])->count(),
            'return sessions and items' => count($this->ids['returnSessions']) + count($this->ids['returnSessionItems']),
            'reconciliations and items' => count($this->ids['reconciliations']) + count($this->ids['reconciliationItems']),
            'disposal items / empty disposals' => count($this->ids['disposalItems']) + count($this->ids['disposals']),
            'supplier-return items / empty returns' => count($this->ids['supplierReturnItems']) + count($this->ids['supplierReturns']),
            'lot holdings and movements' => DB::table('lot_holdings')->whereIn('lot_id', $this->ids['lots'])->count()
                + $this->movementQuery()->count(),
        ];
    }

    /** @return list<int> */
    private function emptyTransactionHeaders(string $headerTable, string $itemTable, string $foreignKey, array $lotIds): array
    {
        $touchedIds = DB::table($itemTable)
            ->whereIn('lot_id', $lotIds)
            ->distinct()
            ->pluck($foreignKey)
            ->all();

        return DB::table($headerTable)
            ->whereIn('id', $touchedIds)
            ->whereNotExists(function ($query) use ($headerTable, $itemTable, $foreignKey, $lotIds): void {
                $query->selectRaw('1')
                    ->from("{$itemTable} as remaining_items")
                    ->whereColumn("remaining_items.{$foreignKey}", "{$headerTable}.id")
                    ->whereNotIn('remaining_items.lot_id', $lotIds);
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function deletePlan(): void
    {
        // Delete children before their parent transaction records.
        DB::table('qr_print_jobs')->where(function ($query): void {
            $query->whereIn('lot_id', $this->ids['lots'])
                ->orWhereIn('qr_label_id', $this->ids['qrLabels']);
        })->delete();
        DB::table('qr_labels')->whereIn('id', $this->ids['qrLabels'])->delete();

        DB::table('reconciliation_set_instrument_results')
            ->whereIn('reconciliation_item_id', $this->ids['reconciliationItems'])
            ->delete();
        DB::table('return_session_set_instrument_items')
            ->whereIn('return_session_item_id', $this->ids['returnSessionItems'])
            ->delete();
        DB::table('reconciliation_items')->whereIn('id', $this->ids['reconciliationItems'])->delete();
        DB::table('reconciliations')->whereIn('id', $this->ids['reconciliations'])->delete();
        DB::table('return_session_items')->whereIn('id', $this->ids['returnSessionItems'])->delete();
        DB::table('return_sessions')->whereIn('id', $this->ids['returnSessions'])->delete();
        DB::table('consignment_items')->whereIn('consignment_id', $this->ids['consignments'])->delete();
        DB::table('consignments')->whereIn('id', $this->ids['consignments'])->delete();

        DB::table('disposal_items')->whereIn('id', $this->ids['disposalItems'])->delete();
        DB::table('supplier_return_items')->whereIn('id', $this->ids['supplierReturnItems'])->delete();
        DB::table('disposals')->whereIn('id', $this->ids['disposals'])->delete();
        DB::table('supplier_returns')->whereIn('id', $this->ids['supplierReturns'])->delete();

        DB::table('lot_holdings')->whereIn('lot_id', $this->ids['lots'])->delete();
        $this->movementQuery()->delete();
        DB::table('stock_in_items')->whereIn('stock_in_id', $this->ids['stockIns'])->delete();
        DB::table('lots')->whereIn('id', $this->ids['lots'])->delete();
        DB::table('stock_ins')->whereIn('id', $this->ids['stockIns'])->delete();
    }

    private function movementQuery()
    {
        return DB::table('lot_movements')->where(function ($query): void {
            $query->whereIn('lot_id', $this->ids['lots'])
                ->orWhere(function ($referenceQuery): void {
                    $referenceQuery->where('reference_type', StockIn::class)
                        ->whereIn('reference_id', $this->ids['stockIns']);
                })
                ->orWhere(function ($referenceQuery): void {
                    $referenceQuery->where('reference_type', Consignment::class)
                        ->whereIn('reference_id', $this->ids['consignments']);
                })
                ->orWhere(function ($referenceQuery): void {
                    $referenceQuery->where('reference_type', Reconciliation::class)
                        ->whereIn('reference_id', $this->ids['reconciliations']);
                })
                ->orWhere(function ($referenceQuery): void {
                    $referenceQuery->where('reference_type', Disposal::class)
                        ->whereIn('reference_id', $this->ids['disposals']);
                })
                ->orWhere(function ($referenceQuery): void {
                    $referenceQuery->where('reference_type', SupplierReturn::class)
                        ->whereIn('reference_id', $this->ids['supplierReturns']);
                });
        });
    }
}
