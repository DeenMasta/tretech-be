<?php

namespace App\Http\Requests\Api\V1\QrLabel;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request to create a reprint job.
 * A mandatory reason must be supplied (audit trail requirement).
 */
class ReprintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'lot_id'       => ['required', 'integer', 'exists:lots,id'],
            'reason'       => ['required', 'string', 'min:5', 'max:1000'],
            'printer_name' => ['nullable', 'string', 'max:255'],
            'device_id'    => ['nullable', 'string', 'max:255'],
        ];
    }
}
