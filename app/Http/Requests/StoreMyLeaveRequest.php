<?php

namespace App\Http\Requests;

use App\Enums\AttendanceStatus;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Support\LeaveGuard;
use App\Support\UploadMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMyLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Only an employee-linked account can request leave for themselves.
        return (bool) $this->user()?->employee;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:1000'],
            // Bukti pendukung: surat sakit, surat tugas, dsb. Untuk pengajuan sakit
            // surat keterangannya WAJIB — lihat requiresAttachment().
            'attachment' => [
                Rule::requiredIf(fn () => $this->requiresAttachment()),
                'file',
                'mimes:jpg,jpeg,png,webp,pdf',
                'max:'.(LeaveRequest::ATTACHMENT_MAX_MB * 1024),
            ],
        ];
    }

    /**
     * Pengajuan sakit wajib melampirkan surat keterangan.
     *
     * Yang menentukan bukan nama jenis cutinya, melainkan status absensi yang
     * dipetakan padanya di menu Jenis Cuti — jadi perusahaan yang memakai lebih dari
     * satu jenis sakit (mis. "Sakit" dan "Sakit Berkepanjangan") tercakup tanpa
     * perlu menambah daftar nama di sini.
     */
    private function requiresAttachment(): bool
    {
        $type = LeaveType::query()->find($this->integer('leave_type_id'));

        return $type?->attendance_status === AttendanceStatus::Sick;
    }

    public function messages(): array
    {
        return [
            ...UploadMessages::attachment('attachment', LeaveRequest::ATTACHMENT_MAX_MB),
            'attachment.required' => 'Foto atau scan surat keterangan sakit wajib dilampirkan untuk pengajuan sakit.',
        ];
    }

    public function attributes(): array
    {
        return [
            'leave_type_id' => 'jenis cuti',
            'start_date' => 'tanggal mulai',
            'end_date' => 'tanggal selesai',
            'attachment' => 'lampiran',
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

            $employee = $this->user()->employee;

            LeaveGuard::check($validator, $employee, $this->integer('leave_type_id'), $this->input('start_date'), $this->input('end_date'));
        }];
    }
}
