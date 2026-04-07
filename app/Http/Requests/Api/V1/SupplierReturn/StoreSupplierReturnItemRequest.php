<?php

namespace App\Http\Requests\Api\V1\SupplierReturn;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierReturnItemRequest extends FormRequest
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
            'lot_id'        => ['required', 'integer', 'exists:lots,id'],
            'return_reason' => ['required', 'string', 'max:500'],
            'remarks'       => ['nullable', 'string', 'max:1000'],
        ];
    }
}
