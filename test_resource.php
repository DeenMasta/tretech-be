<?php require __DIR__."/vendor/autoload.php"; $app = require_once __DIR__."/bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\Lot;
use App\Http\Resources\Api\V1\StockIn\LotResource;
use Illuminate\Database\Eloquent\Collection;

$l = Lot::query()->first();
$l->load('product:id,ref_num,product_name');
$c = new Collection([$l]);
$res = LotResource::collection($c);
$request = request();
echo json_encode($res->toArray($request));
