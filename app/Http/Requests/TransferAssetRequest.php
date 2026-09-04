<?php

namespace App\Http\Requests;

use App\Support\DataScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransferAssetRequest extends FormRequest
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
        $scope = DataScope::forAssets($this->user());

        return [
            // Tujuan pemindahan harus tetap berada di dalam cakupan pemindahnya —
            // kalau tidak, aset itu lenyap dari layarnya sendiri begitu tombolnya
            // ditekan, dan tidak ada lagi yang bisa mengembalikannya.
            'current_branch_id' => ['required', Rule::in($scope->branches()->pluck('id')->all())],
            'department_id' => ['nullable', Rule::in($scope->departments()->pluck('id')->all())],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_branch_id.required' => 'Pilih lokasi tujuannya.',
            'current_branch_id.in' => 'Lokasi tujuan berada di luar cakupan akses Anda.',
            'department_id.in' => 'Divisi tujuan berada di luar cakupan akses Anda.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'current_branch_id' => 'lokasi tujuan',
            'department_id' => 'divisi tujuan',
        ];
    }
}
