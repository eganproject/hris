<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_national' => $this->boolean('is_national'),
            'branch_id' => $this->boolean('is_national') ? null : $this->input('branch_id'),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $holidayId = $this->route('holiday')?->id;

        return [
            'date' => [
                'required',
                'date',
                Rule::unique('holidays', 'date')
                    ->where(fn ($query) => $query->where('branch_id', $this->input('branch_id')))
                    ->ignore($holidayId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'is_national' => ['required', 'boolean'],
            'branch_id' => ['nullable', 'required_if:is_national,false', 'integer', 'exists:branches,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.unique' => 'Sudah ada hari libur pada tanggal & lingkup yang sama.',
            'branch_id.required_if' => 'Pilih lokasi untuk hari libur non-nasional.',
        ];
    }
}
