<?php

namespace App\Http\Requests\Api\V1\Consignment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateConsignmentRequest extends FormRequest
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
            'client_id'      => ['sometimes', 'integer', 'exists:clients,id'],
            'consignment_at' => ['sometimes', 'date'],
            'pic_user_id'    => ['sometimes', 'integer', 'exists:users,id'],
            'surgeon_name'   => ['nullable', 'string', 'max:255'],
            'case_name'      => ['nullable', 'string', 'max:255'],
            'case_date'      => ['nullable', 'date'],
            'remarks'        => ['nullable', 'string'],
        ];
    }
}
