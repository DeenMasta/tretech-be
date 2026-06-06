<?php

namespace App\Http\Requests\Api\V1\MasterData;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInstrumentSetItemRequest extends FormRequest
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
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:9999'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
