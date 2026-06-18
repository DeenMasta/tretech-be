<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\StockIn;
use App\Models\User;
use App\Services\StockIn\StockInFinalizeService;
use App\Exceptions\BusinessLogicException;

try {
    $stockIn = StockIn::findOrFail(7);
    $user = User::first(); // Assuming user_id 1
    $service = app(StockInFinalizeService::class);
    $service->finalize($stockIn, $user);
    echo "Success\n";
} catch (BusinessLogicException $e) {
    echo "BusinessLogicException: " . $e->getMessage() . "\n";
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
