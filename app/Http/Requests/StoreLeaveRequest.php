<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Support\LeaveGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreLeaveRequest extends FormRequest
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
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $employee = Employee::find($this->integer('employee_id'));

            if ($employee && $employee->isInactive()) {
                $validator->errors()->add('employee_id', 'Karyawan tersebut sudah nonaktif — tidak bisa diajukan cuti/izin.');

                return;
            }

            if ($employee) {
                LeaveGuard::check($validator, $employee, $this->integer('leave_type_id'), $this->input('start_date'), $this->input('end_date'));
            }
        }];
    }

    public function attributes(): array
    {
        return [
            'employee_id' => 'karyawan',
            'leave_type_id' => 'jenis cuti',
            'start_date' => 'tanggal mulai',
            'end_date' => 'tanggal selesai',
        ];
    }
}
