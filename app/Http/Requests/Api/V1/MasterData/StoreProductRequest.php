<?php

namespace App\Http\Requests\Api\V1\MasterData;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
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
            'ref_num' => ['required', 'string', 'max:255', Rule::unique('products', 'ref_num')],
            'product_name' => ['required', 'string', 'max:255'],
            'product_type' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'uom' => ['nullable', 'string', 'max:100'],
            'requires_expiry' => ['sometimes', 'boolean'],
            'requires_lot' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
