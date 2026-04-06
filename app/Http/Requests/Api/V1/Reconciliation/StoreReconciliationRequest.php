<?php

namespace App\Http\Requests\Api\V1\Reconciliation;

use Illuminate\Foundation\Http\FormRequest;

class StoreReconciliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'return_session_id' => ['required', 'integer', 'exists:return_sessions,id'],
            'pic_user_id'       => ['required', 'integer', 'exists:users,id'],
            'remarks'           => ['nullable', 'string', 'max:1000'],
        ];
    }
}
