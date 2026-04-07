<?php

namespace App\Http\Requests\Api\V1\Disposal;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDisposalRequest extends FormRequest
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
            'disposed_at' => ['sometimes', 'date'],
            'pic_user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'remarks'     => ['nullable', 'string', 'max:1000'],
        ];
    }
}
