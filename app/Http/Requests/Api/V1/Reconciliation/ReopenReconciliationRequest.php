<?php

namespace App\Http\Requests\Api\V1\Reconciliation;

use Illuminate\Foundation\Http\FormRequest;

class ReopenReconciliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reopen_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
