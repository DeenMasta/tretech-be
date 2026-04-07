<?php

namespace App\Http\Requests\Api\V1\UsageSummary;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateUsageSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reconciliation_id' => [
                'required',
                'integer',
                Rule::exists('reconciliations', 'id')->where('status', 'finalized'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'reconciliation_id.exists' => 'The reconciliation must exist and be in finalized status.',
        ];
    }
}
