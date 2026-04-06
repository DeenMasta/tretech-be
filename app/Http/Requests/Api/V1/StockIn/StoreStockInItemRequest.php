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
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'scanned_lot_number' => ['nullable', 'string', 'max:255', 'required_without:missing_lot_flag'],
            'supplier_batch_code' => ['required', 'string', 'max:255'],
            'expiry_date' => ['nullable', 'date'],
            'lot_entry_mode' => ['sometimes', Rule::in(['scan', 'manual'])],
            'expiry_entry_mode' => ['sometimes', Rule::in(['scan', 'manual'])],
            'missing_lot_flag' => ['sometimes', 'boolean'],
            'source_barcode' => ['nullable', 'string'],
            'entry_override_reason' => [
                'nullable',
                'string',
                Rule::requiredIf(function () {
                    return $this->boolean('missing_lot_flag')
                        || $this->input('lot_entry_mode') === 'manual'
                        || $this->input('expiry_entry_mode') === 'manual';
                }),
            ],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
