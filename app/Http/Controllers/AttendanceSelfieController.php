<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\User;
use App\Support\DataScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Menyajikan foto selfie absen mandiri. Berkasnya ada di disk privat, jadi satu-satunya
 * jalan keluar adalah rute ini — dan tiap permintaan diperiksa siapa peminta-nya.
 *
 * Sebelumnya foto ini tersimpan di disk publik dan bisa dibuka siapa pun yang tahu
 * URL-nya, tanpa login. Foto wajah karyawan beserta koordinat tempat ia bekerja
 * setidaknya sepadan dengan lampiran surat cuti, yang sejak awal dijaga begini.
 */
class AttendanceSelfieController extends Controller
{
    public function __invoke(Request $request, Attendance $attendance, string $side): StreamedResponse
    {
        abort_unless(in_array($side, ['in', 'out'], true), 404);

        $path = $attendance->{"clock_{$side}_photo_path"};

        abort_unless($path, 404);
        abort_unless($this->mayView($request->user(), $attendance), 403);

        $disk = Storage::disk(Attendance::SELFIE_DISK);

        // Foto lama dipangkas otomatis setelah masa simpan; barisnya bisa saja masih
        // menunjuk berkas yang sudah tidak ada.
        abort_unless($disk->exists($path), 404, 'Foto absensi tidak ditemukan.');

        $name = $attendance->work_date->format('Y-m-d').'-'.($side === 'in' ? 'masuk' : 'pulang').'.jpg';

        return $request->boolean('download')
            ? $disk->download($path, $name)
            : $disk->response($path, $name, ['Content-Type' => 'image/jpeg']);
    }

    /**
     * Karyawan yang bersangkutan, atau petugas yang boleh melihat papan absensi
     * harian sepanjang karyawan itu ada dalam cakupan datanya.
     */
    private function mayView(?User $user, Attendance $attendance): bool
    {
        if (! $user) {
            return false;
        }

        $attendance->loadMissing('employee');

        if ($attendance->employee?->user_id === $user->id) {
            return true;
        }

        return $user->can('attendance-daily.view')
            && DataScope::forAttendance($user)->allows($attendance->employee);
    }
}
