<?php

namespace App\Http\Controllers\Api\Mobile\Hr;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Employee;
use App\Support\Mobile\Present;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Profil dan riwayat absen satu anggota tim, untuk atasannya (semua level ke
 * bawah, sama dengan "Tim hari ini"). Tanpa data pribadi seperti alamat atau
 * tanggal lahir: cukup yang dibutuhkan atasan untuk memantau kehadiran.
 */
class TeamMemberController extends HrController
{
    public function show(Request $request, Employee $employee): JsonResponse
    {
        $leader = $this->leader();
        abort_unless(in_array($employee->id, $this->teamIds($leader), true), 403, 'Karyawan ini bukan anggota tim Anda.');

        $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $month = $request->filled('month')
            ? Carbon::createFromFormat('Y-m', $request->string('month'))->startOfMonth()
            : now()->startOfMonth();

        $employee->loadMissing(['jobPosition', 'department', 'branch', 'manager.jobPosition', 'manager.department']);

        $rows = $employee->attendances()
            ->whereBetween('work_date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->with('shift')
            ->orderByDesc('work_date')
            ->get();

        $leaveLike = [AttendanceStatus::Leave, AttendanceStatus::Permit, AttendanceStatus::Sick, AttendanceStatus::BusinessTrip];

        return $this->ok([
            'person' => [
                ...Present::person($employee),
                'branch' => $employee->branch?->name,
                'joined' => $employee->join_date?->format('Y-m'),
                'manager' => Present::person($employee->manager),
                'is_direct' => $employee->manager_id === $leader->id,
            ],
            'month' => $month->format('Y-m'),
            'summary' => [
                'present' => $rows->filter(fn (Attendance $a) => $a->status->isWorked())->count(),
                'late' => $rows->where('status', AttendanceStatus::Late)->count(),
                'leave' => $rows->filter(fn (Attendance $a) => in_array($a->status, $leaveLike, true))->count(),
                'absent' => $rows->where('status', AttendanceStatus::Absent)->count(),
                'late_minutes' => (int) $rows->sum('late_minutes'),
            ],
            'items' => $rows->map(fn (Attendance $a) => [
                'id' => $a->id,
                'work_date' => $a->work_date->toDateString(),
                'status' => $a->status->value,
                'status_label' => $a->status->label(),
                'shift' => $a->shift?->name,
                'clock_in' => $a->clock_in?->toIso8601String(),
                'clock_out' => $a->clock_out?->toIso8601String(),
                'late_minutes' => (int) $a->late_minutes,
                'early_leave_minutes' => (int) $a->early_leave_minutes,
                'work_minutes' => (int) $a->work_minutes,
                'note' => $a->note,
            ])->values(),
        ]);
    }
}
