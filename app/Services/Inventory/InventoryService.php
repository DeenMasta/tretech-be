<?php

namespace App\Services\Inventory;

use App\Models\Lot;
use App\Models\LotMovement;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Cursor;

class InventoryService
{
    /**
     * Paginate all lots with optional filters.
     *
     * Pass $cursorEncoded to use cursor pagination (no COUNT(*) — faster on large tables).
     *
     * Supported filters:
     *   status              — e.g. available, supplied, used, disposed, holding
     *   supplier_id         — integer
     *   product_id          — integer
     *   instrument_set_id   — integer
     *   expiry_from         — YYYY-MM-DD  (inclusive)
     *   expiry_to           — YYYY-MM-DD  (inclusive)
     *   search              — matches lot_number, manufacturing_date, product ref_num or name
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateLots(array $filters = [], int $perPage = 15, ?string $cursorEncoded = null): LengthAwarePaginator|CursorPaginator
    {
        $query = Lot::query()
            ->with([
                'product:id,ref_num,product_name,product_type,uom',
                'supplier:id,supplier_name',
                'qrLabel:id,lot_id,qr_payload,generated_at',
            ])
            ->withCount('lotMovements')
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(! empty($filters['supplier_id']), fn ($q) => $q->where('supplier_id', (int) $filters['supplier_id']))
            ->when(! empty($filters['product_id']), fn ($q) => $q->where('product_id', (int) $filters['product_id']))
            ->when(! empty($filters['instrument_set_id']), fn ($q) => $q->where('instrument_set_id', (int) $filters['instrument_set_id']))
            ->when(! empty($filters['expiry_from']), fn ($q) => $q->whereDate('expiry_date', '>=', $filters['expiry_from']))
            ->when(! empty($filters['expiry_to']), fn ($q) => $q->whereDate('expiry_date', '<=', $filters['expiry_to']))
            ->when(! empty($filters['search']), function ($q) use ($filters) {
                $term = $filters['search'];
                $q->where(function ($sub) use ($term) {
                    $sub->where('lot_number', 'like', "%{$term}%")
                        ->orWhere('manufacturing_date', 'like', "%{$term}%")
                        ->orWhereHas('product', fn ($pq) => $pq->where('ref_num', 'like', "%{$term}%")
                            ->orWhere('product_name', 'like', "%{$term}%")
                        );
                });
            })
            ->orderByDesc('id');

        return $cursorEncoded !== null
            ? $query->cursorPaginate($perPage, ['*'], 'cursor', Cursor::fromEncoded($cursorEncoded))
            : $query->paginate($perPage);
    }

    /**
     * Find a single lot with full detail relations loaded.
     */
    public function findLot(int $id): ?Lot
    {
        return Lot::query()
            ->with([
                'product:id,ref_num,product_name,product_type,uom',
                'supplier:id,supplier_name',
                'instrumentSet:id,set_name',
                'qrLabel:id,lot_id,qr_payload,generated_at',
                'lotHolding',
            ])
            ->withCount('lotMovements')
            ->find($id);
    }

    public function lookupByLotNumber(string $lotNumber): ?Lot
    {
        return Lot::query()
            ->with([
                'product:id,ref_num,product_name,product_type,uom',
                'supplier:id,supplier_name',
                'qrLabel:id,lot_id,qr_payload,generated_at',
                'lotHolding',
            ])
            ->where('lot_number', $lotNumber)
            ->first();
    }

    /**
     * @return array<int, Lot>
     */
    public function lookupByRefNum(string $refNum): array
    {
        return Lot::query()
            ->with([
                'product:id,ref_num,product_name,product_type,uom',
                'supplier:id,supplier_name',
                'qrLabel:id,lot_id,qr_payload,generated_at',
            ])
            ->whereHas('product', fn ($q) => $q->where('ref_num', $refNum))
            ->orderByDesc('id')
            ->get()
            ->all();
    }

