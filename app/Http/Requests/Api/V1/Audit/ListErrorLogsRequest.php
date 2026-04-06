<?php

namespace App\Http\Requests\Api\V1\Audit;

use Illuminate\Foundation\Http\FormRequest;

class ListErrorLogsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'page'       => ['nullable', 'integer', 'min:1'],
            'per_page'   => ['nullable', 'integer', 'min:1', 'max:100'],
            'source'     => ['nullable', 'string', 'max:200'],
            'source_id'  => ['nullable', 'integer'],
            'from_date'  => ['nullable', 'date_format:Y-m-d'],
            'to_date'    => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from_date'],
        ];
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        return $this->only(['source', 'source_id', 'from_date', 'to_date']);
    }

    public function perPage(): int
    {
        return (int) ($this->query('per_page', 20));
    }
}
