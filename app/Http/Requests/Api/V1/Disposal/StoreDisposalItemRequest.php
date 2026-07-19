<?php

namespace App\Http\Requests\Api\V1\Disposal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDisposalItemRequest extends FormRequest
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
            'lot_id'            => ['required', 'integer', 'exists:lots,id'],
            'disposal_category' => [
                'required',
                'string',
                Rule::in(['expired', 'damaged', 'lost', 'other']),
            ],
            'quantity'    => ['required', 'integer', 'min:1'],
            'reason_text' => ['required', 'string', 'max:500'],
            'remarks'     => ['nullable', 'string', 'max:1000'],
        ];
    }
}
