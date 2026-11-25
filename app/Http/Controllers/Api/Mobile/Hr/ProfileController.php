<?php

namespace App\Http\Controllers\Api\Mobile\Hr;

use App\Enums\LeaveRequestStatus;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\OvertimeApproval;
use App\Support\Mobile\Present;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Profil di aplikasi HP: data diri (yang boleh diubah sendiri sama dengan web:
 * telepon, alamat, motto) dan ringkasan menu atasan bila punya bawahan.
 */
class ProfileController extends HrController
{
    public function show(Request $request): JsonResponse
    {
        $employee = $this->employee()->loadMissing(['jobPosition', 'department', 'departments', 'branch', 'manager.jobPosition']);
        $isLeader = $employee->subordinates()->exists();

        return $this->ok([
            ...Present::person($employee),
            'employee_number' => $employee->employee_number,
            'email' => $employee->email ?? $request->user()->email,
            'phone' => $employee->phone,
            'address' => $employee->address,
            'motto' => $employee->motto,
            'birth_date' => Present::date($employee->birth_date),
            'join_date' => Present::date($employee->join_date),
            'branch' => $employee->branch?->name,
            'departments' => $employee->departments->pluck('name')->values(),
            'manager' => Present::person($employee->manager),
            'is_leader' => $isLeader,
            'leader' => $isLeader ? $this->leaderBadges($employee) : null,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'motto' => ['nullable', 'string', 'max:160'],
        ], [], [
            'phone' => 'nomor telepon',
            'address' => 'alamat',
            'motto' => 'motto',
        ]);

        $employee = $this->employee();
        $employee->update($data);

        return $this->message('Data pribadi berhasil diperbarui.');
    }

    /**
     * Ganti foto profil sendiri dari HP. Standar foto sama dengan web; foto lama
     * dihapus dari disk. Foto profil terpisah dari selfie absen.
     */
    public function updatePhoto(Request $request): JsonResponse
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:min_width=200,min_height=200,max_width=4000,max_height=4000'],
        ], [
            'photo.required' => 'Pilih fotonya dulu.',
            'photo.max' => 'Ukuran foto maksimal 4 MB.',
            'photo.mimes' => 'Foto harus berformat JPG, PNG, atau WebP.',
            'photo.dimensions' => 'Resolusi foto minimal 200x200 px.',
        ]);

        $employee = $this->employee();
        $previous = $employee->photo_path;
        $employee->update(['photo_path' => $request->file('photo')->store('employees/photos', 'public')]);
        $this->forgetPhoto($previous, $employee->photo_path);

        return $this->message('Foto profil diperbarui.', ['photo_url' => $employee->photo_url]);
    }

    public function destroyPhoto(): JsonResponse
    {
        $employee = $this->employee();
        $previous = $employee->photo_path;

        if ($previous) {
            $employee->update(['photo_path' => null]);
            $this->forgetPhoto($previous, null);
        }

        return $this->message('Foto profil dihapus.', ['photo_url' => null]);
    }

    private function forgetPhoto(?string $previous, ?string $current): void
    {
        if ($previous && $previous !== $current && ! Employee::query()->where('photo_path', $previous)->exists()) {
            Storage::disk('public')->delete($previous);
        }
    }

    /** Angka badge pada menu atasan (Beranda). */
    private function leaderBadges(Employee $leader): array
    {
        $employeeId = $leader->id;

        return [
            'pending_approvals' => LeaveRequest::query()->where('supervisor_id', $employeeId)
                ->where('status', LeaveRequestStatus::PendingSupervisor->value)->count()
                + OvertimeApproval::query()->where('supervisor_id', $employeeId)
                    ->where('status', OvertimeApproval::STATUS_PENDING)->count(),
            'team_size' => Employee::query()->active()->whereIn('id', $this->teamIds($leader))->count(),
        ];
    }
}
