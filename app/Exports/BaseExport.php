<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Base export class shared by all report export classes.
 * Accepts a flat array of associative-array rows; derives headings from the first row keys.
 */
class BaseExport implements FromArray, WithHeadings, WithStyles
{
    public function __construct(protected array $rows)
    {
    }

    public function array(): array
    {
        return array_map('array_values', $this->rows);
    }

    public function headings(): array
    {
        if (empty($this->rows)) {
            return [];
        }

        return array_keys($this->rows[0]);
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
