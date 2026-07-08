<?php

namespace App\Http\Requests;

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

    public function attributes(): array
    {
        return [
            'work_date' => 'tanggal',
            'requested_clock_in' => 'jam masuk',
            'requested_clock_out' => 'jam pulang',
        ];
    }
}
