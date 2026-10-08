<?php

namespace App\Http\Requests\Api\V1\StockAudit;

use Illuminate\Foundation\Http\FormRequest;

class CountStockAuditItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'counted_quantity' => ['required', 'integer', 'min:0'],
            'version' => ['required', 'integer', 'min:1'],
            'count_method' => ['required', 'in:qr,manual'],
            'source_qr_payload' => ['nullable', 'string', 'max:2000'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
