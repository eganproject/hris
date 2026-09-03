<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttendancePunchRequest extends FormRequest
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
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'work_date' => ['required', 'date'],
            // Jam masuk tanpa jam pulang adalah keadaan yang sah dan sudah didukung
            // penuh oleh AttendanceResolver: orangnya masih bekerja, atau lupa absen
            // pulang — persis seperti yang dikirim mesin sidik jari. Dulu di sini ada
            // required_with:clock_in, sehingga membetulkan jam masuk saja selalu
            // ditolak; halaman harian tidak menampilkan galat apa pun, jadi
            // penolakannya terlihat seperti "tersimpan tapi datanya tidak berubah".
            //
            // Kebalikannya yang memang tidak masuk akal: jam pulang tanpa jam masuk
            // menghasilkan baris Alfa yang tetap menyimpan jam pulang.
            'clock_in' => ['nullable', 'date_format:H:i', 'required_with:clock_out'],
            'clock_out' => ['nullable', 'date_format:H:i'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'employee_id' => 'karyawan',
            'work_date' => 'tanggal',
            'clock_in' => 'jam masuk',
            'clock_out' => 'jam pulang',
        ];
    }
}
