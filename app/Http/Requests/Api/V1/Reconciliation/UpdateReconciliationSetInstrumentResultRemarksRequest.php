<?php

namespace App\Http\Requests\Api\V1\Reconciliation;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReconciliationSetInstrumentResultRemarksRequest extends FormRequest
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
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
