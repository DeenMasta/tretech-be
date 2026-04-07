<?php

namespace App\Http\Requests\Api\V1\HoldingArea;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignLotRequest extends FormRequest
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
        // The lot being assigned is bound in the route as {lot}
        $lotId = $this->route('lot')?->id;

        return [
            'lot_number' => [
                'required',
                'string',
                'max:100',
                // Globally unique, excluding the current lot itself (it owns its HOLD-* number)
                Rule::unique('lots', 'lot_number')->ignore($lotId),
            ],
            'resolution_reason' => ['required', 'string', 'max:500'],
            'remarks'           => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lot_number.unique'           => 'This lot number is already assigned to another unit.',
            'resolution_reason.required'  => 'A resolution reason is mandatory when assigning a lot number.',
        ];
    }
}
