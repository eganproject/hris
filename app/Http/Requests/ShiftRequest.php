<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $shiftId = $this->route('shift')?->id;

        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('shifts', 'code')->ignore($shiftId)],
            'name' => ['required', 'string', 'max:255'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'break_minutes' => ['required', 'integer', 'min:0', 'max:480'],
            'late_tolerance_minutes' => ['nullable', 'integer', 'min:0', 'max:240'],
            'early_leave_tolerance_minutes' => ['nullable', 'integer', 'min:0', 'max:240'],
            'overtime_starts_after_minutes' => ['nullable', 'integer', 'min:0', 'max:480'],
            'overtime_min_minutes' => ['nullable', 'integer', 'min:0', 'max:480'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            ...$this->safe()->except('is_active'),
            'is_active' => $this->boolean('is_active'),
            // Overnight is derived from the times so it can never be inconsistent.
            'crosses_midnight' => $this->input('end_time') <= $this->input('start_time'),
        ];
    }
}
