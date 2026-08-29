<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Models\ShiftSwapRequest;
use App\Services\ShiftSwapService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class StoreShiftSwapRequest extends FormRequest
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
            'type' => ['required', Rule::in(array_keys(ShiftSwapRequest::typeLabels()))],
            'partner_id' => ['required', 'integer', 'exists:employees,id'],
            'requester_date' => ['required', 'date'],
            'partner_date' => ['nullable', 'required_unless:type,'.ShiftSwapRequest::TYPE_COVER, 'date'],
            'reason' => ['nullable', 'string', 'max:1000'],
            // Wajib: pengajuan tukar jadwal mengubah siapa yang bertanggung jawab pada
            // suatu hari kerja, dan rekan maupun HR memutuskannya tanpa bertemu orangnya
            // — bukti kesepakatannya harus ikut, bukan opsional.
            'attachment' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:'.(ShiftSwapRequest::ATTACHMENT_MAX_MB * 1024),
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

                $requester = $this->user()->employee;
                $partner = Employee::find($this->integer('partner_id'));

                if (! $partner) {
                    return;
                }

                $conflicts = app(ShiftSwapService::class)->conflicts(
                    $this->string('type')->toString(),
                    $requester,
                    Carbon::parse($this->date('requester_date')),
                    $partner,
                    $this->date('partner_date') ? Carbon::parse($this->date('partner_date')) : null,
                );

                foreach ($conflicts as $conflict) {
                    $validator->errors()->add('partner_id', $conflict);
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
            'attachment.required' => 'Bukti gambar wajib dilampirkan, misalnya tangkapan layar kesepakatan dengan rekan.',
            'attachment.image' => 'Bukti harus berupa gambar (JPG, PNG, atau WEBP).',
            'attachment.mimes' => 'Bukti harus berupa gambar (JPG, PNG, atau WEBP).',
            'attachment.max' => 'Ukuran bukti maksimal '.ShiftSwapRequest::ATTACHMENT_MAX_MB.' MB.',
        ];
    }

    public function attributes(): array
    {
        return [
            'partner_id' => 'rekan',
            'requester_date' => 'tanggal Anda',
            'partner_date' => 'tanggal rekan',
            'attachment' => 'bukti',
        ];
    }
}
