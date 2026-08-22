<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Absen mandiri (WFH / dinas luar): foto selfie yang baru diambil plus koordinat
 * dari perangkat. Jam tetap diambil dari server, bukan dari klien.
 */
class SelfAttendanceRequest extends FormRequest
{
    /**
     * Kantong error sendiri supaya kegagalan validasi di sini tidak ikut membuka
     * dialog "Ajukan Koreksi" yang memakai kantong default.
     */
    protected $errorBag = 'selfie';

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
            // Fotonya berasal dari canvas kamera (JPEG hasil kompresi), jadi 2 MB
            // sudah sangat longgar untuk gambar 720px.
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0', 'max:100000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'photo' => 'foto selfie',
            'latitude' => 'lintang lokasi',
            'longitude' => 'bujur lokasi',
            'accuracy' => 'akurasi lokasi',
        ];
    }

    public function messages(): array
    {
        return [
            'photo.required' => 'Ambil foto selfie dulu sebelum mengirim absen.',
            'latitude.required' => 'Lokasi tidak terbaca. Izinkan akses lokasi di browser Anda, lalu coba lagi.',
            'longitude.required' => 'Lokasi tidak terbaca. Izinkan akses lokasi di browser Anda, lalu coba lagi.',
        ];
    }
}
