<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class JobPositionRequest extends FormRequest
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
        $jobPositionId = $this->route('jobPosition')?->id;

        return [
            'departments' => ['required', 'array', 'min:1'],
            'departments.*' => ['integer', 'distinct', Rule::exists('departments', 'id')->where('is_active', true)],
            'code' => ['required', 'string', 'max:50', Rule::unique('job_positions', 'code')->ignore($jobPositionId)],
            'name' => ['required', 'string', 'max:255'],
            'level' => ['nullable', 'string', 'max:100'],
            'default_role_id' => ['nullable', 'integer', Rule::exists('roles', 'id')->where('guard_name', 'web')],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            ...$this->safe()->except(['departments', 'is_active']),
            'is_active' => $this->boolean('is_active'),
        ];
    }

    /**
     * @return array<int, int>
     */
    public function departmentIds(): array
    {
        return collect($this->validated('departments'))
            ->map(fn (int|string $departmentId): int => (int) $departmentId)
            ->values()
            ->all();
    }
}
