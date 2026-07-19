<?php

namespace App\Http\Requests\Api\V1\Consignment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateConsignmentItemRequest extends FormRequest
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
            'proposed_quantity' => ['sometimes', 'integer', 'min:1'],
            'quantity'          => ['sometimes', 'integer', 'min:1'],
            'remarks'           => ['nullable', 'string'],
        ];
    }
}
