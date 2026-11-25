<?php

namespace App\Http\Controllers\Api\Mobile\Hr;

use App\Enums\LeaveRequestStatus;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\OvertimeApproval;
use App\Support\Mobile\Present;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
