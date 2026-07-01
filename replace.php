<?php

$dir = __DIR__;
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

$search1 = 'supplier_batch_code';
$replace1 = 'manufacturing_date';

$search2 = 'supplierBatchCode';
$replace2 = 'manufacturingDate';

$search3 = 'supplier batch code';
$replace3 = 'manufacturing date';

$search4 = 'Supplier Batch Code';
$replace4 = 'Manufacturing Date';

$search5 = 'SupplierBatchCode';
$replace5 = 'ManufacturingDate';

$excludeDirs = ['/vendor/', '/node_modules/', '/storage/', '/.git/', '/database/migrations/'];

foreach ($iterator as $file) {
    if ($file->isDir()) continue;
    
    $path = $file->getPathname();
    $skip = false;
    foreach ($excludeDirs as $exc) {
        if (strpos(str_replace('\\', '/', $path), $exc) !== false) {
            $skip = true;
            break;
        }
    }
    
    if ($skip) continue;
    
    if ($file->getExtension() !== 'php' && $file->getExtension() !== 'md' && $file->getExtension() !== 'js') {
        continue;
    }

    // skip this script
    if (basename($path) === 'replace.php') continue;

    $content = file_get_contents($path);
    if (
        strpos($content, $search1) !== false || 
        strpos($content, $search2) !== false ||
        strpos($content, $search3) !== false ||
        strpos($content, $search4) !== false ||
        strpos($content, $search5) !== false
    ) {
        $content = str_replace($search1, $replace1, $content);
        $content = str_replace($search2, $replace2, $content);
        $content = str_replace($search3, $replace3, $content);
        $content = str_replace($search4, $replace4, $content);
        $content = str_replace($search5, $replace5, $content);
        file_put_contents($path, $content);
        echo "Updated: $path\n";
    }
}
echo "Done\n";
