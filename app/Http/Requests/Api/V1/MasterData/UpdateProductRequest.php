<?php

namespace App\Http\Requests\Api\V1\MasterData;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
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
        /** @var Product|int|string|null $product */
        $product = $this->route('product');
        $productId = $product instanceof Product ? $product->id : $product;

        return [
            'ref_num' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('products', 'ref_num')->ignore($productId)],
            'product_name' => ['sometimes', 'required', 'string', 'max:255'],
            'product_type' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'uom' => ['nullable', 'string', 'max:100'],
            'requires_expiry' => ['sometimes', 'boolean'],
            'requires_lot' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
