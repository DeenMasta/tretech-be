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
        $lot->loadMissing('product:id,ref_num');

        $ref   = $lot->product?->ref_num ?? '';
        $lotNo = $lot->lot_number ?? '';

        if ($ref === '' || $lotNo === '') {
            throw new BusinessLogicException('Cannot generate QR payload: lot is missing ref_num or lot_number.');
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
        $existing = QrLabel::query()->where('lot_id', $lot->id)->first();
        if ($existing !== null) {
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
     * Build a minimal TSPL command string suitable for a 40×30 mm ZPL/TSPL Bluetooth label printer.
     * The Flutter app sends this string to the printer via BLE.
     *
     * Layout (top → bottom):
     *   Line 1: REF value (large)
     *   Line 2: LOT value
     *   Line 3: EXP value
     *   QR code: the canonical payload
     */
    public function buildTsplPayload(string $qrPayload, Lot $lot): string
    {
        $lot->loadMissing('product:id,ref_num,product_name');

        $ref        = $lot->product?->ref_num ?? '-';
        $productName = $lot->product?->product_name ?? '';
        $lotNo      = $lot->lot_number;
        $exp        = $lot->expiry_date ? $lot->expiry_date->format('d/m/Y') : 'NO EXPIRY';

        // TSPL commands for a 40mm × 30mm label at 203 DPI
        $lines = [
            'SIZE 40 mm, 30 mm',
            'GAP 2 mm, 0 mm',
            'DIRECTION 1',
            'CLS',
            // QR code: top-left area
            "QRCODE 5,5,H,4,A,0,M2,S3,\"{$qrPayload}\"",
            // Product REF — right of QR
            "TEXT 130,5,\"3\",0,1,1,\"{$ref}\"",
            // Product name (truncated to 18 chars)
            'TEXT 130,25,"2",0,1,1,"' . mb_substr($productName, 0, 18) . '"',
            // LOT
            "TEXT 130,45,\"2\",0,1,1,\"LOT:{$lotNo}\"",
            // EXP
            "TEXT 130,65,\"2\",0,1,1,\"EXP:{$exp}\"",
            'PRINT 1,1',
        ];

        return implode("\r\n", $lines);
    }
}
