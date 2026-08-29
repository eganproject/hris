<?php

namespace App\Http\Controllers;

use App\Models\ShiftSwapRequest;
use App\Models\User;
use App\Support\DataScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Menyajikan bukti gambar pengajuan tukar jadwal. Berkasnya ada di disk privat, jadi
 * satu-satunya jalan keluar adalah rute ini — dan tiap permintaan diperiksa siapa
 * peminta-nya.
 *
 * Tanpa parameter berkasnya ditampilkan inline supaya peninjau bisa langsung melihat
 * gambarnya di tab baru; dengan ?download=1 berkasnya diunduh.
 */
class ShiftSwapAttachmentController extends Controller
{
    public function __invoke(Request $request, ShiftSwapRequest $swap): StreamedResponse
    {
        abort_unless($swap->hasAttachment(), 404);
        abort_unless($this->mayView($request->user(), $swap), 403);

        $disk = Storage::disk(ShiftSwapRequest::ATTACHMENT_DISK);

        // Baris masih menyimpan path meski berkasnya sudah hilang dari disk (mis.
        // dipangkas manual) — jangan sampai berujung error 500.
        abort_unless($disk->exists($swap->attachment_path), 404, 'Berkas bukti tidak ditemukan.');

        $headers = ['Content-Type' => $swap->attachment_mime ?: 'application/octet-stream'];

        return $request->boolean('download')
            ? $disk->download($swap->attachment_path, $swap->attachment_name, $headers)
            : $disk->response($swap->attachment_path, $swap->attachment_name, $headers);
    }

    /**
     * Tiga pihak yang berkepentingan: yang mengajukan, rekan yang dimintai persetujuan,
     * dan HR yang berwenang atas cakupan data karyawan tersebut.
     */
    private function mayView(?User $user, ShiftSwapRequest $swap): bool
    {
        if (! $user) {
            return false;
        }

        $employeeId = $user->employee?->id;

        if ($employeeId !== null && in_array($employeeId, [$swap->requester_id, $swap->partner_id], true)) {
            return true;
        }

        $swap->loadMissing('requester');

        return $user->can('swaps.view')
            && DataScope::forAttendance($user)->allows($swap->requester);
    }
}
