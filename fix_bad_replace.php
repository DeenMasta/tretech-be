<?php
$directory = __DIR__ . '/tests';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $content = file_get_contents($file->getPathname());
        $newContent = str_replace("'2026-01-01' => '2026-01-01'", "'manufacturing_date' => '2026-01-01'", $content);
        $newContent = str_replace('"2026-01-01" => "2026-01-01"', '"manufacturing_date" => "2026-01-01"', $newContent);
        
        $newContent = str_replace("'2026-01-01'   => '2026-01-01'", "'manufacturing_date'   => '2026-01-01'", $newContent);
        $newContent = str_replace("'2026-01-01'     => '2026-01-01'", "'manufacturing_date'     => '2026-01-01'", $newContent);

        // Also fix the case with spaces
        $newContent = preg_replace("/'2026-01-01'\s*=>\s*'2026-01-01'/", "'manufacturing_date' => '2026-01-01'", $newContent);

        if ($content !== $newContent) {
            file_put_contents($file->getPathname(), $newContent);
            echo "Fixed {$file->getPathname()}\n";
        }
    }
}
echo "Done";
