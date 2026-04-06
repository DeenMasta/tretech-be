<?php

namespace App\Http\Requests\Api\V1\Audit;

use Illuminate\Foundation\Http\FormRequest;

class ListAuditLogsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'page'            => ['nullable', 'integer', 'min:1'],
            'per_page'        => ['nullable', 'integer', 'min:1', 'max:100'],
            'user_id'         => ['nullable', 'integer', 'exists:users,id'],
            'auditable_type'  => ['nullable', 'string', 'max:200'],
            'auditable_id'    => ['nullable', 'integer'],
            'action_type'     => ['nullable', 'string', 'max:100'],
            'ip_address'      => ['nullable', 'string', 'max:45'],
            'device_id'       => ['nullable', 'string', 'max:200'],
            'from_date'       => ['nullable', 'date_format:Y-m-d'],
            'to_date'         => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from_date'],
        ];
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        return $this->only([
            'user_id', 'auditable_type', 'auditable_id',
            'action_type', 'ip_address', 'device_id',
            'from_date', 'to_date',
        ]);
    }

    public function perPage(): int
    {
        return (int) ($this->query('per_page', 20));
    }
}
