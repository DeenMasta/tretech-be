<?php

use App\Models\InstrumentSet;
use App\Models\Lot;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$set = InstrumentSet::with('instrumentSetItems:id,instrument_set_id,product_id,quantity')->where('set_code', 'SET-GEN-01')->first();

if (!$set) {
    echo "Set not found\n";
    exit;
}

$productIds = collect([$set])->pluck('instrumentSetItems.*.product_id')->flatten()->unique()->filter();
echo "Product IDs from pluck:\n";
print_r($productIds->toArray());

$productIds2 = collect([$set])->flatMap(fn($s) => $s->instrumentSetItems->pluck('product_id'))->unique()->filter();
echo "Product IDs from flatMap:\n";
print_r($productIds2->toArray());

$availableStocks = Lot::query()
    ->whereIn('product_id', $productIds2)
    ->where('quantity_available', '>', 0)
    ->selectRaw('product_id, SUM(quantity_available) as total')
    ->groupBy('product_id')
    ->pluck('total', 'product_id');

echo "Available Stocks:\n";
print_r($availableStocks->toArray());

$minSets = null;
foreach ($set->instrumentSetItems as $item) {
    echo "Item product_id: {$item->product_id}, qty: {$item->quantity}\n";
    if ($item->quantity <= 0) continue;
    $avail = $availableStocks[$item->product_id] ?? 0;
    $possible = (int) floor($avail / $item->quantity);
    echo "  avail: $avail, possible: $possible\n";
    if ($minSets === null || $possible < $minSets) {
        $minSets = $possible;
    }
}
echo "Available Sets: " . ($minSets ?? 0) . "\n";
