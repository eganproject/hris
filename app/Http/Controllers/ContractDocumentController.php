<?php

namespace App\Http\Controllers;

use App\Models\EmployeeContract;
use App\Models\User;
use App\Support\DataScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Menyajikan dokumen kontrak. Berkasnya ada di disk privat, jadi satu-satunya jalan
 * keluar adalah rute ini — dan tiap permintaan diperiksa siapa peminta-nya.
 *
 * Tanpa parameter, berkas ditampilkan inline supaya bisa langsung dibaca di tab
 * baru; dengan ?download=1 berkasnya diunduh.
 */
class ContractDocumentController extends Controller
{
    public function __invoke(Request $request, EmployeeContract $contract): StreamedResponse
    {
        abort_unless($contract->hasDocument(), 404);
        abort_unless($this->mayView($request->user(), $contract), 403);

        $disk = Storage::disk(EmployeeContract::DOCUMENT_DISK);

        // Baris masih menyimpan path meski berkasnya sudah hilang dari disk (mis.
        // dipangkas manual) — jangan sampai berujung error 500.
        abort_unless($disk->exists($contract->document_path), 404, 'Berkas dokumen kontrak tidak ditemukan.');

        $headers = ['Content-Type' => $contract->document_mime ?: 'application/octet-stream'];

        return $request->boolean('download')
            ? $disk->download($contract->document_path, $contract->document_name, $headers)
            : $disk->response($contract->document_path, $contract->document_name, $headers);
    }

    /**
     * Dua pihak yang berkepentingan: karyawan yang bersangkutan, dan HR yang
     * berwenang atas cakupan data karyawan tersebut.
     */
    private function mayView(?User $user, EmployeeContract $contract): bool
    {
        if (! $user) {
            return false;
        }

        $contract->loadMissing('employee');

        if ($contract->employee?->user_id === $user->id) {
            return true;
        }

        return $user->can('employees.view')
            && DataScope::forEmployees($user)->allows($contract->employee);
    }
}
