<?php

namespace App\Http\Requests;

use App\Enums\AssetCondition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignAssetRequest extends FormRequest
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
            // Hanya karyawan aktif. Daftarnya sengaja tidak dipersempit ke cabang aset:
            // penyerahan lintas cabang memang terjadi, dan yang menjaga siapa boleh
            // menyentuh aset ini adalah cakupan asetnya sendiri, bukan daftar namanya.
            'employee_id' => ['required', Rule::exists('employees', 'id')->where('employment_status', 'active')],
            'assigned_at' => ['nullable', 'date', 'before_or_equal:now'],
            'expected_return_at' => ['nullable', 'date', 'after_or_equal:today'],
            'condition_out' => [
                'required',
                Rule::in(array_map(fn (AssetCondition $c) => $c->value, AssetCondition::SERVICEABLE)),
            ],
            'purpose' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employee_id.required' => 'Pilih karyawan yang menerima asetnya.',
            'employee_id.exists' => 'Karyawan itu tidak ditemukan atau sudah tidak aktif.',
            'assigned_at.before_or_equal' => 'Tanggal penyerahan tidak boleh di masa depan.',
            'expected_return_at.after_or_equal' => 'Target pengembalian tidak boleh sudah lewat.',
            'condition_out.in' => 'Aset yang rusak atau tidak layak tidak boleh diserahkan.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'employee_id' => 'karyawan',
            'assigned_at' => 'tanggal penyerahan',
            'expected_return_at' => 'target pengembalian',
            'condition_out' => 'kondisi saat diserahkan',
            'purpose' => 'keperluan',
        ];
    }
}
