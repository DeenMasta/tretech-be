<?php

namespace App\Http\Requests\Api\V1\Consignment;

use Illuminate\Foundation\Http\FormRequest;

class PostConfirmEditRequest extends FormRequest
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
            'reason'  => ['required', 'string', 'min:5', 'max:1000'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
