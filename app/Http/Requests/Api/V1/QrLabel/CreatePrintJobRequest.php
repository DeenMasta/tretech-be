<?php

namespace App\Http\Requests\Api\V1\QrLabel;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request to create a new print job for an existing finalized lot.
 * Used by the Flutter app when the user wants to print / re-send a label.
 */
class CreatePrintJobRequest extends FormRequest
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
            'printer_name' => ['nullable', 'string', 'max:255'],
            'device_id'    => ['nullable', 'string', 'max:255'],
        ];
    }
}