    /** @return array<string, int> */
    public function summary(): array
    {
        $lotTotals = Lot::query()
            ->selectRaw('COUNT(*) as total_lots')
            ->selectRaw('COALESCE(SUM(quantity), 0) as total_quantity')
            // quantity_available is the stock balance source of truth. Status
            // is only used to keep unresolved holding stock out of ready stock.
            ->selectRaw("COALESCE(SUM(CASE WHEN status <> 'holding' THEN quantity_available ELSE 0 END), 0) as available_quantity")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'holding' THEN quantity_available ELSE 0 END), 0) as holding_quantity")
            ->selectRaw('COALESCE(SUM(quantity_consigned), 0) as consigned_quantity')
            ->first();

        $movementTotals = LotMovement::query()
            ->selectRaw("COALESCE(SUM(CASE WHEN movement_type = 'used' THEN quantity ELSE 0 END), 0) as used_quantity")
            ->selectRaw("COALESCE(SUM(CASE WHEN movement_type = 'disposed' THEN quantity ELSE 0 END), 0) as disposed_quantity")
            ->selectRaw("COALESCE(SUM(CASE WHEN movement_type = 'returned_to_supplier' THEN quantity ELSE 0 END), 0) as returned_to_supplier_quantity")
            ->selectRaw("COALESCE(SUM(CASE WHEN movement_type = 'damaged' THEN quantity ELSE 0 END), 0) as damaged_quantity")
            ->selectRaw("COALESCE(SUM(CASE WHEN movement_type = 'missing' THEN quantity ELSE 0 END), 0) as missing_quantity")
            ->first();

        return [
            'total' => (int) $lotTotals->total_quantity,
            'total_lots' => (int) $lotTotals->total_lots,
            'available' => (int) $lotTotals->available_quantity,
            'holding' => (int) $lotTotals->holding_quantity,
            // Keep the existing API key for backwards compatibility; this is
            // the current consigned unit balance, not a lot-status count.
            'supplied' => (int) $lotTotals->consigned_quantity,
            'used' => (int) $movementTotals->used_quantity,
            'disposed' => (int) $movementTotals->disposed_quantity,
            'returned_to_supplier' => (int) $movementTotals->returned_to_supplier_quantity,
            'damaged' => (int) $movementTotals->damaged_quantity,
            'missing' => (int) $movementTotals->missing_quantity,
        ];
    }

    /**
     * Lots expiring within $days days (inclusive today), excluding already-terminal statuses.
     *
     * @param  array<string, mixed>  $filters  supports status, supplier_id, product_id
     */
    public function expiringSoon(int $days = 30, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Lot::query()
            ->with([
                'product:id,ref_num,product_name',
                'supplier:id,supplier_name',
                'qrLabel:id,lot_id,qr_payload,generated_at',
            ])
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', now()->toDateString())
            ->whereDate('expiry_date', '<=', now()->addDays($days)->toDateString())
            ->whereNotIn('status', ['used', 'disposed'])
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(! empty($filters['supplier_id']), fn ($q) => $q->where('supplier_id', (int) $filters['supplier_id']))
            ->when(! empty($filters['product_id']), fn ($q) => $q->where('product_id', (int) $filters['product_id']))
            ->orderBy('expiry_date')
            ->paginate($perPage);
    }

    /**
     * Per-lot movement history (timeline for a single lot).
     *
     * @param  array<string, mixed>  $filters  supports movement_type, from_date, to_date
     */
    public function paginateLotMovements(Lot $lot, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return LotMovement::query()
            ->with(['recordedByUser:id,full_name,email'])
            ->where('lot_id', $lot->id)
            ->when(! empty($filters['movement_type']), fn ($q) => $q->where('movement_type', $filters['movement_type']))
            ->when(! empty($filters['from_date']), fn ($q) => $q->whereDate('performed_at', '>=', $filters['from_date']))
            ->when(! empty($filters['to_date']), fn ($q) => $q->whereDate('performed_at', '<=', $filters['to_date']))
            ->orderByDesc('performed_at')
            ->paginate($perPage);
    }

    /**
     * Global inventory ledger across all lots.
     *
     * @param  array<string, mixed>  $filters  supports lot_id, lot_number, movement_type, from_date, to_date
     */
    public function paginateLedger(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return LotMovement::query()
            ->with([
                'lot:id,lot_number,product_id,status',
                'lot.product:id,ref_num,product_name',
                'recordedByUser:id,full_name,email',
            ])
            ->when(! empty($filters['lot_id']), fn ($q) => $q->where('lot_id', (int) $filters['lot_id']))
            ->when(! empty($filters['lot_number']), function ($q) use ($filters) {
                $q->whereHas('lot', fn ($lq) => $lq->where('lot_number', $filters['lot_number']));
            })
            ->when(! empty($filters['movement_type']), fn ($q) => $q->where('movement_type', $filters['movement_type']))
            ->when(! empty($filters['from_date']), fn ($q) => $q->whereDate('performed_at', '>=', $filters['from_date']))
            ->when(! empty($filters['to_date']), fn ($q) => $q->whereDate('performed_at', '<=', $filters['to_date']))
            ->when(! empty($filters['search']), function ($q) use ($filters) {
                $term = $filters['search'];
                $q->whereHas('lot', function ($lq) use ($term) {
                    $lq->where('lot_number', 'like', "%{$term}%")
                        ->orWhereHas('product', function ($pq) use ($term) {
                            $pq->where('ref_num', 'like', "%{$term}%")
                                ->orWhere('product_name', 'like', "%{$term}%");
                        });
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Find lots for a specific product that were received as part of an InstrumentSet.
     * These lots are tagged with instrument_set_id to indicate their origin.
     */
    public function paginateSetsContainingProduct(int $productId, int $perPage = 15): LengthAwarePaginator
    {
        return Lot::query()
            ->with([
                'instrumentSet:id,set_code,set_name',
            ])
            ->where('product_id', $productId)
            ->whereNotNull('instrument_set_id')
            ->whereIn('status', ['available', 'holding'])
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
