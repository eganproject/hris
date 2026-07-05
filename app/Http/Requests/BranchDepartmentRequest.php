<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BranchDepartmentRequest extends FormRequest
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
            'departments' => ['nullable', 'array'],
            'departments.*' => ['integer', Rule::exists('departments', 'id')->where('is_active', true)],
            'primary_department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('is_active', true)],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $departmentIds = collect($this->input('departments', []))
                    ->map(fn (mixed $departmentId): int => (int) $departmentId);
                $primaryDepartmentId = $this->integer('primary_department_id');

                if ($primaryDepartmentId && ! $departmentIds->contains($primaryDepartmentId)) {
                    $validator->errors()->add('primary_department_id', 'Divisi utama harus termasuk dalam divisi yang dipilih.');
                }
            },
        ];
    }
}
