<?php

namespace App\Http\Requests\Api\V1\ReturnSession;

use Illuminate\Foundation\Http\FormRequest;

class ScanReturnItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lot_id'           => ['nullable', 'integer', 'exists:lots,id'],
            'lot_number'       => ['nullable', 'string', 'max:255'],
            'source_qr_payload' => ['nullable', 'string'],
            'remarks'          => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lot_id.exists' => 'The specified lot does not exist.',
        ];
    }

    /**
     * Ensure at least one of lot_id or lot_number is present.
     *
     * @return array<string, mixed>
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($v) {
            if (empty($this->input('lot_id')) && empty($this->input('lot_number'))) {
                $v->errors()->add('lot_id', 'Either lot_id or lot_number must be provided.');
            }
        });
    }
}
