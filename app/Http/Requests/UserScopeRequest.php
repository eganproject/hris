<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserScopeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the route already requires access-control.update
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'branches' => ['nullable', 'array'],
            'branches.*' => ['integer', 'exists:branches,id'],
            'departments' => ['nullable', 'array'],
            'departments.*' => ['integer', 'exists:departments,id'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', \Illuminate\Validation\Rule::exists('roles', 'name')->where('guard_name', 'web')],
        ];
    }
}
