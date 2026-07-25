<?php

namespace App\Http\Requests\Api\V1\ReturnSession;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReturnSessionItemRemarksRequest extends FormRequest
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
