<?php

namespace App\Http\Requests\Api\V1\StockAudit;

use Illuminate\Foundation\Http\FormRequest;

class SubmitStockAuditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
