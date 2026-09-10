<?php

namespace App\Http\Controllers;

use App\Models\AttendanceCorrection;
use App\Models\User;
use App\Support\DataScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Menyajikan bukti gambar pengajuan koreksi absensi. Berkasnya ada di disk privat,
 * jadi satu-satunya jalan keluar adalah rute ini — dan tiap permintaan diperiksa
 * siapa peminta-nya.
 *
 * Tanpa parameter berkasnya ditampilkan inline supaya peninjau bisa langsung melihat
 * gambarnya di tab baru; dengan ?download=1 berkasnya diunduh.
 */
class AttendanceCorrectionAttachmentController extends Controller
{
    public function __invoke(Request $request, AttendanceCorrection $correction): StreamedResponse
    {
        abort_unless($correction->hasAttachment(), 404);
        abort_unless($this->mayView($request->user(), $correction), 403);

        $disk = Storage::disk(AttendanceCorrection::ATTACHMENT_DISK);

        // Baris masih menyimpan path meski berkasnya sudah hilang dari disk (mis.
        // dipangkas manual) — jangan sampai berujung error 500.
        abort_unless($disk->exists($correction->attachment_path), 404, 'Berkas bukti tidak ditemukan.');

        $headers = ['Content-Type' => $correction->attachment_mime ?: 'application/octet-stream'];

        return $request->boolean('download')
            ? $disk->download($correction->attachment_path, $correction->attachment_name, $headers)
            : $disk->response($correction->attachment_path, $correction->attachment_name, $headers);
    }

    /**
     * Dua pihak yang berkepentingan: yang mengajukan, dan peninjau yang halaman
     * Koreksi Absensi-nya memang memuat orang itu.
     *
     * Cakupannya diukur dengan DataScope yang sama seperti halaman peninjauannya
     * (forTeam) — bukan aturan tersendiri di sini. Atasan langsung ikut tercakup
     * lewat garis atasan itu, sepanjang ia memang memegang menu Koreksi Absensi;
     * yang tidak memegangnya juga tidak menerima tembusan notifikasinya.
     */
    private function mayView(?User $user, AttendanceCorrection $correction): bool
    {
        if (! $user) {
            return false;
        }

        $correction->loadMissing('employee');

        if ($correction->employee?->user_id === $user->id) {
            return true;
        }

        return $user->can('corrections.view')
            && DataScope::forTeam($user)->allows($correction->employee);
    }
}
