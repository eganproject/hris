<?php

namespace App\Http\Requests;

use App\Models\AttendanceCorrection;
use App\Support\UploadMessages;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->employee;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'work_date' => ['required', 'date', 'before_or_equal:today'],
            'requested_clock_in' => ['nullable', 'date_format:H:i'],
            'requested_clock_out' => ['nullable', 'date_format:H:i'],
            'reason' => ['required', 'string', 'max:1000'],
            // Wajib: koreksi absensi mengubah jam kerja yang sudah tercatat mesin, dan
            // yang memutuskannya tidak menyaksikan orangnya hadir. Tanpa bukti, satu-
            // satunya dasar persetujuan adalah kalimat pengajunya sendiri.
            'attachment' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:'.(AttendanceCorrection::ATTACHMENT_MAX_MB * 1024),
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if (! $this->filled('requested_clock_in') && ! $this->filled('requested_clock_out')) {
                    $validator->errors()->add('requested_clock_in', 'Isi minimal jam masuk atau jam pulang yang benar.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...UploadMessages::photo('attachment', AttendanceCorrection::ATTACHMENT_MAX_MB, 'Bukti'),
            'attachment.required' => 'Bukti gambar wajib dilampirkan — foto diri Anda dari rekaman CCTV, atau tangkapan layar bukti aktivitas kerja bila hari itu WFH atau dinas luar.',
        ];
    }

    public function attributes(): array
    {
        return [
            'work_date' => 'tanggal',
            'requested_clock_in' => 'jam masuk',
            'requested_clock_out' => 'jam pulang',
            'attachment' => 'bukti',
        ];
    }
}
