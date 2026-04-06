<?php

namespace App\Http\Requests\Api\V1\StockIn;

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
            'scanned_lot_number' => ['nullable', 'string', 'max:255'],
            'supplier_batch_code' => ['sometimes', 'string', 'max:255'],
            'expiry_date' => ['nullable', 'date'],
            'lot_entry_mode' => ['sometimes', Rule::in(['scan', 'manual'])],
            'expiry_entry_mode' => ['sometimes', Rule::in(['scan', 'manual'])],
            'missing_lot_flag' => ['sometimes', 'boolean'],
            'source_barcode' => ['nullable', 'string'],
            'entry_override_reason' => ['nullable', 'string'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
