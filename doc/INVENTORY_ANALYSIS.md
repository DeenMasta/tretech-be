# TRETECH — Comprehensive Inventory & Stock Movement Analysis

> Analyzed: 2026-07-22 | Author: Antigravity AI  
> Scope: All backend services, models, migrations, reporting

---

## Table of Contents
1. [System Flow Map](#1-system-flow-map)
2. [Critical Logic Bugs](#2-critical-logic-bugs-)
3. [Database Integrity Issues](#3-database-integrity-issues-)
4. [Design & Logic Concerns](#4-design--logic-concerns-)
5. [Reporting Issues](#5-reporting-issues-)
6. [Priority Fix Order](#6-priority-fix-order)

---

## 1. System Flow Map

```
┌─────────────────────────────────────────────────────────────────┐
│                        STOCK ENTRY                              │
│                                                                 │
│  StockIn (draft) ─► finalize() ─► Lots created                 │
│    ├─ Product item  → Lot(product_id, status=available)         │
│    │   └─ if missing lot → Lot(status=holding) + LotHolding     │
│    └─ Set item → UNPACKED → N product Lots (auto-lot-number)    │
│         └─ instrument_set_id tagged on each component lot       │
└──────────────────────────┬──────────────────────────────────────┘
                           │ qty_available ↑
                           ▼
┌─────────────────────────────────────────────────────────────────┐
│                       HOLDING AREA                              │
│                                                                 │
│  HoldingAreaService::assignLot()                               │
│    → lot_number updated, status: holding → available            │
│    → LotHolding.released_at set                                │
│    → LotMovement: holding_released                             │
└──────────────────────────┬──────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────┐
│                      CONSIGNMENT                                │
│                                                                 │
│  Consignment (draft) ─► confirm()                              │
│    ├─ Lot item:                                                 │
│    │   qty_available ↓, qty_consigned ↑                        │
│    │   if fully depleted → status: depleted, location: client  │
│    └─ Set item (FIFO deduct at confirm):                        │
│        foreach component product → oldest lots deducted first   │
│    → LotMovement: consigned                                    │
└──────────────────────────┬──────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────┐
│                     RETURN SESSION                              │
│                                                                 │
│  ReturnSession (in_progress) ─► scan items ─► complete()       │
│    └─► auto-creates + finalizes Reconciliation                 │
│                                                                 │
│  ReturnSessionItem fields:                                      │
│    quantity | used_quantity | damaged_quantity | missing_quantity│
└──────────────────────────┬──────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────┐
│                     RECONCILIATION                              │
│                                                                 │
│  finalize():                                                    │
│    returned qty  → qty_available ↑, qty_consigned ↓            │
│                 → status: available, location: warehouse        │
│    used qty      → qty_consigned ↓ → status: used              │
│    damaged qty   → qty_consigned ↓, qty_available ↑ ← BUG-01  │
│    missing qty   → qty_consigned ↓ → status: missing           │
│    → ReconciliationItem created per consigned item              │
└──────────────────────────┬──────────────────────────────────────┘
                           │ (parallel paths)
              ┌────────────┴────────────┐
              ▼                         ▼
┌─────────────────────┐    ┌────────────────────────────┐
│     DISPOSAL        │    │     SUPPLIER RETURN        │
│                     │    │                            │
│ complete():         │    │ complete():                │
│ qty_available ↓     │    │ qty_available ↓            │
│ if depleted →       │    │ if depleted →              │
│   status: disposed  │    │  status: returned_to_supp. │
│ LotMovement:        │    │ LotMovement:               │
│   disposed          │    │   returned_to_supplier     │
└─────────────────────┘    └────────────────────────────┘
```

### Valid Lot Status States
| Status | Set By | Meaning |
|---|---|---|
| `available` | StockInFinalizeService, ReconciliationFinalizeService, HoldingAreaService | In warehouse, ready to consign |
| `holding` | StockInFinalizeService | Missing lot number, quarantined |
| `depleted` | ConsignmentConfirmService | Fully consigned, no stock left in warehouse |
| `used` | ReconciliationFinalizeService | Consumed at client site |
| `disposed` | DisposalCompleteService | Written off |
| `returned_to_supplier` | SupplierReturnCompleteService | Sent back to supplier |
| `damaged` | ReconciliationFinalizeService | Returned damaged |
| `missing` | ReconciliationFinalizeService | Lost/unaccounted |
| ~~`supplied`~~ | **NOWHERE** | ⚠️ Listed in `InventoryService::summary()` but **never actually assigned** |

---

## 2. Critical Logic Bugs 🔴

### BUG-01 — Damaged quantity INFLATES `quantity_available` (CRITICAL) — ✅ FIXED

**File:** [`ReconciliationFinalizeService.php:230–250`](file:///d:/Projects/TRETECH/tretech-backend/app/Services/Reconciliation/ReconciliationFinalizeService.php#L230-L250)

```php
if ($damagedQty > 0) {
    $lot->quantity_consigned -= $damagedQty;
    $lot->quantity_available += $damagedQty; // ← BUG: damaged stock returned as available!
    ...
    'to_status' => 'damaged',
```

**Problem:** When items are returned damaged, the code **subtracts from `quantity_consigned` AND adds to `quantity_available`**. This means damaged goods silently re-enter usable inventory, even though the movement log says `to_status = 'damaged'`. The lot's `status` field gets set to `'damaged'` only in a specific elseif branch — but if there's *any* returned quantity alongside the damage, the `if ($returnedQty > 0)` block runs first and sets status to `'available'`, overriding the damage.

**Impact:** Damaged stock reappears as usable inventory. **Physical stock counts will not match system counts.** Customers may receive damaged products that should have been written off.

**Fix:**
```php
if ($damagedQty > 0) {
    $lot->quantity_consigned -= min($damagedQty, max(0, (int) $lot->quantity_consigned));
    // Do NOT increase quantity_available for damaged items
    // Track separately if needed (quantity_damaged column)
    LotMovement::query()->create([...]);
}
```

**Resolved:** `ReconciliationFinalizeService` now bounds the damaged write-off to
the remaining consigned balance and does not add damaged units to
`quantity_available`. The damaged lot movement records the actual written-off
quantity. Regression coverage: `ReconciliationFinalizeServiceTest::finalize_damaged_stock_does_not_inflate_available_quantity`.

---

### BUG-02 — Status overwrite conflicts for mixed-condition lots (HIGH) — ✅ FIXED

**File:** [`ReconciliationFinalizeService.php:274–288`](file:///d:/Projects/TRETECH/tretech-backend/app/Services/Reconciliation/ReconciliationFinalizeService.php#L274-L288)

```php
if ($returnedQty > 0) {
    $lot->fill(['status' => 'available', ...]); // ALWAYS runs when returned > 0
}
if ($usedQty > 0 && $returnedQty === 0 ...) { // NEVER runs if returnedQty > 0
    $lot->fill(['status' => 'used']);
} elseif ($damagedQty > 0 && $returnedQty === 0 ...) { // NEVER runs if returnedQty > 0
    $lot->fill(['status' => 'damaged']);
} elseif ($missingQty > 0 && $returnedQty === 0 ...) { // NEVER runs if returnedQty > 0
    $lot->fill(['status' => 'missing']);
}
```

**Previous problem:** The status was assigned in separate conditional blocks, so
the outcome depended on which block happened to run. A batch lot can contain a
mix of returned and non-returned quantities, but it has one status field.

**Fix:** Resolve the status once from the final usable balance, with a
deterministic terminal-status priority when no usable units remain:
```php
// A positive available balance means the batch remains usable.
$finalStatus = match(true) {
    $lot->quantity_available > 0 => 'available',
    $damagedQty > 0 => 'damaged',
    $missingQty > 0 => 'missing',
    $usedQty > 0 => 'used',
    default => $lot->status,
};
```

**Resolved:** Final status is now calculated once after all quantities have been
applied. A lot with remaining `quantity_available` is `available` (including a
mixed return/damage outcome); otherwise it receives a deterministic terminal
status: `damaged`, `missing`, or `used`. The complete mixed outcome remains
auditable through `ReconciliationItem` quantities and `LotMovement` records.

---

### BUG-03 — `quantity_consigned` can go negative (no bounds check) (HIGH) — ✅ FIXED

**File:** [`ReconciliationFinalizeService.php:209–272`](file:///d:/Projects/TRETECH/tretech-backend/app/Services/Reconciliation/ReconciliationFinalizeService.php#L209-L272)

The code has a `min()` guard for returned quantities (line 180):
```php
$quantityToRelease = min($returnedQty, max(0, (int) $lot->quantity_consigned)); // ✅ guarded
```

Previously, `used`, `damaged`, and `missing` deductions were not consistently
bounded against the remaining balance:
```php
$lot->quantity_consigned -= $usedQty;    // ❌ no bounds check
$lot->quantity_consigned -= $damagedQty; // ❌ no bounds check
$lot->quantity_consigned -= $missingQty; // ❌ no bounds check
```

All three run sequentially. If `usedQty + damagedQty + missingQty > quantity_consigned` (possible after reopen/re-finalize cycles), the unsigned integer column will either throw a DB error or clamp to 0 depending on MySQL strict mode settings.

**Fix:** Apply min bounds to each deduction:
```php
$safeDeductUsed    = min($usedQty,    max(0, (int) $lot->quantity_consigned));
$lot->quantity_consigned -= $safeDeductUsed;

$safeDeductDamaged = min($damagedQty, max(0, (int) $lot->quantity_consigned));
$lot->quantity_consigned -= $safeDeductDamaged;

$safeDeductMissing = min($missingQty, max(0, (int) $lot->quantity_consigned));
$lot->quantity_consigned -= $safeDeductMissing;
```

**Resolved:** Returned, used, damaged, and missing quantities are each bounded
against the current remaining `quantity_consigned` balance. Movement records are
created only for positive, actually-applied quantities. Regression coverage:
`ReconciliationFinalizeServiceTest::finalize_never_deducts_more_than_the_consigned_balance`.

---

### BUG-04 — Duplicate `$returnSessionItemsByKey` collection initialization - ✅ FIXED

**File:** [`ReconciliationFinalizeService.php:77–112`](file:///d:/Projects/TRETECH/tretech-backend/app/Services/Reconciliation/ReconciliationFinalizeService.php#L77-L112)

```php
// === BLOCK A (lines 77–93) — builds returnedKeys + returnSessionItemsByKey ===
$returnedKeys = [];
$returnSessionItemsByKey = collect();
foreach ($returnSessionItems as $rsi) { ... } // ← populates both

// === BLOCK B (lines 98–112) — COMPLETELY OVERWRITES BLOCK A ===
$returnSessionItemsByKey = collect();  // ← reset to empty!
$returnSessionItems = ReturnSessionItem::query()->...->get(); // ← second DB query!
foreach ($returnSessionItems as $rsi) { ... }
```

**Problem:** Block A is dead code. It builds `$returnedKeys` (used for the audit log at line 365 and 371) but `$returnSessionItemsByKey` from Block A is immediately overwritten. The `$returnedKeys` variable is populated in Block A but is only used in the audit log later — however the actual reconciliation logic uses `$returnSessionItemsByKey` from Block B. So `$returnedKeys` reflects Block A's data while `$returnSessionItemsByKey` is from Block B.

This causes:
1. A **wasted DB query** on every reconciliation finalization
2. Code that's confusing and error-prone to maintain

**Resolved:** The return-session items are now queried once, then used to build both
`$returnedKeys` for audit logging and `$returnSessionItemsByKey` for reconciliation.
Regression coverage:
`ReconciliationFinalizeServiceTest::finalize_loads_return_session_items_once`.

---

### BUG-05 — Lot location not updated for partial consignments (**✅ FIXED**)

**File:** [`ConsignmentConfirmService.php:80–108`](file:///d:/Projects/TRETECH/tretech-backend/app/Services/Consignment/ConsignmentConfirmService.php#L80-L108)

```php
$lot->quantity_available -= $item->quantity;
$lot->quantity_consigned += $item->quantity;

if ($lot->isFullyDepleted()) {  // Only updates location if FULLY consumed
    $lot->status = 'depleted';
    $lot->current_location_type = 'client';
    $lot->current_location_id   = $locked->client_id;
}
// If partially consumed: location stays 'warehouse', status stays 'available'
```

**Problem:** A lot with qty=10 where only 3 are consigned: location remains `warehouse`, status remains `available`. But 3 units are physically at the client. The system shows the lot as entirely in the warehouse. Reports that show lot location will be wrong.

**Resolved:** Every consignment now updates the lot's location to the client while
`quantity_available` and `quantity_consigned` retain the warehouse/client split.
The consignment movement also preserves the pre-consignment location. Regression
coverage: `ConsignmentConfirmServiceTest::confirm_partial_consignment_records_the_client_location`.

---

### BUG-06 — Disposal & SupplierReturn allow `quantity_available` to go negative (MEDIUM) — ✅ FIXED

**Files:**
- [`DisposalCompleteService.php:64–70`](file:///d:/Projects/TRETECH/tretech-backend/app/Services/Disposal/DisposalCompleteService.php#L64-L70)
- [`SupplierReturnCompleteService.php:66–72`](file:///d:/Projects/TRETECH/tretech-backend/app/Services/SupplierReturn/SupplierReturnCompleteService.php#L66-L72)

```php
// DisposalCompleteService
$lot->quantity_available -= $item->quantity; // No check that item->quantity <= qty_available
if ($lot->isFullyDepleted()) {
    $lot->status = 'disposed';
}
```

**Problem:** If `$item->quantity > $lot->quantity_available` (possible via race condition or data entry error), `quantity_available` underflows. The lot status check `isFullyDepleted()` checks if `quantity_available === 0`, so a negative value would **not trigger** the status change either — leaving the lot in a zombie state.

**Resolved:** Both completion services now validate the requested quantity against
the lot's locked `quantity_available` before making any deduction. An oversized
disposal or supplier return throws `BusinessLogicException`, so the transaction
rolls back with the lot balance and parent record unchanged. Regression coverage:
`DisposalCompleteServiceTest::complete_rejects_a_quantity_greater_than_the_locked_lot_balance`
and `SupplierReturnCompleteServiceTest::complete_rejects_a_quantity_greater_than_the_locked_lot_balance`.

The guard applied before deduction is:
```php
if ($item->quantity > $lot->quantity_available) {
    throw new BusinessLogicException(
        "Cannot dispose {$item->quantity} unit(s) — only {$lot->quantity_available} available for lot [{$lot->lot_number}]."
    );
}
```

---

### BUG-07 — Reconciliation reopen uses non-existent `supplied` status + incomplete quantity revert (HIGH) — ✅ FIXED

**File:** [`ReconciliationReopenService.php:51–55`](file:///d:/Projects/TRETECH/tretech-backend/app/Services/Reconciliation/ReconciliationReopenService.php#L51-L55)

```php
foreach ($usedItems as $item) {
    if ($item->lot) {
        $item->lot->fill(['status' => 'supplied'])->save(); // ← status 'supplied' doesn't exist!
    }
}
```

**Three separate problems:**

1. **`supplied` is a phantom status.** No service ever sets a lot to `'supplied'`. The valid status after reconciliation reopen for a formerly-used lot should be `'depleted'` (still consigned, not yet returned) or the original pre-finalization status.

2. **No quantity revert.** The reopen only changes the lot `status` — it does **not** restore `quantity_consigned` or `quantity_available`. So after reopen, a lot that was marked `used` (with `quantity_consigned` decremented) now has `status = 'supplied'` (wrong) but still has the decremented `quantity_consigned`. Re-finalizing then subtracts `quantity_consigned` again, leading to double-deduction.

3. **Only `result = 'used'` is reverted.** Lots with `result = 'partial'`, `'returned'`, `'damaged'`, or `'missing'` are left in whatever state finalization put them, while the reconciliation items are deleted. This means those lots are in a permanently inconsistent state after reopen.

**Resolved:** `ReconciliationReopenService` now replays every lot movement for the
reconciliation in reverse order. It restores the appropriate available and
consigned quantities, then restores the recorded `from_status` and source
location. The reversed movements and reconciliation items are deleted, leaving
a clean state for re-finalization and preventing duplicate deductions. Regression
coverage: `ReconciliationReopenServiceTest::reopen_reverses_all_finalization_movements_and_removes_them`.

---

### BUG-08 — Return session reopen ignores `damaged` and `missing` movements (MEDIUM) — ✅ FIXED

**File:** [`ReturnSessionService.php:192–218`](file:///d:/Projects/TRETECH/tretech-backend/app/Services/Return/ReturnSessionService.php#L192-L218)

```php
if ($movement->movement_type === 'returned') {
    $lot->quantity_available -= $movement->quantity;
    $lot->quantity_consigned += $movement->quantity;
    ...
} elseif ($movement->movement_type === 'used') {
    $lot->quantity_consigned += $movement->quantity;
}
// ← No handling for 'damaged' or 'missing' movements!
```

**Problem:** When a reconciliation included damaged/missing movements, reopening the return session does not restore the `quantity_consigned` for those movement types. After reopen, `quantity_consigned` is lower than it should be — the consigned quantities are not fully restored, so a re-finalization will calculate wrong numbers.

**Resolved:** Return-session reopen now restores `quantity_consigned` for
`used`, `damaged`, and `missing` reconciliation movements. Damaged quantities
are not deducted from `quantity_available` because they are now written off,
rather than added to usable stock, during finalization. Regression coverage:
`ReturnSessionServiceTest::reopen_restores_consigned_quantity_for_damaged_and_missing_movements`.

The reversal now handles the affected movement types together:
```php
} elseif (in_array($movement->movement_type, ['used', 'damaged', 'missing'], true)) {
    $lot->quantity_consigned += $movement->quantity;
}
```

---

## 3. Database Integrity Issues 🟠

### DB-01 — No DB-level constraint enforcing lot `product_id`/`instrument_set_id` mutual exclusion

**Migration:** [`2026_06_15_000002_relax_lots_product_id_nullable.php`](file:///d:/Projects/TRETECH/tretech-backend/database/migrations/2026_06_15_000002_relax_lots_product_id_nullable.php#L13-L16)

The comment says: *"enforced at the application layer"*. But if a bug creates a row with both or neither FK populated, the DB accepts it silently. The `isSetInstance()` and `isProductInstance()` helpers would return `false` for both, causing silent failures in reconciliation and consignment logic.

**Fix:**
```sql
ALTER TABLE lots ADD CONSTRAINT chk_lots_product_or_set
CHECK (
  (product_id IS NOT NULL AND instrument_set_id IS NULL) OR
  (product_id IS NULL AND instrument_set_id IS NOT NULL)
);
```

---

### DB-02 — No unique constraint on `lot_holdings.lot_id` (open holding records)

**Migration:** [`2026_04_03_000025_create_lot_holdings_table.php`](file:///d:/Projects/TRETECH/tretech-backend/database/migrations/2026_04_03_000025_create_lot_holdings_table.php)

The `lot_holdings` table has no unique constraint on `lot_id`. A lot could accumulate multiple open holding records. The `Lot::lotHolding()` is `hasOne` — Eloquent returns the first record found, silently ignoring extras. The `HoldingAreaService::assignLot()` closes holdings with `->whereNull('released_at')->update(...)` (a bulk update), so multiple open records would all be closed together — but this is coincidental correctness, not by design.

**Fix:** Add unique constraint for open holdings:
```sql
ALTER TABLE lot_holdings ADD UNIQUE INDEX uq_lot_holdings_lot_open (lot_id, released_at);
-- or enforce at app layer: only one record with released_at IS NULL per lot_id
```

---

### DB-03 — `lot_movements` missing `quantity` column in the original migration

**Migration:** [`2026_04_03_000026_create_lot_movements_table.php`](file:///d:/Projects/TRETECH/tretech-backend/database/migrations/2026_04_03_000026_create_lot_movements_table.php)

The original migration creates `lot_movements` without a `quantity` column, yet every service passes `'quantity'` when creating movements. The column was added in `2026_06_20_115510_add_quantity_to_transaction_items.php`. Running only the initial migrations would create a broken table that silently drops the quantity field on inserts.

---

### DB-04 — Unique constraint on `lots` allows same lot_number for same product in different sets

**Migration:** [`2026_06_20_115510_modify_lots_for_batch_quantities.php:28`](file:///d:/Projects/TRETECH/tretech-backend/database/migrations/2026_06_20_115510_modify_lots_for_batch_quantities.php#L28)

```php
$table->unique(['product_id', 'lot_number'], 'uq_lots_product_id_lot_number');
```

The uniqueness is `(product_id, lot_number)`. But the `HoldingAreaService::assignLot()` uniqueness check is:
```php
Lot::query()->where('lot_number', $newLotNumber)->where('id', '!=', $locked->id)->exists()
```

This check is **global** (any lot_number match), which is stricter than the DB constraint. But the `generateAutoComponentLotNumber()` in `StockInFinalizeService` checks:
```php
Lot::query()->where('lot_number', $base)->where('product_id', '!=', $setItem->product_id)->exists()
```

This means: the generated lot number is considered free if no *other* product has that name. **The same lot_number CAN appear for the same product across different instrument sets**, which is allowed by the DB constraint. This could create confusion in reports and look-up queries.

---

### DB-05 — `lot_movements` has no composite index on `(lot_id, movement_type)`

**Migration:** [`2026_04_03_000026_create_lot_movements_table.php`](file:///d:/Projects/TRETECH/tretech-backend/database/migrations/2026_04_03_000026_create_lot_movements_table.php)

The `lot_movements` table has separate indexes on `lot_id` and `movement_type` but no **composite index**. The `restoreReturnedSetComponents()` query (BUG-07 related) filters on both `reference_type`, `reference_id`, `movement_type`, and then a `whereHas('lot')` — this will do a full scan of all movements for that consignment without an optimal index path.

---

## 4. Design & Logic Concerns 🟡

### DES-01 — `InventoryService::summary()` includes phantom `supplied` status

**File:** [`InventoryService.php:122`](file:///d:/Projects/TRETECH/tretech-backend/app/Services/Inventory/InventoryService.php#L122)

```php
$statuses = ['available', 'supplied', 'used', 'disposed', 'holding'];
```

`'supplied'` is listed here but is **never set by any service** (except the broken reopen, BUG-07). It should be either:
- Removed from the summary (if it's a legacy status)
- Properly implemented as a status transition (lots move from `available` → `supplied` when consigned but not yet returned)

The `ExpiryDashboardService` also incorrectly includes `'supplied'` in its expiry filter (line 39 and 55).

---

### DES-02 — `restoreReturnedSetComponents()` uses fragile `LIKE` on remarks string

**File:** [`ReconciliationFinalizeService.php:441–450`](file:///d:/Projects/TRETECH/tretech-backend/app/Services/Reconciliation/ReconciliationFinalizeService.php#L441-L450)

```php
->where('remarks', 'like', 'Set component consigned via%')
```

Movement restoration is based on **pattern-matching the human-readable remarks field**. If this string is ever changed (typo fix, localization, refactor), the `restoreReturnedSetComponents()` method silently finds 0 movements and throws:
```
BusinessLogicException: "Unable to restore all returned components..."
```

This is extremely brittle. The set component restoration would break silently for any movements created before the string was changed.

**Fix:** Add a `movement_subtype` or `metadata` (JSON) column to `lot_movements`:
```sql
ALTER TABLE lot_movements ADD COLUMN movement_subtype VARCHAR(100) NULL;
```
Then use `->where('movement_subtype', 'set_component_consignment')` instead of the remarks LIKE.

---

### DES-03 — Silent fallback assumes "remainder = used" without explicit confirmation

**File:** [`ReconciliationFinalizeService.php:144–147`](file:///d:/Projects/TRETECH/tretech-backend/app/Services/Reconciliation/ReconciliationFinalizeService.php#L144-L147)

```php
// Fallback: if only returned_qty was provided and it's less than consigned
if ($usedQty === 0 && $damagedQty === 0 && $missingQty === 0 && $returnedQty < $consignedQty) {
    $usedQty = max(0, $consignedQty - $returnedQty);
}
```

If a user partially returns items without explicitly flagging the rest as "used", the system **assumes** they were used. This could lead to incorrect `used` records for items that are still at the client site but simply weren't returned yet.

---

### DES-04 — Set unpack only links FIRST component lot to stock-in item

**File:** [`StockInFinalizeService.php:258–260`](file:///d:/Projects/TRETECH/tretech-backend/app/Services/StockIn/StockInFinalizeService.php#L258-L260)

```php
// Link the first component lot back to the stock-in item (for reference)
if ($createdLots->isEmpty()) {
    $item->fill(['lot_id' => $lot->id])->save();
}
```

When unpacking a set of N products, only the first component lot is linked to `stock_in_item.lot_id`. The remaining N-1 lots have no direct `stock_in_item` back-reference. Traceability is lost for most components.

---

### DES-05 — TOCTOU race condition on set availability check vs. confirm

**File:** [`ConsignmentItemService.php:136–149`](file:///d:/Projects/TRETECH/tretech-backend/app/Services/Consignment/ConsignmentItemService.php#L136-L149)

When adding a Set item to a draft consignment, availability is pre-checked:
```php
$totalAvailable = Lot::query()->where('product_id', $setItem->product_id)
    ->where('quantity_available', '>', 0)->sum('quantity_available');
```

But this is **not locked**. Between adding the item and confirming the consignment (which could be hours or days), other consignments can consume the same stock. The confirm service re-validates (with `lockForUpdate`), which is the correct final check — but the add-item validation can give a misleading "OK" to the user for stock that may not exist at confirm time.

---

### DES-06 — `lot_movements` `$timestamps = false` misses `updated_at` but has manual `created_at`

**File:** [`LotMovement.php:15`](file:///d:/Projects/TRETECH/tretech-backend/app/Models/LotMovement.php#L15)

```php
public $timestamps = false;
```

Combined with the migration having `$table->timestamp('created_at')->useCurrent()`, this means Eloquent won't manage timestamps but the DB auto-sets `created_at`. However the model casts `created_at` — so it's readable. This is fine but inconsistent. If someone ever calls `$movement->touch()`, it would fail silently. Not a bug, but a code smell.

---

### DES-07 — `quantity` field not validated against lot's `quantity_consigned` for reconciliation items

No service enforces the accounting identity:
```
consigned_qty == returned_qty + used_qty + damaged_qty + missing_qty
```

The fallback in DES-03 partially compensates but can be wrong. There is no aggregate validation before finalization. The `ReconciliationItem::quantity` field stores `$consignedQty` (from the consignment item), but no code ever asserts that the sum of all outcome quantities equals this.

---

## 5. Reporting Issues 🟡

### RPT-01 — `ReturnsAnalysisService` counts **items** not **quantities**

**File:** [`ReturnsAnalysisService.php:39–48`](file:///d:/Projects/TRETECH/tretech-backend/app/Services/Reporting/ReturnsAnalysisService.php#L39-L48)

```php
$issued   = $c->consignment_items_count; // count of items (rows), not qty
$returned = $c->returnSession?->returnSessionItems->count() ?? 0; // count of scans
$usedCount = $c->reconciliation->reconciliationItems->where('result', 'used')->count();
```

This counts **line items** (rows), not actual **quantities**. A consignment with one item of quantity 100 would show `issued = 1`, not `issued = 100`. The return rate and usage rate would then be `1/1 = 100%` regardless of how many units were actually used.

**Impact:** Reports are misleading for any consignment with multi-quantity lots.

**Fix:** Sum `quantity` instead of counting rows:
```php
$issued   = $c->consignmentItems->sum('quantity');
$returned = $c->returnSession?->returnSessionItems->sum('quantity') ?? 0;
$usedCount = $c->reconciliation->reconciliationItems->sum('used_quantity') ?? 0;
```

---

### RPT-02 — `DisposalReportService` leaks lot records for non-matching items after whereHas filter

**File:** [`DisposalReportService.php:40–54`](file:///d:/Projects/TRETECH/tretech-backend/app/Services/Reporting/DisposalReportService.php#L40-L54)

```php
if (!empty($filters['disposal_category'])) {
    $query->whereHas('disposalItems', function ($q) use ($filters) {
        $q->where('disposal_category', $filters['disposal_category']);
    });
}
```

`whereHas` filters which **disposal sessions** are included — but the sessions are then loaded with **all their items** (via `with('disposalItems')`). So if a session has 3 items with category "expired" and 2 items with category "damaged", filtering by category "expired" will include the session but the export will still contain all 5 items including the 2 "damaged" ones.

**Fix:** Also filter the eager-loaded relationship:
```php
->with(['disposalItems' => function ($q) use ($filters) {
    if (!empty($filters['disposal_category'])) {
        $q->where('disposal_category', $filters['disposal_category']);
    }
    $q->with(['lot.product:id,ref_num,product_name,uom', 'lot.supplier:id,supplier_name']);
}])
```

---

### RPT-03 — `ExpiryDashboardService` queries expired lots with `supplied` status (phantom)

**File:** [`ExpiryDashboardService.php:39`](file:///d:/Projects/TRETECH/tretech-backend/app/Services/Reporting/ExpiryDashboardService.php#L39) and line 55:

```php
->whereIn('lots.status', ['available', 'supplied', 'holding']); // 'supplied' never exists
->whereIn('status', ['available', 'supplied']); // same
```

Since `'supplied'` is never set, this is effectively just querying `['available', 'holding']`. The code is logically correct but misleading. If `'supplied'` is ever properly implemented, these filters would need updating.

---

### RPT-04 — `ReturnsAnalysisService` uses `returned_at` from `returnSession.created_at` (wrong field)

**File:** [`ReturnsAnalysisService.php:61`](file:///d:/Projects/TRETECH/tretech-backend/app/Services/Reporting/ReturnsAnalysisService.php#L61)

```php
'returned_at' => $c->returnSession?->created_at?->format('Y-m-d H:i:s'),
```

The report uses `created_at` as the "returned_at" timestamp. The actual semantically correct field is `started_at` (when the return session started) or `completed_at` (when it was completed). `created_at` is the DB row creation timestamp and could be seconds before/after the actual return event timestamp.

---

## 6. Priority Fix Order

| Priority | ID | Severity | Impact |
|---|---|---|---|
| Done | **BUG-01** | ✅ FIXED | Damaged stock no longer enters available inventory |
| Done | **BUG-07** | ✅ FIXED | Reopen restores pre-finalization lot quantities, status, and location |
| Done | **BUG-02** | ✅ FIXED | Mixed outcomes are resolved once and remain auditable by quantity |
| Done | **BUG-03** | ✅ FIXED | Reconciliation deductions cannot make `quantity_consigned` negative |
| Done | **BUG-04** | ✅ FIXED | Return-session items are loaded once per finalization |
| Done | **BUG-06** | ✅ FIXED | Disposal/SupplierReturn cannot reduce `quantity_available` below zero |
| Done | **BUG-08** | ✅ FIXED | Session reopen restores damaged and missing consigned quantities |
| 7 | **RPT-01** | 🟠 MEDIUM | Reports count rows not quantities — all rates are wrong |
| 8 | **DES-02** | 🟠 MEDIUM | LIKE-on-remarks for set component restore is fragile |
| 9 | **RPT-02** | 🟠 MEDIUM | Disposal filter leaks wrong items into exports |
| 10 | **DB-01** | 🟠 MEDIUM | No DB constraint for lot product/set mutual exclusion |
| 11 | **DB-02** | 🟠 MEDIUM | Multiple open lot_holdings allowed per lot |
| Done | **BUG-05** | ✅ FIXED | Partial consignments record their client location and quantity split |
| 13 | **DES-01** | 🟡 LOW | Phantom `supplied` status throughout summary + expiry services |
| 14 | **DES-03** | 🟡 LOW | Silent "rest = used" fallback assumption |
| 15 | **RPT-03** | 🟡 LOW | Expiry dashboard queries nonexistent `supplied` status |
| 16 | **RPT-04** | 🟡 LOW | Wrong timestamp field used for `returned_at` |
| 17 | **DES-04** | 🟡 LOW | Set unpack only links first lot to stock-in item |
| 18 | **DES-05** | 🟡 LOW | TOCTOU race on set availability check vs. confirm |
| 19 | **DES-07** | 🟡 LOW | No accounting identity check (consigned = returned + used + damaged + missing) |

---

> **Summary of most urgent actions:**
> 1. ✅ BUG-01 fixed — damaged goods no longer inflate `quantity_available`.
> 2. ✅ BUG-02 fixed — mixed outcomes are resolved without status overwrites.
> 3. ✅ BUG-03 fixed — reconciliation deductions are bounded by consigned stock.
> 4. ✅ BUG-04 fixed — return-session items are loaded once and shared by audit and reconciliation logic.
> 5. ✅ BUG-07 fixed — reopening restores every finalized lot movement before recomputation.
