<?php

use Illuminate\Http\Request;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$request = Request::create('/api/v1/master-data/instrument-sets?include_availability=1', 'GET');
$response = $kernel->handle($request);
echo $response->getContent();
