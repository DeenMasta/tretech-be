<?php

namespace App\Http\Requests\Api\V1\Disposal;

use Illuminate\Foundation\Http\FormRequest;

class ReopenDisposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reopen_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
