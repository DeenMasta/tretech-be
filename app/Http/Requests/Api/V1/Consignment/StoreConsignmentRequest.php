<?php

namespace App\Http\Requests\Api\V1\Consignment;

use Illuminate\Foundation\Http\FormRequest;

class StoreConsignmentRequest extends FormRequest
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
            'client_id'      => ['required', 'integer', 'exists:clients,id'],
            'consignment_at' => ['required', 'date'],
            'pic_user_id'    => ['required', 'integer', 'exists:users,id'],
            'remarks'        => ['nullable', 'string'],
        ];
    }
}
