<?php

use App\Models\QrLabel;

echo "Updating QR labels...\n";
$labels = QrLabel::where('qr_payload', 'LIKE', '%BATCH=%')->get();
$count = 0;
foreach ($labels as $label) {
    $label->qr_payload = str_replace('BATCH=', 'MFG=', $label->qr_payload);
    $label->save();
    $count++;
}
echo "Updated {$count} labels.\n";
