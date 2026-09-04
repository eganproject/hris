<?php

namespace App\Http\Requests;

use App\Actions\Assets\ReturnAsset;
use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReturnAssetRequest extends FormRequest
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
            'condition_in' => ['required', Rule::in(AssetCondition::values())],
            // Status berikutnya boleh ditentukan petugas, tapi hanya di antara yang
            // masuk akal sebagai hasil sebuah pengembalian.
            'next_status' => [
                'nullable',
                Rule::in(array_map(fn (AssetStatus $s) => $s->value, ReturnAsset::OUTCOMES)),
            ],
            'returned_at' => ['nullable', 'date', 'before_or_equal:now'],
            'return_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'condition_in.required' => 'Catat dulu kondisi barangnya saat diterima kembali.',
            'returned_at.before_or_equal' => 'Tanggal pengembalian tidak boleh di masa depan.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'condition_in' => 'kondisi saat kembali',
            'next_status' => 'status setelah kembali',
            'returned_at' => 'tanggal pengembalian',
        ];
    }
}
