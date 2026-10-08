<?php

namespace App\Services\StockAudit;

use App\Enums\StockAuditResult;
use App\Enums\StockAuditStatus;
use App\Exceptions\ApiException;
use App\Models\Lot;
use App\Models\LotMovement;
use App\Models\StockAudit;
use App\Models\StockAuditItem;
use App\Models\User;
use App\Services\QrLabel\QrPayloadService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StockAuditService
{
    public function __construct(private readonly QrPayloadService $qrPayloadService) {}

    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $paginator = StockAudit::query()
            ->with(['startedByUser:id,full_name', 'submittedByUser:id,full_name', 'completedByUser:id,full_name'])
            ->withCount('items')
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $nested) use ($search) {
                    $nested->where('audit_no', 'like', "%{$search}%")
                        ->orWhere('remarks', 'like', "%{$search}%");
                });
            })
            ->when($filters['from_date'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['to_date'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->orderByDesc('id')
            ->paginate($perPage);

        foreach ($paginator->items() as $audit) {
            $this->attachSummary($audit);
        }

        return $paginator;
    }

    public function create(array $data): StockAudit
    {
        return StockAudit::query()->create([
            'audit_no' => 'SA-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'status' => StockAuditStatus::Draft,
            'scope_type' => 'warehouse_all',
            'blind_count' => $data['blind_count'] ?? true,
            'remarks' => $data['remarks'] ?? null,
        ]);
    }

    public function updateDraft(StockAudit $audit, array $data): StockAudit
    {
        $this->assertStatus($audit, [StockAuditStatus::Draft], 'Only a draft audit can be edited.');
        $audit->update(['remarks' => $data['remarks'] ?? null]);

        return $audit->refresh();
    }

    public function start(StockAudit $audit, User $actor): StockAudit
    {
        if ($audit->status === StockAuditStatus::InProgress) {
            return $this->find($audit);
        }

        return DB::transaction(function () use ($audit, $actor) {
            $locked = StockAudit::query()->lockForUpdate()->findOrFail($audit->id);
            $this->assertStatus($locked, [StockAuditStatus::Draft], 'Only a draft audit can be started.');

            $lots = $this->eligibleLotsQuery()->lockForUpdate()->get();
            foreach ($lots as $lot) {
                StockAuditItem::query()->create([
                    'stock_audit_id' => $locked->id,
                    'lot_id' => $lot->id,
                    'snapshot' => $this->snapshotForLot($lot),
                    'expected_quantity' => $lot->quantity_available,
                    'result' => StockAuditResult::Pending,
                ]);
            }

            $locked->update([
                'status' => StockAuditStatus::InProgress,
                'snapshot_movement_id' => LotMovement::query()->max('id'),
                'started_at' => now(),
                'started_by_user_id' => $actor->id,
            ]);

            return $this->find($locked->refresh());
        });
    }

    public function find(StockAudit $audit): StockAudit
    {
        $audit->load([
            'startedByUser:id,full_name', 'submittedByUser:id,full_name',
            'completedByUser:id,full_name', 'cancelledByUser:id,full_name',
        ])->loadCount('items');

        return $this->attachSummary($audit);
    }

    public function paginateItems(StockAudit $audit, array $filters, int $perPage): LengthAwarePaginator
    {
        return StockAuditItem::query()
            ->with(['stockAudit', 'countedByUser:id,full_name'])
            ->where('stock_audit_id', $audit->id)
            ->when($filters['result'] ?? null, fn (Builder $query, string $result) => $query->where('result', $result))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where('snapshot', 'like', "%{$search}%"))
            ->orderByRaw("CASE result WHEN 'movement_conflict' THEN 0 WHEN 'missing' THEN 1 WHEN 'short' THEN 2 WHEN 'over' THEN 3 WHEN 'unexpected' THEN 4 WHEN 'pending' THEN 5 ELSE 6 END")
            ->orderBy('id')
            ->paginate($perPage);
    }

    public function lookup(StockAudit $audit, array $data): StockAuditItem
    {
        $this->assertStatus($audit, [StockAuditStatus::InProgress], 'Stock can only be looked up while counting is in progress.');

        $payload = $data['qr_payload'] ?? null;
        if ($payload !== null) {
            try {
                $parsed = $this->qrPayloadService->validatePayload($payload);
                $refCode = $parsed['ref'];
                $lotNumber = $parsed['lot'];
            } catch (\Throwable $exception) {
                throw new ApiException($exception->getMessage(), 422);
            }
        } else {
            $refCode = trim((string) $data['ref_code']);
            $lotNumber = trim((string) $data['lot_number']);
        }

        $lot = Lot::query()
            ->with(['product:id,ref_num,product_name,product_type,uom', 'instrumentSet:id,set_code,set_name'])
            ->where('lot_number', $lotNumber)
            ->where(function (Builder $query) use ($refCode) {
                $query->whereHas('product', fn (Builder $product) => $product->where('ref_num', $refCode))
                    ->orWhereHas('instrumentSet', fn (Builder $set) => $set->where('set_code', $refCode));
            })
            ->first();

        if ($lot !== null) {
            $existing = StockAuditItem::query()
                ->with(['stockAudit', 'countedByUser:id,full_name'])
                ->where('stock_audit_id', $audit->id)
                ->where('lot_id', $lot->id)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            return StockAuditItem::query()->create([
                'stock_audit_id' => $audit->id,
                'lot_id' => $lot->id,
                'snapshot' => $this->snapshotForLot($lot),
                'expected_quantity' => 0,
                'result' => StockAuditResult::Pending,
                'source_qr_payload' => $payload,
            ])->load('stockAudit');
        }

        $existingUnknown = StockAuditItem::query()
            ->with('stockAudit')
            ->where('stock_audit_id', $audit->id)
            ->whereNull('lot_id')
            ->get()
            ->first(fn (StockAuditItem $item) => ($item->snapshot['ref_code'] ?? null) === $refCode
                && ($item->snapshot['lot_number'] ?? null) === $lotNumber);

        if ($existingUnknown !== null) {
            return $existingUnknown;
        }

        return StockAuditItem::query()->create([
            'stock_audit_id' => $audit->id,
            'snapshot' => [
                'lot_number' => $lotNumber,
                'ref_code' => $refCode,
                'item_name' => 'Unknown stock',
                'item_type' => 'unknown',
                'status' => null,
                'location_type' => null,
                'location_id' => null,
                'lot_updated_at' => null,
            ],
            'expected_quantity' => 0,
            'result' => StockAuditResult::Pending,
            'source_qr_payload' => $payload,
        ])->load('stockAudit');
    }

    public function countItem(StockAudit $audit, StockAuditItem $item, array $data, User $actor): StockAuditItem
    {
        if ($item->stock_audit_id !== $audit->id) {
            throw new ApiException('The stock audit item does not belong to this audit.', 404);
        }

        return DB::transaction(function () use ($audit, $item, $data, $actor) {
            $lockedAudit = StockAudit::query()->lockForUpdate()->findOrFail($audit->id);
            $this->assertStatus($lockedAudit, [StockAuditStatus::InProgress, StockAuditStatus::Review], 'This audit is not open for counting.');
            $lockedItem = StockAuditItem::query()->lockForUpdate()->findOrFail($item->id);

            if ($lockedItem->version !== (int) $data['version']) {
                throw new ApiException('This count was updated by another user. Reload it and try again.', 409);
            }

            $counted = (int) $data['counted_quantity'];
            $values = [
                'counted_quantity' => $counted,
                'count_method' => $data['count_method'],
                'source_qr_payload' => $data['source_qr_payload'] ?? $lockedItem->source_qr_payload,
                'counted_at' => now(),
                'counted_by_user_id' => $actor->id,
                'remarks' => $data['remarks'] ?? null,
                'version' => $lockedItem->version + 1,
            ];

            if ($lockedAudit->status === StockAuditStatus::Review) {
                $values += $this->varianceValues($lockedItem, $counted);
            }

            $lockedItem->update($values);

            return $lockedItem->refresh()->load(['stockAudit', 'countedByUser:id,full_name']);
        });
    }

    public function bulkCountItems(StockAudit $audit, array $rows, User $actor): Collection
    {
        return DB::transaction(function () use ($audit, $rows, $actor) {
            $lockedAudit = StockAudit::query()->lockForUpdate()->findOrFail($audit->id);
            $this->assertStatus($lockedAudit, [StockAuditStatus::InProgress, StockAuditStatus::Review], 'This audit is not open for counting.');

            $rowsById = collect($rows)->keyBy(fn (array $row) => (int) $row['id']);
            $items = StockAuditItem::query()
                ->where('stock_audit_id', $lockedAudit->id)
                ->whereIn('id', $rowsById->keys())
                ->lockForUpdate()
                ->get();

            if ($items->count() !== $rowsById->count()) {
                throw new ApiException('One or more stock audit items do not belong to this audit.', 404);
            }

            foreach ($items as $item) {
                $row = $rowsById[$item->id];
                if ($item->version !== (int) $row['version']) {
                    throw new ApiException("Lot {$item->snapshot['lot_number']} was updated by another user. Reload the audit and try again.", 409);
                }
            }

            foreach ($items as $item) {
                $row = $rowsById[$item->id];
                $counted = (int) $row['counted_quantity'];
                $values = [
                    'counted_quantity' => $counted,
                    'count_method' => $row['count_method'],
                    'source_qr_payload' => $row['source_qr_payload'] ?? $item->source_qr_payload,
                    'counted_at' => now(),
                    'counted_by_user_id' => $actor->id,
                    'remarks' => $row['remarks'] ?? null,
                    'version' => $item->version + 1,
                ];

                if ($lockedAudit->status === StockAuditStatus::Review) {
                    $values += $this->varianceValues($item, $counted);
                }

                $item->update($values);
            }

            return StockAuditItem::query()
                ->with(['stockAudit', 'countedByUser:id,full_name'])
                ->whereIn('id', $items->pluck('id'))
                ->orderBy('id')
                ->get();
        });
    }

    public function submit(StockAudit $audit, User $actor): StockAudit
    {
        $conflictCount = $this->detectAndMarkConflicts($audit);
        if ($conflictCount > 0) {
            throw new ApiException('Inventory changed after this audit started. Refresh and recount the conflicted items.', 409);
        }

        return DB::transaction(function () use ($audit, $actor) {
            $locked = StockAudit::query()->lockForUpdate()->findOrFail($audit->id);
            if ($locked->status === StockAuditStatus::Review) {
                return $this->find($locked);
            }
            $this->assertStatus($locked, [StockAuditStatus::InProgress], 'Only an in-progress audit can be submitted.');

            $pending = $locked->items()->whereNull('counted_quantity')->count();
            if ($pending > 0) {
                throw new ApiException("{$pending} stock lines are still uncounted. Count every line; enter zero when none is found.", 422);
            }

            foreach ($locked->items()->lockForUpdate()->get() as $item) {
                $counted = $item->counted_quantity;
                $item->update([
                    'counted_quantity' => $counted,
                    ...$this->varianceValues($item, $counted),
                ]);
            }

            $locked->update([
                'status' => StockAuditStatus::Review,
                'submitted_at' => now(),
                'submitted_by_user_id' => $actor->id,
            ]);

            return $this->find($locked->refresh());
        });
    }

    public function detectAndMarkConflicts(StockAudit $audit): int
    {
        if (! in_array($audit->status, [StockAuditStatus::InProgress, StockAuditStatus::Review], true)) {
            return 0;
        }

        return DB::transaction(function () use ($audit) {
            $locked = StockAudit::query()->lockForUpdate()->findOrFail($audit->id);
            $items = $locked->items()->with('lot')->lockForUpdate()->get();
            $lotIds = $items->pluck('lot_id')->filter()->values();
            $movedLotIds = LotMovement::query()
                ->where('id', '>', $locked->snapshot_movement_id ?? 0)
                ->whereIn('lot_id', $lotIds)
                ->pluck('lot_id')
                ->all();
            $movedLookup = array_fill_keys($movedLotIds, true);

            foreach ($items as $item) {
                if ($item->lot_id === null || $item->expected_quantity === 0) {
                    continue;
                }

                $lot = $item->lot;
                $snapshot = $item->snapshot;
                $changed = $lot === null
                    || isset($movedLookup[$item->lot_id])
                    || $lot->quantity_available !== $item->expected_quantity
                    || $lot->status !== ($snapshot['status'] ?? null)
                    || $lot->current_location_type !== ($snapshot['location_type'] ?? null)
                    || $lot->current_location_id !== ($snapshot['location_id'] ?? null);

                if ($changed) {
                    $item->update(['result' => StockAuditResult::MovementConflict]);
                }
            }

            $knownLotIds = $lotIds->all();
            $newLots = $this->eligibleLotsQuery()
                ->when($knownLotIds !== [], fn (Builder $query) => $query->whereNotIn('id', $knownLotIds))
                ->get();

            foreach ($newLots as $lot) {
                StockAuditItem::query()->create([
                    'stock_audit_id' => $locked->id,
                    'lot_id' => $lot->id,
                    'snapshot' => $this->snapshotForLot($lot),
                    'expected_quantity' => $lot->quantity_available,
                    'result' => StockAuditResult::MovementConflict,
                ]);
            }

            return $locked->items()->where('result', StockAuditResult::MovementConflict->value)->count();
        });
    }

    public function refreshConflicts(StockAudit $audit): StockAudit
    {
        $this->assertStatus($audit, [StockAuditStatus::InProgress, StockAuditStatus::Review], 'This audit cannot refresh conflicts.');

        return DB::transaction(function () use ($audit) {
            $locked = StockAudit::query()->lockForUpdate()->findOrFail($audit->id);
            $conflicts = $locked->items()->where('result', StockAuditResult::MovementConflict->value)->lockForUpdate()->get();

            foreach ($conflicts as $item) {
                $lot = $item->lot()->with(['product:id,ref_num,product_name,product_type,uom', 'instrumentSet:id,set_code,set_name'])->first();
                $item->update([
                    'snapshot' => $lot ? $this->snapshotForLot($lot) : $item->snapshot,
                    'expected_quantity' => $lot?->quantity_available ?? 0,
                    'counted_quantity' => null,
                    'variance_quantity' => null,
                    'result' => StockAuditResult::Pending,
                    'count_method' => null,
                    'counted_at' => null,
                    'counted_by_user_id' => null,
                    'version' => $item->version + 1,
                ]);
            }

            $locked->update([
                'status' => StockAuditStatus::InProgress,
                'submitted_at' => null,
                'submitted_by_user_id' => null,
                'snapshot_movement_id' => LotMovement::query()->max('id'),
            ]);

            return $this->find($locked->refresh());
        });
    }

    public function complete(StockAudit $audit, User $actor): StockAudit
    {
        $conflictCount = $this->detectAndMarkConflicts($audit);
        if ($conflictCount > 0) {
            throw new ApiException('Inventory changed after this audit started. Refresh and recount the conflicted items.', 409);
        }

        return DB::transaction(function () use ($audit, $actor) {
            $locked = StockAudit::query()->lockForUpdate()->findOrFail($audit->id);
            if ($locked->status === StockAuditStatus::Completed) {
                return $this->find($locked);
            }
            $this->assertStatus($locked, [StockAuditStatus::Review], 'Only an audit in review can be completed.');

            $locked->update([
                'status' => StockAuditStatus::Completed,
                'completed_at' => now(),
                'completed_by_user_id' => $actor->id,
            ]);

            return $this->find($locked->refresh());
        });
    }

    public function cancel(StockAudit $audit, string $reason, User $actor): StockAudit
    {
        $this->assertStatus($audit, [StockAuditStatus::Draft, StockAuditStatus::InProgress, StockAuditStatus::Review], 'This audit cannot be cancelled.');
        $audit->update([
            'status' => StockAuditStatus::Cancelled,
            'cancelled_at' => now(),
            'cancelled_by_user_id' => $actor->id,
            'cancel_reason' => $reason,
        ]);

        return $this->find($audit->refresh());
    }

    public function report(StockAudit $audit): array
    {
        $this->assertStatus($audit, [StockAuditStatus::Review, StockAuditStatus::Completed], 'The report is available after the audit is submitted.');
        $items = $audit->items()->with('countedByUser:id,full_name')->orderBy('result')->orderBy('id')->get();

        return [
            'audit' => $this->find($audit),
            'summary' => $this->summary($audit),
            'items' => $items,
            'rows' => $items->map(fn (StockAuditItem $item) => [
                'Result' => $item->result->value,
                'Reference' => $item->snapshot['ref_code'] ?? '-',
                'Item' => $item->snapshot['item_name'] ?? '-',
                'Lot Number' => $item->snapshot['lot_number'] ?? '-',
                'Expected' => $item->expected_quantity,
                'Counted' => $item->counted_quantity,
                'Variance' => $item->variance_quantity,
                'Counted By' => $item->countedByUser?->full_name,
                'Remarks' => $item->remarks,
            ])->all(),
        ];
    }

    public function summary(StockAudit $audit): array
    {
        $items = $audit->items()->get(['result', 'expected_quantity', 'counted_quantity', 'variance_quantity']);
        $counts = $items->countBy(fn (StockAuditItem $item) => $item->result->value);

        return [
            'total_lines' => $items->count(),
            'counted_lines' => $items->whereNotNull('counted_quantity')->count(),
            'pending' => (int) ($counts[StockAuditResult::Pending->value] ?? 0),
            'tally' => (int) ($counts[StockAuditResult::Tally->value] ?? 0),
            'short' => (int) (($counts[StockAuditResult::Short->value] ?? 0) + ($counts[StockAuditResult::Missing->value] ?? 0)),
            'over' => (int) (($counts[StockAuditResult::Over->value] ?? 0) + ($counts[StockAuditResult::Unexpected->value] ?? 0)),
            'movement_conflict' => (int) ($counts[StockAuditResult::MovementConflict->value] ?? 0),
            'expected_quantity' => (int) $items->sum('expected_quantity'),
            'counted_quantity' => (int) $items->sum(fn (StockAuditItem $item) => $item->counted_quantity ?? 0),
            'missing_quantity' => (int) abs($items->whereIn('result', [StockAuditResult::Missing, StockAuditResult::Short])->sum('variance_quantity')),
            'over_quantity' => (int) $items->whereIn('result', [StockAuditResult::Over, StockAuditResult::Unexpected])->sum('variance_quantity'),
        ];
    }

    private function attachSummary(StockAudit $audit): StockAudit
    {
        $audit->setAttribute('summary', $this->summary($audit));

        return $audit;
    }

    private function eligibleLotsQuery(): Builder
    {
        return Lot::query()
            ->with(['product:id,ref_num,product_name,product_type,uom', 'instrumentSet:id,set_code,set_name'])
            ->where('current_location_type', 'warehouse')
            ->whereIn('status', ['available', 'holding'])
            ->where('quantity_available', '>', 0);
    }

    private function snapshotForLot(Lot $lot): array
    {
        $lot->loadMissing(['product:id,ref_num,product_name,product_type,uom', 'instrumentSet:id,set_code,set_name']);
        $isSet = $lot->instrument_set_id !== null;

        return [
            'lot_number' => $lot->lot_number,
            'ref_code' => $isSet ? $lot->instrumentSet?->set_code : $lot->product?->ref_num,
            'item_name' => $isSet ? $lot->instrumentSet?->set_name : $lot->product?->product_name,
            'item_type' => $isSet ? 'instrument_set' : ($lot->product?->product_type ?? 'product'),
            'uom' => $isSet ? 'set' : $lot->product?->uom,
            'status' => $lot->status,
            'location_type' => $lot->current_location_type,
            'location_id' => $lot->current_location_id,
            'lot_updated_at' => $lot->updated_at?->toIso8601String(),
        ];
    }

    private function varianceValues(StockAuditItem $item, int $counted): array
    {
        $variance = $counted - $item->expected_quantity;
        $result = match (true) {
            $variance === 0 => StockAuditResult::Tally,
            $variance < 0 => StockAuditResult::Short,
            default => StockAuditResult::Over,
        };

        return ['variance_quantity' => $variance, 'result' => $result];
    }

    private function assertStatus(StockAudit $audit, array $allowed, string $message): void
    {
        if (! in_array($audit->status, $allowed, true)) {
            throw new ApiException($message, 409);
        }
    }
}
