<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Models\User;
use App\Support\DataScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Menyajikan lampiran pengajuan cuti. Berkasnya ada di disk privat, jadi satu-satunya
 * jalan keluar adalah rute ini — dan tiap permintaan diperiksa siapa peminta-nya.
 *
 * Tanpa parameter, berkas ditampilkan inline supaya peninjau bisa langsung melihat
 * gambar atau PDF di tab baru; dengan ?download=1 berkasnya diunduh.
 */
class LeaveAttachmentController extends Controller
{
    public function __invoke(Request $request, LeaveRequest $leaveRequest): StreamedResponse
    {
        abort_unless($leaveRequest->hasAttachment(), 404);
        abort_unless($this->mayView($request->user(), $leaveRequest), 403);

        $disk = Storage::disk(LeaveRequest::ATTACHMENT_DISK);

        // Baris masih menyimpan path meski berkasnya sudah hilang dari disk (mis.
        // dipangkas manual) — jangan sampai berujung error 500.
        abort_unless($disk->exists($leaveRequest->attachment_path), 404, 'Berkas lampiran tidak ditemukan.');

        $headers = ['Content-Type' => $leaveRequest->attachment_mime ?: 'application/octet-stream'];

        return $request->boolean('download')
            ? $disk->download($leaveRequest->attachment_path, $leaveRequest->attachment_name, $headers)
            : $disk->response($leaveRequest->attachment_path, $leaveRequest->attachment_name, $headers);
    }

    /**
     * Tiga pihak yang berkepentingan: yang mengajukan, atasan yang ditunjuk untuk
     * memutuskan, dan HR yang berwenang atas cakupan data karyawan tersebut.
     */
    private function mayView(?User $user, LeaveRequest $leaveRequest): bool
    {
        if (! $user) {
            return false;
        }

        $leaveRequest->loadMissing('employee');

        if ($leaveRequest->employee?->user_id === $user->id) {
            return true;
        }

        if ($leaveRequest->supervisor_id !== null && $leaveRequest->supervisor_id === $user->employee?->id) {
            return true;
        }

        return $user->can('leave.view')
            && DataScope::forAttendance($user)->allows($leaveRequest->employee);
    }
}
