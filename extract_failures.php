<?php
$log = file_get_contents('C:\Users\irsya\.gemini\antigravity-ide\brain\481f4bc0-6a21-4215-a63c-f4108d814e7e\.system_generated\tasks\task-243.log');
$lines = explode("\n", $log);
$recording = false;
$output = [];
foreach ($lines as $line) {
    if (strpos($line, '⨯') !== false || strpos($line, 'FAIL') !== false || strpos($line, 'Exception') !== false || strpos($line, 'Error') !== false || strpos($line, 'Failed asserting') !== false || strpos($line, '.php:') !== false) {
        $output[] = $line;
    }
}
file_put_contents('failures.txt', implode("\n", array_slice($output, 0, 100)));
echo "Extracted failures";
