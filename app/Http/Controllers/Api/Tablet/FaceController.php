<?php

namespace App\Http\Controllers\Api\Tablet;

use App\Http\Controllers\Api\Tablet\Concerns\LogsTabletActivity;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceTablet;
use App\Models\Employee;
use App\Models\EmployeeFaceProfile;
use App\Support\TabletPin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * HR mendaftarkan wajah karyawan dari menu HR di tablet (PIN HR tablet).
 * Yang disimpan: pola angka wajah dari 3 foto (depan, kiri, kanan), 1 foto
 * depan untuk dilihat HR, dan waktu persetujuan UU PDP.
 */
class FaceController extends Controller
{
    use LogsTabletActivity;

    /** Panjang embedding yang diterima (MobileFaceNet = 512, model lama 128/192). */
    private const DIMS = [128, 192, 512];

    public function store(Request $request): JsonResponse
    {
        /** @var AttendanceTablet $tablet */
        $tablet = $request->user();

        $data = $request->validate([
            'hr_pin' => ['required', 'string'],
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'model' => ['required', 'string', 'max:40'],
            'embeddings' => ['required', 'array', 'min:1', 'max:5'],
            'embeddings.*' => ['required', 'string', 'max:4096'],
            'consent' => ['accepted'],
            'photo' => ['required', 'image', 'max:4096'],
        ]);

        $this->checkHrPin($tablet, $data['hr_pin']);

        $employee = Employee::query()->findOrFail($data['employee_id']);
        abort_unless($employee->branch_id === $tablet->branch_id && $employee->employment_status === 'active', 422, 'Karyawan ini bukan karyawan aktif di lokasi kerja tablet ini.');

        $dim = null;
        foreach ($data['embeddings'] as $i => $b64) {
            $bytes = base64_decode($b64, true);
            $count = $bytes === false ? 0 : intdiv(strlen($bytes), 4);

            if ($bytes === false || strlen($bytes) % 4 !== 0 || ! in_array($count, self::DIMS, true) || ($dim !== null && $dim !== $count)) {
                throw ValidationException::withMessages(["embeddings.$i" => 'Data wajah tidak valid. Ulangi foto.']);
            }

            $dim = $count;
        }

        $profile = EmployeeFaceProfile::query()->firstOrNew(['employee_id' => $employee->id]);
        $old = $profile->photo_path;

        $profile->forceFill([
            'embeddings' => array_values($data['embeddings']),
            'embedding_model' => $data['model'],
            'embedding_dim' => $dim,
            'photo_path' => $request->file('photo')->store('faces/'.$employee->id, Attendance::SELFIE_DISK),
            'consented_at' => now(),
            'enrolled_at' => now(),
            'enrolled_tablet_id' => $tablet->id,
            'enrolled_by' => null,
        ])->save();

        if ($old && $old !== $profile->photo_path) {
            Storage::disk(Attendance::SELFIE_DISK)->delete($old);
        }

        $this->audit($tablet, 'face-enroll', "Mendaftarkan wajah {$employee->full_name} lewat tablet {$tablet->name}.", $employee);

        return response()->json(['data' => [
            'employee_id' => $employee->id,
            'enrolled_at' => $profile->enrolled_at->toIso8601String(),
        ]], 201);
    }

    /** PIN HR diperiksa lagi di server supaya token tablet saja tidak cukup. */
    private function checkHrPin(AttendanceTablet $tablet, string $pin): void
    {
        if (! TabletPin::verify($pin, $tablet->hr_pin_hash)) {
            throw ValidationException::withMessages(['hr_pin' => $tablet->hr_pin_hash ? 'PIN HR salah.' : 'PIN HR tablet ini belum diatur di web HRIS.']);
        }
    }
}
