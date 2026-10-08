<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InventoryReportExport extends BaseExport
{
    public function array(): array
    {
        return array_map(
            fn (array $row): array => array_values($this->visibleRow($row)),
            $this->rows
        );
    }

    public function headings(): array
    {
        return empty($this->rows) ? [] : array_keys($this->visibleRow($this->rows[0]));
    }

    public function styles(Worksheet $sheet): array
    {
        $styles = parent::styles($sheet);

        foreach ($this->rows as $index => $row) {
            if (! ($row['__product_total'] ?? false)) {
                continue;
            }

            $styles[$index + 2] = [
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'color' => ['rgb' => 'E2F0D9'],
                ],
                'font' => ['bold' => true],
            ];
        }

        return $styles;
    }

    /** @param array<string, mixed> $row */
    private function visibleRow(array $row): array
    {
        unset($row['__product_total']);

        return $row;
    }
}
