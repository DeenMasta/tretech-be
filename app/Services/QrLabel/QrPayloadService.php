<?php

namespace App\Services\QrLabel;

use App\Models\Lot;
use App\Models\QrLabel;
use App\Models\User;
use App\Exceptions\BusinessLogicException;

class QrPayloadService
{
    /**
     * Canonical payload format:
     *   V=1;REF={RefNum};LOT={LotNumber};BATCH={SupplierBatchCode|-};EXP={YYYY-MM-DD|-}
     *
     * Rules
     *  - V (version) is always "1"
     *  - REF  = product.ref_num  (mandatory)
     *  - LOT  = lot.lot_number   (mandatory)
     *  - BATCH = lot.supplier_batch_code or "-" when absent
     *  - EXP   = expiry_date formatted Y-m-d or "-" when absent/not required
     */
    public function generatePayload(Lot $lot): string
    {
        $lot->loadMissing(['product:id,ref_num', 'instrumentSet:id,set_code']);

        if ($lot->product_id !== null) {
            $ref = $lot->product?->ref_num ?? '';
        } elseif ($lot->instrument_set_id !== null) {
            $ref = $lot->instrumentSet?->set_code ?? '';
        } else {
            $ref = '';
        }

        $lotNo = $lot->lot_number ?? '';

        if ($ref === '' || $lotNo === '') {
            throw new BusinessLogicException('Cannot generate QR payload: lot is missing ref_num/set_code or lot_number.');
        }

        $batch = ($lot->supplier_batch_code !== null && $lot->supplier_batch_code !== '')
            ? $lot->supplier_batch_code
            : '-';

        $exp = $lot->expiry_date ? $lot->expiry_date->format('Y-m-d') : '-';

        return sprintf('V=1;REF=%s;LOT=%s;BATCH=%s;EXP=%s', $ref, $lotNo, $batch, $exp);
    }

    /**
     * Parse and validate a raw QR payload string.
     *
     * Returns an associative array of the parsed segments on success.
     *
     * @return array{version:string,ref:string,lot:string,batch:string,exp:string}
     * @throws BusinessLogicException if the payload is invalid
     */
    public function validatePayload(string $payload): array
    {
        $segments = [];
        foreach (explode(';', $payload) as $segment) {
            if (!str_contains($segment, '=')) {
                throw new BusinessLogicException("Invalid QR payload segment: \"{$segment}\".");
            }
            [$key, $value]     = explode('=', $segment, 2);
            $segments[strtoupper(trim($key))] = trim($value);
        }

        foreach (['V', 'REF', 'LOT', 'BATCH', 'EXP'] as $required) {
            if (!isset($segments[$required]) || $segments[$required] === '') {
                throw new BusinessLogicException("QR payload is missing required field: {$required}.");
            }
        }

        if ($segments['V'] !== '1') {
            throw new BusinessLogicException("Unsupported QR payload version: {$segments['V']}.");
        }

        return [
            'version' => $segments['V'],
            'ref'     => $segments['REF'],
            'lot'     => $segments['LOT'],
            'batch'   => $segments['BATCH'],
            'exp'     => $segments['EXP'],
        ];
    }

    /**
     * Create (or return the existing) QrLabel record for a lot.
     * Called automatically during stock-in finalization.
     */
    public function createLabelForLot(Lot $lot, User $actor): QrLabel
    {
        $existing = QrLabel::query()->where(['lot_id' => $lot->id])->first();
        if ($existing !== null) {
            // Self-heal: if the stored payload is the old JSON format, regenerate it.
            if (str_starts_with(trim($existing->qr_payload), '{')) {
                try {
                    $existing->qr_payload = $this->generatePayload($lot);
                    $existing->save();
                } catch (\Throwable) {
                    // Ignore regeneration errors; buildTsplPayload will also regenerate.
                }
            }
            return $existing;
        }

        $payload = $this->generatePayload($lot);

        return QrLabel::query()->create([
            'lot_id'               => $lot->id,
            'qr_payload'           => $payload,
            'generated_at'         => now(),
            'generated_by_user_id' => $actor->id,
        ]);
    }

    /**
     * Build a TSPL command string suitable for a 100×150 mm Bluetooth label printer.
     * The Flutter app sends this string to the printer via BLE.
     *
     * Layout:
     *   QR Code: Top Left
     *   Address: Top Right
     *   Details (Ref, Lot, Exp / Set Name, Set Code): Bottom
     */
    public function buildTsplPayload(string $qrPayload, Lot $lot): string
    {
        $lot->loadMissing(['product:id,ref_num,product_name', 'instrumentSet:id,set_code,set_name']);

        $lotNo = $lot->lot_number;
        $exp   = $lot->expiry_date ? $lot->expiry_date->format('Y-m-d') : '-';

        // Always regenerate the canonical QR payload from the lot to avoid
        // using stale records that may contain the old JSON format.
        try {
            $qrPayload = $this->generatePayload($lot);
        } catch (\Throwable) {
            // If regeneration fails, fall back to the stored value.
        }

        // TSPL commands for a 100mm × 150mm label at 203 DPI (1mm = 8 dots)
        $lines = [
            'SIZE 100 mm, 150 mm',
            'GAP 2 mm, 0 mm',
            'DIRECTION 1',
            'CLS',
            // QR code: top-left area
            "QRCODE 20,20,H,4,A,0,M2,S6,\"{$qrPayload}\"",
            // Company Address Header: right of QR code
            "TEXT 220,20,\"2\",0,1,1,\"TREMED Surgical Solution Sdn Bhd\"",
            "TEXT 220,50,\"1\",0,1,1,\"No 6-1, Block A, Zenith Corporate\"",
            "TEXT 220,70,\"1\",0,1,1,\"Park, Jalan SS 7/26, Kelana Jaya\"",
            "TEXT 220,90,\"1\",0,1,1,\"47301 Petaling Jaya, Selangor\"",
            "TEXT 220,110,\"1\",0,1,1,\"Tel: 0126338787\"",
            "TEXT 220,130,\"1\",0,1,1,\"Email: finance@tremedsurgical.com\"",
        ];

        if ($lot->product_id !== null) {
            $ref = $lot->product?->ref_num ?? '-';
            
            $lines = array_merge($lines, [
                // Product Details
                "TEXT 20,230,\"2\",0,1,1,\"Ref : {$ref}\"",
                "TEXT 20,260,\"2\",0,1,1,\"Lot : {$lotNo}\"",
                "TEXT 20,290,\"2\",0,1,1,\"Exp : {$exp}\"",
            ]);
        } elseif ($lot->instrument_set_id !== null) {
            $setCode = $lot->instrumentSet?->set_code ?? '-';
            $setName = $lot->instrumentSet?->set_name ?? '-';
            
            $lines = array_merge($lines, [
                // Instrument Details
                "TEXT 20,250,\"2\",0,1,1,\"Set Name : {$setName}\"",
                "TEXT 20,280,\"2\",0,1,1,\"Set Code : {$setCode}\"",
            ]);
        }

        $lines[] = 'PRINT 1,1';

        return implode("\r\n", $lines);
    }
}
