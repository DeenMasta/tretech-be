<?php

namespace App\Http\Requests\Api\V1\StockIn;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStockInSessionRequest extends FormRequest
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
            'supplier_id' => ['sometimes', 'integer', 'exists:suppliers,id'],
            'do_number' => ['sometimes', 'string', 'max:255'],
            'stock_in_at' => ['sometimes', 'date'],
            'pic_user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
