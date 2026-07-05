<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Models\EmployeeContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class EmployeeRequest extends FormRequest
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
        $employeeId = $this->route('employee')?->id;
        $contractId = $this->route('employee')?->currentContract?->id;
        $userId = $this->route('employee')?->user_id;
        $requiresLoginPassword = $this->isMethod('post') && $this->filled('email') && ! $userId;

        return [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'job_position_id' => ['required', 'integer', 'exists:job_positions,id'],
            'employee_number' => ['required', 'string', 'max:50', Rule::unique('employees', 'employee_number')->ignore($employeeId)],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=300,min_height=300,max_width=3000,max_height=3000'],
            'full_name' => ['required', 'string', 'max:255'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('employees', 'email')->ignore($employeeId),
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'identity_number' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'join_date' => ['required', 'date'],
            'employment_status' => ['required', 'string', Rule::in(array_keys(Employee::employmentStatusLabels()))],
            'address' => ['nullable', 'string', 'max:1000'],
            'contract_number' => ['required', 'string', 'max:100', Rule::unique('employee_contracts', 'contract_number')->ignore($contractId)],
            'contract_type' => ['required', 'string', Rule::in(['PKWT', 'PKWTT', 'Probation', 'Internship'])],
            'contract_start_date' => ['required', 'date'],
            'contract_end_date' => ['nullable', 'date', 'after_or_equal:contract_start_date'],
            'contract_status' => ['required', 'string', Rule::in(array_keys(EmployeeContract::statusLabels()))],
            'contract_notes' => ['nullable', 'string', 'max:1000'],
            'login_password' => [Rule::requiredIf($requiresLoginPassword), 'nullable', 'string', 'min:8'],
            'login_role_id' => ['nullable', 'integer', Rule::exists('roles', 'id')->where('guard_name', 'web')],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($this->route('employee')?->user_id && ! $this->filled('email')) {
                    $validator->errors()->add('email', 'Email karyawan wajib diisi karena akun login sudah aktif.');

                    return;
                }

                $branchId = $this->integer('branch_id');
                $departmentId = $this->integer('department_id');
                $jobPositionId = $this->integer('job_position_id');

                $isDepartmentAvailableAtBranch = DB::table('branch_department')
                    ->where('branch_id', $branchId)
                    ->where('department_id', $departmentId)
                    ->where('is_active', true)
                    ->exists();

                if (! $isDepartmentAvailableAtBranch) {
                    $validator->errors()->add('department_id', 'Divisi tidak tersedia pada lokasi kerja yang dipilih.');
                }

                $jobPositionAvailableForDepartment = DB::table('department_job_position')
                    ->join('job_positions', 'job_positions.id', '=', 'department_job_position.job_position_id')
                    ->where('department_job_position.department_id', $departmentId)
                    ->where('department_job_position.job_position_id', $jobPositionId)
                    ->where('department_job_position.is_active', true)
                    ->where('job_positions.is_active', true)
                    ->exists();

                if (! $jobPositionAvailableForDepartment) {
                    $validator->errors()->add('job_position_id', 'Jabatan tidak sesuai dengan divisi yang dipilih.');
                }
            },
        ];
    }
}
