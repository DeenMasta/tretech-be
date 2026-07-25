<?php

namespace App\Http\Requests\Api\V1\StockIn;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockInItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('missing_lot_flag')) {
            $this->merge(['missing_lot_flag' => false]);
        }

        if (! $this->has('entry_kind')) {
            $this->merge(['entry_kind' => 'product']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isSet = $this->input('entry_kind') === 'set';

        return [
            'entry_kind' => ['sometimes', Rule::in(['product', 'set'])],

            // Product-mode fields. Required only when entry_kind = product.
            'product_id' => [
                Rule::requiredIf(fn () => ! $isSet),
                'nullable',
                'integer',
                'exists:products,id',
            ],
            'scanned_lot_number' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(function () use ($isSet) {
                    if ($isSet || $this->boolean('missing_lot_flag') || $this->boolean('generate_lot_number')) {
                        return false;
                    }
                    if ($productId = $this->input('product_id')) {
                        $product = Product::find($productId);
                        if ($product && ! $product->requires_lot) {
                            return false;
                        }
                    }

                    return true;
                }),
                function ($attribute, $value, $fail) use ($isSet) {
                    if ($isSet) {
                        return;
                    }
                    if (! empty($value) && $productId = $this->input('product_id')) {
                        $product = Product::find($productId);
                        if ($product && ! $product->requires_lot) {
                            $fail('Lot number cannot be provided for products that do not require it.');
                        }
                    }
                },
            ],
            'manufacturing_date' => [
                'nullable',
                'date',
            ],
            'expiry_date' => [
                'nullable',
                'date',
                Rule::requiredIf(function () use ($isSet) {
                    if ($isSet) {
                        return false;
                    }
                    if ($productId = $this->input('product_id')) {
                        $product = Product::find($productId);
                        if ($product && $product->requires_expiry) {
                            return true;
                        }
                    }

                    return false;
                }),
                function ($attribute, $value, $fail) use ($isSet) {
                    if ($isSet) {
                        return;
                    }
                    if (! empty($value) && $productId = $this->input('product_id')) {
                        $product = Product::find($productId);
                        if ($product && ! $product->requires_expiry) {
                            $fail('Expiry date cannot be provided for products that do not require it.');
                        }
                    }
                },
            ],
            'lot_entry_mode' => ['sometimes', Rule::in(['scan', 'manual'])],
            'expiry_entry_mode' => ['sometimes', Rule::in(['scan', 'manual'])],
            'missing_lot_flag' => [
                'sometimes',
                'boolean',
                function ($attribute, $value, $fail) use ($isSet) {
                    if ($isSet) {
                        return;
                    }
                    if ($value && $productId = $this->input('product_id')) {
                        $product = Product::find($productId);
                        if ($product && ! $product->requires_lot) {
                            $fail('Missing lot flag cannot be true for products that do not require a lot number.');
                        }
                    }
                },
            ],
            'generate_lot_number' => [
                'sometimes',
                'boolean',
                function ($attribute, $value, $fail) use ($isSet) {
                    if ($isSet || ! $value) {
                        return;
                    }

                    $product = Product::find($this->input('product_id'));
                    if (! $product || strcasecmp((string) $product->product_type, 'Instrument') !== 0) {
                        $fail('Generate lot number is only available for instrument products.');
                    }
                },
            ],
            'source_barcode' => ['nullable', 'string'],
            'entry_override_reason' => [
                'nullable',
                'string',
                Rule::requiredIf(function () use ($isSet) {
                    if ($isSet) {
                        return false;
                    }

                    return $this->boolean('missing_lot_flag')
                        || $this->input('lot_entry_mode') === 'manual'
                        || $this->input('expiry_entry_mode') === 'manual';
                }),
            ],
            'quantity' => ['sometimes', 'integer', 'min:1'],
            'remarks' => ['nullable', 'string'],

            // Set-mode field. Required only when entry_kind = set.
            'instrument_set_id' => [
                Rule::requiredIf(fn () => $isSet),
                'nullable',
                'integer',
                'exists:instrument_sets,id',
            ],
            'component_lots' => [
                Rule::requiredIf(fn () => $isSet),
                'nullable',
                'array',
            ],
            'component_lots.*.instrument_set_item_id' => ['required_with:component_lots', 'integer', 'exists:instrument_set_items,id'],
            'component_lots.*.lot_number' => ['nullable', 'string', 'max:255'],
            'component_lots.*.generate_lot_number' => ['required_with:component_lots', 'boolean'],
        ];
    }
}
