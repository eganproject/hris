<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleOverrideRequest extends FormRequest
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
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'work_date' => ['required', 'date'],
            'is_day_off' => ['sometimes', 'boolean'],
            'shift_id' => ['nullable', 'required_without:is_day_off', 'integer', 'exists:shifts,id'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'employee_id' => 'karyawan',
            'work_date' => 'tanggal',
            'shift_id' => 'shift',
        ];
    }
}
