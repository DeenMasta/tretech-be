<?php

namespace App\Http\Requests\Api\V1\MasterData;

use App\Models\InstrumentSet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInstrumentSetRequest extends FormRequest
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
        /** @var InstrumentSet|int|string|null $instrumentSet */
        $instrumentSet = $this->route('instrumentSet');
        $instrumentSetId = $instrumentSet instanceof InstrumentSet ? $instrumentSet->id : $instrumentSet;

        return [
            'set_code' => ['nullable', 'string', 'max:255', Rule::unique('instrument_sets', 'set_code')->ignore($instrumentSetId)],
            'set_name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
