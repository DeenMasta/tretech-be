<?php

namespace App\Http\Requests\Api\V1\StockAudit;

use Illuminate\Foundation\Http\FormRequest;

class BulkCountStockAuditItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:1000'],
            'items.*.id' => ['required', 'integer', 'distinct', 'exists:stock_audit_items,id'],
            'items.*.counted_quantity' => ['required', 'integer', 'min:0'],
            'items.*.version' => ['required', 'integer', 'min:1'],
            'items.*.count_method' => ['required', 'in:qr,manual'],
            'items.*.source_qr_payload' => ['nullable', 'string', 'max:2000'],
            'items.*.remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
