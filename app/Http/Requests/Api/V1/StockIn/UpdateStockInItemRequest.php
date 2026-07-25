<?php

namespace App\Http\Requests\Api\V1\StockIn;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStockInItemRequest extends FormRequest
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
            'product_id' => ['sometimes', 'integer', 'exists:products,id'],
            'scanned_lot_number' => [
                'nullable',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    if (empty($value)) {
                        return;
                    }

                    $stockInItem = $this->route('stockInItem');
                    $productId = $this->input('product_id', $stockInItem ? $stockInItem->product_id : null);

                    if ($productId) {
                        $product = Product::find($productId);
                        if ($product && ! $product->requires_lot) {
                            $fail('Lot number cannot be provided for products that do not require it.');
                        }
                    }
                },
            ],
            'manufacturing_date' => ['nullable', 'date'],
            'expiry_date' => [
                'nullable',
                'date',
                function ($attribute, $value, $fail) {
                    $stockInItem = $this->route('stockInItem');
                    $productId = $this->input('product_id', $stockInItem ? $stockInItem->product_id : null);

                    if ($productId) {
                        $product = Product::find($productId);
                        if ($product) {
                            if ($product->requires_expiry && empty($value)) {
                                $fail('The expiry date field is required.');
                            }
                            if (! $product->requires_expiry && ! empty($value)) {
                                $fail('Expiry date cannot be provided for products that do not require it.');
                            }
                        }
                    }
                },
            ],
            'lot_entry_mode' => ['sometimes', Rule::in(['scan', 'manual'])],
            'expiry_entry_mode' => ['sometimes', Rule::in(['scan', 'manual'])],
            'missing_lot_flag' => [
                'sometimes',
                'boolean',
                function ($attribute, $value, $fail) {
                    if (! $value) {
                        return;
                    }

                    $stockInItem = $this->route('stockInItem');
                    $productId = $this->input('product_id', $stockInItem ? $stockInItem->product_id : null);

                    if ($productId) {
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
                function ($attribute, $value, $fail) {
                    if (! $value) {
                        return;
                    }

                    $stockInItem = $this->route('stockInItem');
                    $productId = $this->input('product_id', $stockInItem ? $stockInItem->product_id : null);
                    $product = Product::find($productId);
                    if (! $product || strcasecmp((string) $product->product_type, 'Instrument') !== 0) {
                        $fail('Generate lot number is only available for instrument products.');
                    }
                },
            ],
            'source_barcode' => ['nullable', 'string'],
            'entry_override_reason' => ['nullable', 'string'],
            'quantity' => ['sometimes', 'integer', 'min:1'],
            'remarks' => ['nullable', 'string'],
            'component_lots' => ['sometimes', 'array'],
            'component_lots.*.instrument_set_item_id' => ['required_with:component_lots', 'integer', 'exists:instrument_set_items,id'],
            'component_lots.*.lot_number' => ['nullable', 'string', 'max:255'],
            'component_lots.*.generate_lot_number' => ['required_with:component_lots', 'boolean'],
        ];
    }
}
