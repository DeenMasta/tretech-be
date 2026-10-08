<?php

namespace App\Http\Requests\Api\V1\StockAudit;

use Illuminate\Foundation\Http\FormRequest;

class CancelStockAuditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:3', 'max:2000']];
    }
}
