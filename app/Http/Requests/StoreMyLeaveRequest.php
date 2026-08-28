<?php

namespace App\Http\Requests;

use App\Models\LeaveRequest;
use App\Support\LeaveGuard;
use Illuminate\Foundation\Http\FormRequest;
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
            // Bukti pendukung: surat sakit, surat tugas, dsb.
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:'.(LeaveRequest::ATTACHMENT_MAX_MB * 1024)],
        ];
    }

    public function messages(): array
    {
        return [
            'attachment.max' => 'Ukuran lampiran maksimal '.LeaveRequest::ATTACHMENT_MAX_MB.' MB.',
            'attachment.mimes' => 'Lampiran harus berupa gambar (JPG, PNG, WEBP) atau PDF.',
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
