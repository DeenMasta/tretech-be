<?php

namespace App\Http\Requests\Api\V1\StockAudit;

use Illuminate\Foundation\Http\FormRequest;

class LookupStockAuditItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'qr_payload' => ['nullable', 'string', 'required_without_all:ref_code,lot_number', 'max:2000'],
            'ref_code' => ['nullable', 'string', 'required_without:qr_payload', 'max:255'],
            'lot_number' => ['nullable', 'string', 'required_without:qr_payload', 'max:255'],
        ];
    }
}
