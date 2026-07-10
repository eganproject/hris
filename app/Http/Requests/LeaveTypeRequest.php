<?php

namespace App\Http\Requests;

use App\Enums\AttendanceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeaveTypeRequest extends FormRequest
{
    /** Attendance statuses a leave type may resolve to. */
    public const ALLOWED_STATUSES = [
        AttendanceStatus::Leave->value => 'Cuti / Izin',
        AttendanceStatus::Sick->value => 'Sakit',
        AttendanceStatus::BusinessTrip->value => 'Dinas Luar',
        AttendanceStatus::Wfh->value => 'WFH',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_paid' => $this->boolean('is_paid'),
            'counts_against_balance' => $this->boolean('counts_against_balance'),
            'is_active' => $this->boolean('is_active'),
            'default_quota_days' => $this->boolean('counts_against_balance') ? $this->input('default_quota_days') : null,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $id = $this->route('leaveType')?->id;

        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('leave_types', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:100'],
            'attendance_status' => ['required', Rule::in(array_keys(self::ALLOWED_STATUSES))],
            'is_paid' => ['required', 'boolean'],
            'counts_against_balance' => ['required', 'boolean'],
            'default_quota_days' => ['nullable', 'required_if:counts_against_balance,true', 'integer', 'min:0', 'max:365'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'default_quota_days.required_if' => 'Kuota default wajib diisi untuk jenis cuti yang memakai kuota.',
            'code.unique' => 'Kode jenis cuti ini sudah dipakai.',
        ];
    }
}
