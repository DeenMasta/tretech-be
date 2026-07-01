<?php

$dir = __DIR__ . '/tests';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

foreach ($iterator as $file) {
    if ($file->isDir()) continue;
    if ($file->getExtension() !== 'php') continue;
    
    $path = $file->getPathname();
    $content = file_get_contents($path);
    
    // Replace 'manufacturing_date' => 'SOMETHING' with 'manufacturing_date' => '2026-01-01'
    // Specifically looking for common test batch strings: 'BATCH-TEST', 'BATCH-123', 'BATCH-001'
    
    $modified = false;
    
    $patterns = [
        "/'manufacturing_date'\s*=>\s*'[^']+'/" => "'manufacturing_date' => '2026-01-01'",
        "/\"manufacturing_date\"\s*=>\s*\"[^\"]+\"/" => "\"manufacturing_date\" => \"2026-01-01\""
    ];
    
    foreach ($patterns as $pattern => $replacement) {
        $content = preg_replace_callback($pattern, function($matches) {
            // Check if it's already a valid date-like string (e.g. YYYY-MM-DD)
            if (preg_match("/'\d{4}-\d{2}-\d{2}'/", $matches[0]) || preg_match("/\"\d{4}-\d{2}-\d{2}\"/", $matches[0])) {
                return $matches[0];
            }
            return preg_replace("/['\"][^'\"]+['\"]/", "'2026-01-01'", $matches[0]);
        }, $content, -1, $count);
        
        if ($count > 0) $modified = true;
    }
    
    if ($modified) {
        file_put_contents($path, $content);
        echo "Updated: $path\n";
    }
}
echo "Done\n";
