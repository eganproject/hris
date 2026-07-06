<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResignEmployeeRequest extends FormRequest
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
        $joinDate = $this->route('employee')?->join_date?->format('Y-m-d');

        return [
            'exit_reason' => ['required', 'string', Rule::in(array_keys(Employee::exitReasonLabels()))],
            'exit_date' => array_filter([
                'required',
                'date',
                'before_or_equal:today',
                $joinDate ? 'after_or_equal:'.$joinDate : null,
            ]),
            'exit_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'exit_reason' => 'alasan keluar',
            'exit_date' => 'tanggal keluar',
            'exit_notes' => 'catatan keluar',
        ];
    }

    /**
     * Flash the employee so the list page can re-open the exit modal with errors
     * when the process is triggered straight from the employee table.
     */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        if ($employee = $this->route('employee')) {
            session()->flash('resign_employee', ['id' => $employee->id, 'name' => $employee->full_name]);
        }

        parent::failedValidation($validator);
    }
}
