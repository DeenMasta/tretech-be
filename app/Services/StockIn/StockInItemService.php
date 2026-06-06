<?php

namespace App\Services\StockIn;

use App\Exceptions\BusinessLogicException;
use App\Models\Lot;
use App\Models\Product;
use App\Models\StockIn;
use App\Models\StockInItem;
use Illuminate\Database\Eloquent\Collection;

class StockInItemService
{
    public function __construct(private readonly StockInSessionService $stockInSessionService)
    {
    }

    /**
     * @return Collection<int, StockInItem>
     */
    public function listBySession(StockIn $stockIn): Collection
    {
        return $stockIn->stockInItems()
            ->with(['product:id,ref_num,product_name', 'lot:id,lot_number,status'])
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function addItem(StockIn $stockIn, array $data): StockInItem
    {
        $this->stockInSessionService->ensureDraft($stockIn);

        $lotNumber = $this->normalizeLotNumber($data['scanned_lot_number'] ?? null);
        $missingLotFlag = (bool) ($data['missing_lot_flag'] ?? false);
        $requiresLotTracking = $this->requiresLotTracking((int) $data['product_id']);

        if ($requiresLotTracking && !$missingLotFlag && $lotNumber === null) {
            throw new BusinessLogicException('Lot number is required unless missing_lot_flag is true.');
        }

        if ($lotNumber !== null) {
            $this->guardLotUniqueness($stockIn, $lotNumber);
        }

        return StockInItem::query()->create([
            'stock_in_id' => $stockIn->id,
            'product_id' => $data['product_id'],
            'scanned_lot_number' => $lotNumber,
            'supplier_batch_code' => $data['supplier_batch_code'],
            'expiry_date' => $data['expiry_date'] ?? null,
            'lot_entry_mode' => $data['lot_entry_mode'] ?? 'scan',
            'expiry_entry_mode' => $data['expiry_entry_mode'] ?? 'scan',
            'missing_lot_flag' => $missingLotFlag,
            'source_barcode' => $data['source_barcode'] ?? null,
            'entry_override_reason' => $data['entry_override_reason'] ?? null,
            'remarks' => $data['remarks'] ?? null,
        ])->load(['product:id,ref_num,product_name', 'lot:id,lot_number,status']);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateItem(StockIn $stockIn, StockInItem $stockInItem, array $data): StockInItem
    {
        $this->stockInSessionService->ensureDraft($stockIn);
        $this->ensureBelongsToSession($stockIn, $stockInItem);

        $nextProductId = array_key_exists('product_id', $data)
            ? (int) $data['product_id']
            : (int) $stockInItem->product_id;
        $requiresLotTracking = $this->requiresLotTracking($nextProductId);

        $nextLotNumber = array_key_exists('scanned_lot_number', $data)
            ? $this->normalizeLotNumber($data['scanned_lot_number'])
            : $this->normalizeLotNumber($stockInItem->scanned_lot_number);
        $nextMissingFlag = array_key_exists('missing_lot_flag', $data)
            ? (bool) $data['missing_lot_flag']
            : (bool) $stockInItem->missing_lot_flag;

        if ($requiresLotTracking && !$nextMissingFlag && $nextLotNumber === null) {
            throw new BusinessLogicException('Lot number is required unless missing_lot_flag is true.');
        }

        if ($nextLotNumber !== null) {
            $this->guardLotUniqueness($stockIn, $nextLotNumber, $stockInItem->id);
        }

        $payload = [...$data];
        if (array_key_exists('scanned_lot_number', $payload)) {
            $payload['scanned_lot_number'] = $nextLotNumber;
        }

        $stockInItem->fill($payload)->save();

        return $stockInItem->refresh()->load(['product:id,ref_num,product_name', 'lot:id,lot_number,status']);
    }

    public function deleteItem(StockIn $stockIn, StockInItem $stockInItem): void
    {
        $this->stockInSessionService->ensureDraft($stockIn);
        $this->ensureBelongsToSession($stockIn, $stockInItem);
        $stockInItem->delete();
    }

    private function ensureBelongsToSession(StockIn $stockIn, StockInItem $stockInItem): void
    {
        if ($stockInItem->stock_in_id !== $stockIn->id) {
            throw new BusinessLogicException('Stock-in item does not belong to the provided session.');
        }
    }

    private function normalizeLotNumber(mixed $value): ?string
    {
        $lot = trim((string) ($value ?? ''));

        return $lot === '' ? null : $lot;
    }

    private function guardLotUniqueness(StockIn $stockIn, string $lotNumber, ?int $ignoreItemId = null): void
    {
        $inSessionExists = StockInItem::query()
            ->where('stock_in_id', $stockIn->id)
            ->where('scanned_lot_number', $lotNumber)
            ->when($ignoreItemId !== null, fn ($query) => $query->where('id', '!=', $ignoreItemId))
            ->exists();

        if ($inSessionExists) {
            throw new BusinessLogicException("Lot number {$lotNumber} already exists in this stock-in session.");
        }

        $inDatabaseExists = Lot::query()->where('lot_number', $lotNumber)->exists();

        if ($inDatabaseExists) {
            throw new BusinessLogicException("Lot number {$lotNumber} already exists in inventory.");
        }
    }

    private function requiresLotTracking(int $productId): bool
    {
        $product = Product::query()->select(['id', 'requires_lot'])->find($productId);

        if (!$product) {
            throw new BusinessLogicException('Selected product is not found.');
        }

        return (bool) $product->requires_lot;
    }
}
