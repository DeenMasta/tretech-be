<?php

namespace App\Http\Requests\Api\V1\Consignment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConsignmentItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (!$this->has('entry_kind')) {
            $this->merge(['entry_kind' => 'lot']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isSet = $this->input('entry_kind') === 'set';

        return [
            'entry_kind' => ['sometimes', Rule::in(['lot', 'set'])],

            // Lot-mode fields. Required only when entry_kind = 'lot'.
            'lot_id' => [
                Rule::requiredIf(fn () => !$isSet),
                'nullable',
                'integer',
                'exists:lots,id',
            ],

            // Set-mode fields. Required only when entry_kind = 'set'.
            'instrument_set_id' => [
                Rule::requiredIf(fn () => $isSet),
                'nullable',
                'integer',
                'exists:instrument_sets,id',
            ],

            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
