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
        $employee = $this->route('employee');
        $employeeId = $employee?->id;
        $contractId = $employee?->currentContract?->id;
        $userId = $employee?->user_id;
        $requiresLoginPassword = $this->isMethod('post') && $this->filled('email') && ! $userId;

        // Setting the status to "Nonaktif" (from a not-already-inactive state, or on
        // a brand-new employee) triggers the exit flow, so the reason & date become
        // required and are processed together with the save.
        $isClosingExit = $this->input('employment_status') === 'inactive'
            && (! $employee || ! $employee->isInactive());
        $exitJoinDate = $employee?->join_date?->format('Y-m-d') ?: $this->input('join_date');

        return [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'job_position_id' => ['required', 'integer', 'exists:job_positions,id'],
            'manager_id' => ['nullable', 'integer', 'exists:employees,id', Rule::notIn([$employeeId])],
            'machine_pins' => ['nullable', 'array'],
            'machine_pins.*.device_id' => ['nullable', 'integer', 'exists:devices,id'],
            'machine_pins.*.machine_user_id' => ['nullable', 'string', 'max:50'],
            // employee_number is not accepted here: the system generates it (COK…).
            'photo' =>['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=300,min_height=300,max_width=3000,max_height=3000'],
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
            'contract_end_date' => [
                Rule::requiredIf(fn () => $this->input('contract_type') !== 'PKWTT'),
                'nullable',
                'date',
                'after_or_equal:contract_start_date',
            ],
            'contract_status' => ['required', 'string', Rule::in(array_keys(EmployeeContract::statusLabels()))],
            'contract_notes' => ['nullable', 'string', 'max:1000'],
            'exit_reason' => [Rule::requiredIf($isClosingExit), 'nullable', 'string', Rule::in(array_keys(Employee::exitReasonLabels()))],
            'exit_date' => array_filter([
                $isClosingExit ? 'required' : 'nullable',
                'date',
                'before_or_equal:today',
                $exitJoinDate ? 'after_or_equal:'.$exitJoinDate : null,
            ]),
            'exit_notes' => ['nullable', 'string', 'max:1000'],
            'login_password' => [Rule::requiredIf($requiresLoginPassword), 'nullable', 'string', 'min:8'],
            'login_role_id' => ['nullable', 'integer', Rule::exists('roles', 'id')->where('guard_name', 'web')],
            'leave_balance' => ['nullable', 'array'],
            'leave_balance.*' => ['nullable', 'integer', 'min:0', 'max:365'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contract_end_date.required' => 'Tanggal selesai kontrak wajib diisi untuk jenis kontrak selain PKWTT.',
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

                // Validate the fingerprint PIN rows: no duplicate machine within the
                // form, and no PIN already used by another employee on that machine.
                $currentId = $this->route('employee')?->id;
                $seenDevices = [];
                $validPins = 0;

                foreach ((array) $this->input('machine_pins', []) as $index => $row) {
                    $pin = trim((string) ($row['machine_user_id'] ?? ''));

                    if ($pin === '') {
                        continue; // empty rows are ignored on save
                    }

                    $validPins++;
                    $deviceId = ($row['device_id'] ?? null) ?: null;
                    $deviceKey = (int) $deviceId;

                    if (in_array($deviceKey, $seenDevices, true)) {
                        $validator->errors()->add("machine_pins.$index.device_id", 'Mesin ini sudah dipilih di baris lain.');

                        continue;
                    }
                    $seenDevices[] = $deviceKey;

                    $conflict = DB::table('employee_devices')
                        ->when($deviceId, fn ($q) => $q->where('device_id', $deviceId), fn ($q) => $q->whereNull('device_id'))
                        ->where('machine_user_id', $pin)
                        ->when($currentId, fn ($q) => $q->where('employee_id', '!=', $currentId))
                        ->exists();

                    if ($conflict) {
                        $validator->errors()->add("machine_pins.$index.machine_user_id", 'PIN ini sudah dipakai karyawan lain pada mesin tersebut.');
                    }
                }

                if ($validPins === 0) {
                    $validator->errors()->add('machine_pins', 'Minimal satu PIN mesin absensi wajib diisi.');
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
