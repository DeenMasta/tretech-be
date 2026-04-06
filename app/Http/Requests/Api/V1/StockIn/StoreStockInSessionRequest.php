<?php

namespace App\Http\Requests\Api\V1\StockIn;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockInSessionRequest extends FormRequest
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
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'do_number' => ['required', 'string', 'max:255'],
            'stock_in_at' => ['required', 'date'],
            'pic_user_id' => ['required', 'integer', 'exists:users,id'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
