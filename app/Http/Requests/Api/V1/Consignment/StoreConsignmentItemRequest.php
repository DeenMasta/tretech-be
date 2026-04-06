<?php

namespace App\Http\Requests\Api\V1\Consignment;

use Illuminate\Foundation\Http\FormRequest;

class StoreConsignmentItemRequest extends FormRequest
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
            'lot_id'  => ['required', 'integer', 'exists:lots,id'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
