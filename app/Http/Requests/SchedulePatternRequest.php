<?php

namespace App\Http\Requests;

use App\Enums\SchedulePatternType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SchedulePatternRequest extends FormRequest
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
        $patternId = $this->route('schedulePattern')?->id;
        $isRotating = $this->input('type') === SchedulePatternType::Rotating->value;

        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('schedule_patterns', 'code')->ignore($patternId)],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(SchedulePatternType::options()))],
            'cycle_length' => [Rule::requiredIf($isRotating), 'nullable', 'integer', 'min:1', 'max:60'],
            'anchor_date' => [Rule::requiredIf($isRotating), 'nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
            'days' => ['array'],
            'days.*' => ['nullable', 'integer', 'exists:shifts,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'cycle_length' => 'panjang siklus',
            'anchor_date' => 'tanggal jangkar',
        ];
    }
}
