<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BranchRequest extends FormRequest
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
        $branchId = $this->route('branch')?->id;

        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('branches', 'code')->ignore($branchId)],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(['office', 'warehouse'])],
            'city' => ['nullable', 'string', 'max:120'],
            'province' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
            // Absen lewat aplikasi HP (opsional, diaktifkan per lokasi).
            'mobile_attendance_enabled' => ['sometimes', 'boolean'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude', 'required_if:mobile_attendance_enabled,1'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude', 'required_if:mobile_attendance_enabled,1'],
            'geofence_radius_m' => ['nullable', 'integer', 'min:20', 'max:5000'],
            'geofence_strict' => ['sometimes', 'boolean'],
            'wifi_bssids' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'latitude.required_if' => 'Isi titik koordinat kantor sebelum mengaktifkan absen lewat aplikasi.',
            'longitude.required_if' => 'Isi titik koordinat kantor sebelum mengaktifkan absen lewat aplikasi.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'latitude' => 'latitude',
            'longitude' => 'longitude',
            'geofence_radius_m' => 'radius',
            'wifi_bssids' => 'BSSID Wi-Fi',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            ...$this->safe()->except(['is_active', 'mobile_attendance_enabled', 'geofence_strict', 'wifi_bssids', 'geofence_radius_m']),
            'is_active' => $this->boolean('is_active'),
            'mobile_attendance_enabled' => $this->boolean('mobile_attendance_enabled'),
            'geofence_strict' => $this->boolean('geofence_strict'),
            'geofence_radius_m' => $this->integer('geofence_radius_m') ?: 100,
            // Satu BSSID per baris (atau dipisah koma), mis. a4:2b:b0:12:34:56.
            'wifi_bssids' => collect(preg_split('/[\s,;]+/', (string) $this->input('wifi_bssids')))
                ->map(fn ($v) => strtolower(str_replace('-', ':', trim($v))))
                ->filter(fn ($v) => preg_match('/^([0-9a-f]{2}:){5}[0-9a-f]{2}$/', $v))
                ->unique()
                ->values()
                ->all() ?: null,
        ];
    }
}
