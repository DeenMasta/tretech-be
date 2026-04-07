<?php

namespace App\Http\Requests\Api\V1\SupplierReturn;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierReturnRequest extends FormRequest
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
            'supplier_id'  => ['required', 'integer', 'exists:suppliers,id'],
            'returned_at'  => ['required', 'date'],
            'pic_user_id'  => ['required', 'integer', 'exists:users,id'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'remarks'      => ['nullable', 'string', 'max:1000'],
        ];
    }
}
