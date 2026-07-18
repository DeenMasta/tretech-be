<?php

namespace App\Services\QrLabel;

use App\Exceptions\BusinessLogicException;
use App\Models\Lot;
use App\Models\QrLabel;
use App\Models\User;

class QrPayloadService
{
    /**
     * Canonical payload format:
     *   V=1;REF={RefNum};LOT={LotNumber};MFG={YYYY-MM-DD|-};EXP={YYYY-MM-DD|-}
     *
     * Rules
     *  - V (version) is always "1"
     *  - REF  = product.ref_num  (mandatory)
     *  - LOT  = lot.lot_number   (mandatory)
     *  - MFG   = lot.manufacturing_date formatted Y-m-d or "-" when absent
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

        $manufacturingDate = $lot->manufacturing_date
            ? $lot->manufacturing_date->format('Y-m-d')
            : '-';

        $exp = $lot->expiry_date ? $lot->expiry_date->format('Y-m-d') : '-';

        return sprintf('V=1;REF=%s;LOT=%s;MFG=%s;EXP=%s', $ref, $lotNo, $manufacturingDate, $exp);
    }

    /**
     * Parse and validate a raw QR payload string.
     *
     * Returns an associative array of the parsed segments on success.
     *
     * @return array{version:string,ref:string,lot:string,mfg:string,exp:string}
     *
     * @throws BusinessLogicException if the payload is invalid
     */
    public function validatePayload(string $payload): array
    {
        $segments = [];
        foreach (explode(';', $payload) as $segment) {
            if (!str_contains($segment, '=')) {
                throw new BusinessLogicException("Invalid QR payload segment: \"{$segment}\".");
            }
            [$key, $value] = explode('=', $segment, 2);
            $segments[strtoupper(trim($key))] = trim($value);
        }

        if (isset($segments['BATCH']) && ! isset($segments['MFG'])) {
            $segments['MFG'] = $segments['BATCH'];
        }

        foreach (['V', 'REF', 'LOT', 'MFG', 'EXP'] as $required) {
            if (!isset($segments[$required]) || $segments[$required] === '') {
                throw new BusinessLogicException("QR payload is missing required field: {$required}.");
            }
        }

        if ($segments['V'] !== '1') {
            throw new BusinessLogicException("Unsupported QR payload version: {$segments['V']}.");
        }

        return [
            'version' => $segments['V'],
            'ref' => $segments['REF'],
            'lot' => $segments['LOT'],
            'mfg' => $segments['MFG'],
            'exp' => $segments['EXP'],
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
            if (str_starts_with(trim($existing->qr_payload), '{') || str_contains($existing->qr_payload, 'BATCH=')) {
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
            'lot_id' => $lot->id,
            'qr_payload' => $payload,
            'generated_at' => now(),
            'generated_by_user_id' => $actor->id,
        ]);
    }

    /**
     * Build a TSPL command string for a 50x30 mm Bluetooth sticker printer.
     * The layout keeps the company details and compresses the QR/text blocks.
     */
    public function buildTsplPayload(string $qrPayload, Lot $lot): string
    {
        $lot->loadMissing(['product:id,ref_num,product_name', 'instrumentSet:id,set_code,set_name']);

        $lotNo = $lot->lot_number;
        $exp = $lot->expiry_date?->format('Y-m-d');
        $mfg = $lot->manufacturing_date?->format('Y-m-d');

        try {
            $qrPayload = $this->generatePayload($lot);
        } catch (\Throwable) {
            // If regeneration fails, fall back to the stored value.
        }

        $lines = [
            'SIZE 50 mm, 30 mm',
            'GAP 2 mm, 0 mm',
            'DIRECTION 1',
            'CLS',
            "QRCODE 8,8,H,3,A,0,M2,S1,\"{$qrPayload}\"",
            'TEXT 140,15,"0",0,1,1,"TREMED Surgical Solution"',
            'TEXT 140,31,"0",0,1,1,"No 6-1, Block A,"',
            'TEXT 140,47,"0",0,1,1,"Zenith Corporate Park,"',
            'TEXT 140,63,"0",0,1,1,"Jalan SS 7/26, 47301"',
            'TEXT 140,79,"0",0,1,1,"Petaling Jaya, Selangor"',
            'TEXT 140,95,"0",0,1,1,"Tel: 0126338787"',
            'TEXT 140,111,"0",0,1,1,"finance@tremedsurgical.com"',
        ];

        if ($lot->product_id !== null) {
            $ref = $lot->product?->ref_num ?? '-';
            $productTitle = $lot->product?->product_name;
            if ($productTitle === null || trim($productTitle) === '') {
                $productTitle = $ref;
            }
            $usesCompactDetailsTemplate = mb_strlen($productTitle) > 23 || mb_strlen((string) $lotNo) > 23;
            $detailFont = $usesCompactDetailsTemplate ? '1' : '2';

            $lines = array_merge($lines, [
                'TEXT 8,140,"' . $detailFont . '",0,1,1,"' . $this->sanitizeTsplText($productTitle, 36) . '"',
                'TEXT 8,163,"' . $detailFont . '",0,1,1,"REF: ' . $this->sanitizeTsplText($ref, 30) . '"',
                'TEXT 8,183,"' . $detailFont . '",0,1,1,"LOT: ' . $this->sanitizeTsplText($lotNo, 30) . '"',
            ]);

            $this->appendDateLines($lines, 205, $exp, $mfg, $usesCompactDetailsTemplate ? '1' : null);
        } elseif ($lot->instrument_set_id !== null) {
            $setCode = $lot->instrumentSet?->set_code ?? '-';
            $setName = $lot->instrumentSet?->set_name ?? '-';

            $lines = array_merge($lines, [
                'TEXT 8,140,"2",0,1,1,"' . $this->sanitizeTsplText($setName, 36) . '"',
                'TEXT 8,163,"2",0,1,1,"CODE: ' . $this->sanitizeTsplText($setCode, 30) . '"',
            ]);

            $this->appendDateLines($lines, 185, $exp, $mfg);
        }

        $lines[] = 'PRINT 1,1';

        return implode("\r\n", $lines);
    }

    /**
     * Add optional EXP/MFG label lines. Skip placeholders entirely.
     *
     * @param array<int, string> $lines
     */
    private function appendDateLines(array &$lines, int $y, ?string $exp, ?string $mfg, ?string $forcedFont = null): void
    {
        $paired = $exp !== null && $mfg !== null;
        $font = $forcedFont ?? ($paired ? '1' : '2');

        if ($exp !== null) {
            $lines[] = 'TEXT 8,' . $y . ',"' . $font . '",0,1,1,"EXP: ' . $this->sanitizeTsplText($exp, 14) . '"';
        }

        if ($mfg !== null) {
            $lines[] = 'TEXT 210,' . $y . ',"' . $font . '",0,1,1,"MFG: ' . $this->sanitizeTsplText($mfg, 14) . '"';
        }
    }

    private function sanitizeTsplText(?string $value, int $maxLength = 24): string
    {
        $text = trim((string) $value);
        $text = str_replace(['"', "\r", "\n"], ["'", ' ', ' '], $text);
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        if (strlen($text) <= $maxLength) {
            return $text;
        }

        return rtrim(substr($text, 0, max(0, $maxLength - 3))) . '...';
    }
}
