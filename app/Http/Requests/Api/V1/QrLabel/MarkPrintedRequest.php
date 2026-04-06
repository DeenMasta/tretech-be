<?php

namespace App\Http\Requests\Api\V1\QrLabel;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request sent by the Flutter app after a successful BLE label print.
 */
class MarkPrintedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Optionally the device may report the printer name it actually used
            'printer_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
