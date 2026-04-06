<?php

namespace App\Http\Requests\Api\V1\ReturnSession;

use Illuminate\Foundation\Http\FormRequest;

class StoreReturnSessionRequest extends FormRequest
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
            'consignment_id' => ['required', 'integer', 'exists:consignments,id'],
            'pic_user_id'    => ['required', 'integer', 'exists:users,id'],
            'remarks'        => ['nullable', 'string'],
        ];
    }
}
