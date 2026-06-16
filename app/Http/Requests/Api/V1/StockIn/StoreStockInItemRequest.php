<?php

namespace App\Http\Requests\Api\V1\StockIn;

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
        if (!$this->has('missing_lot_flag')) {
            $this->merge(['missing_lot_flag' => false]);
        }

        if (!$this->has('entry_kind')) {
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
                Rule::requiredIf(fn () => !$isSet),
                'nullable',
                'integer',
                'exists:products,id',
            ],
            'scanned_lot_number' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(fn () => !$isSet && !$this->boolean('missing_lot_flag')),
            ],
            'supplier_batch_code' => [
                Rule::requiredIf(fn () => !$isSet),
                'nullable',
                'string',
                'max:255',
            ],
            'expiry_date' => ['nullable', 'date'],
            'lot_entry_mode' => ['sometimes', Rule::in(['scan', 'manual'])],
            'expiry_entry_mode' => ['sometimes', Rule::in(['scan', 'manual'])],
            'missing_lot_flag' => ['sometimes', 'boolean'],
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
            'remarks' => ['nullable', 'string'],

            // Set-mode field. Required only when entry_kind = set.
            'instrument_set_id' => [
                Rule::requiredIf(fn () => $isSet),
                'nullable',
                'integer',
                'exists:instrument_sets,id',
            ],
        ];
    }
}
