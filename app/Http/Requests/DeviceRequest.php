<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeviceRequest extends FormRequest
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
        $deviceId = $this->route('device')?->id;

        return [
            'serial_number' => ['required', 'string', 'max:100', Rule::unique('devices', 'serial_number')->ignore($deviceId)],
            'name' => ['required', 'string', 'max:255'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'serial_number' => $this->string('serial_number')->toString(),
            'name' => $this->string('name')->toString(),
            'branch_id' => $this->integer('branch_id') ?: null,
            'timezone' => $this->string('timezone')->toString() ?: 'Asia/Jakarta',
            'is_active' => $this->boolean('is_active'),
        ];
    }
}
