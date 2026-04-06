<?php

namespace App\Http\Requests\Api\V1\QrLabel;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request sent by the Flutter app when a BLE label print fails.
 */
class MarkFailedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'error_message' => ['required', 'string', 'max:2000'],
        ];
    }
}
