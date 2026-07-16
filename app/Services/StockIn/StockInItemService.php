<?php

namespace App\Services\StockIn;

use App\Exceptions\BusinessLogicException;
use App\Models\InstrumentSet;
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
            ->with([
                'product:id,ref_num,product_name',
                'instrumentSet:id,set_code,set_name',
                'instrumentSet.instrumentSetItems.product:id,product_name,ref_num',
                'lot:id,lot_number,status',
            ])
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function addItem(StockIn $stockIn, array $data): StockInItem
    {
        $this->stockInSessionService->ensureDraft($stockIn);

        $entryKind = ($data['entry_kind'] ?? 'product') === 'set' ? 'set' : 'product';

        if ($entryKind === 'set') {
            return $this->addSetItem($stockIn, $data);
        }

        return $this->addProductItem($stockIn, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function addProductItem(StockIn $stockIn, array $data): StockInItem
    {
        $lotNumber = $this->normalizeLotNumber($data['scanned_lot_number'] ?? null);
        $missingLotFlag = (bool) ($data['missing_lot_flag'] ?? false);
        $requiresLotTracking = $this->requiresLotTracking((int) $data['product_id']);

        if ($requiresLotTracking && !$missingLotFlag && $lotNumber === null) {
            throw new BusinessLogicException('Lot number is required unless missing_lot_flag is true.');
        }

        if ($lotNumber !== null) {
            $this->guardLotUniqueness($stockIn, (int) $data['product_id'], $lotNumber);
        }

        return StockInItem::query()->create([
            'stock_in_id' => $stockIn->id,
            'entry_kind' => 'product',
            'product_id' => $data['product_id'],
            'instrument_set_id' => null,
            'scanned_lot_number' => $lotNumber,
            'manufacturing_date' => $data['manufacturing_date'] ?? null,
            'expiry_date' => $data['expiry_date'] ?? null,
            'lot_entry_mode' => $data['lot_entry_mode'] ?? 'scan',
            'expiry_entry_mode' => $data['expiry_entry_mode'] ?? 'scan',
            'missing_lot_flag' => $missingLotFlag,
            'source_barcode' => $data['source_barcode'] ?? null,
            'entry_override_reason' => $data['entry_override_reason'] ?? null,
            'remarks' => $data['remarks'] ?? null,
            'quantity' => $data['quantity'] ?? 1,
        ])->load([
            'product:id,ref_num,product_name',
            'instrumentSet:id,set_code,set_name',
            'instrumentSet.instrumentSetItems.product:id,product_name,ref_num',
            'lot:id,lot_number,status',
        ]);
    }

    /**
     * Set-instance entry. No lot number is captured here — finalize will mint
     * one from the set code. Supplier batch / expiry are not collected.
     *
     * @param array<string, mixed> $data
     */
    private function addSetItem(StockIn $stockIn, array $data): StockInItem
    {
        $instrumentSetId = (int) ($data['instrument_set_id'] ?? 0);

        $set = InstrumentSet::query()->select(['id', 'is_active'])->find($instrumentSetId);

        if (!$set) {
            throw new BusinessLogicException('Selected instrument set is not found.');
        }

        if (!$set->is_active) {
            throw new BusinessLogicException('Inactive instrument sets cannot be received.');
        }

        return StockInItem::query()->create([
            'stock_in_id' => $stockIn->id,
            'entry_kind' => 'set',
            'product_id' => null,
            'instrument_set_id' => $instrumentSetId,
            'scanned_lot_number' => null,
            'manufacturing_date' => null,
            'expiry_date' => null,
            'lot_entry_mode' => 'scan',
            'expiry_entry_mode' => 'scan',
            'missing_lot_flag' => false,
            'source_barcode' => $data['source_barcode'] ?? null,
            'entry_override_reason' => null,
            'remarks' => $data['remarks'] ?? null,
            'quantity' => $data['quantity'] ?? 1,
        ])->load([
            'product:id,ref_num,product_name',
            'instrumentSet:id,set_code,set_name',
            'instrumentSet.instrumentSetItems.product:id,product_name,ref_num',
            'lot:id,lot_number,status',
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateItem(StockIn $stockIn, StockInItem $stockInItem, array $data): StockInItem
    {
        $this->stockInSessionService->ensureDraft($stockIn);
        $this->ensureBelongsToSession($stockIn, $stockInItem);

        $reload = [
            'product:id,ref_num,product_name',
            'instrumentSet:id,set_code,set_name',
            'instrumentSet.instrumentSetItems.product:id,product_name,ref_num',
            'lot:id,lot_number,status',
        ];

        // Set-entry items are minimal: only remarks/source_barcode are
        // editable while in draft. Product/lot fields are not applicable.
        if ($stockInItem->isSetEntry()) {
            $allowed = array_intersect_key($data, array_flip(['remarks', 'source_barcode']));
            $stockInItem->fill($allowed)->save();

            return $stockInItem->refresh()->load($reload);
        }

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
            $this->guardLotUniqueness($stockIn, $nextProductId, $nextLotNumber, $stockInItem->id);
        }

        $payload = [...$data];
        if (array_key_exists('scanned_lot_number', $payload)) {
            $payload['scanned_lot_number'] = $nextLotNumber;
        }
        if (array_key_exists('quantity', $data)) {
            $payload['quantity'] = $data['quantity'];
        }

        $stockInItem->fill($payload)->save();

        return $stockInItem->refresh()->load($reload);
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

    private function guardLotUniqueness(StockIn $stockIn, int $productId, string $lotNumber, ?int $ignoreItemId = null): void
    {
        $inSessionExists = StockInItem::query()
            ->where('stock_in_id', $stockIn->id)
            ->where('product_id', $productId)
            ->where('scanned_lot_number', $lotNumber)
            ->when($ignoreItemId !== null, fn ($query) => $query->where('id', '!=', $ignoreItemId))
            ->exists();

        if ($inSessionExists) {
            throw new BusinessLogicException("Lot number {$lotNumber} is already scanned for this product in this stock-in session.");
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
