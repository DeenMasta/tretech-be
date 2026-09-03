<?php

namespace App\Http\Requests\Api\V1\StockIn;

use Illuminate\Foundation\Http\FormRequest;

class CorrectStockInItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // At least one of the three correctable fields must be present
            // (enforced in service layer — all three are optional individually)
            'lot_number'          => ['sometimes', 'string', 'max:255'],
            'manufacturing_date' => ['sometimes', 'nullable', 'date'],
            'expiry_date'         => ['sometimes', 'nullable', 'date'],
            'quantity'            => ['sometimes', 'integer', 'min:1'],

            // Mandatory reason for admin correction (audit trail requirement)
            'admin_reason'        => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
