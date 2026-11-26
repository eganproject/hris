<?php

namespace App\Services\Tablet;

use App\Models\Attendance;
use App\Models\AttendanceTablet;
use App\Models\Employee;
use App\Models\TabletAttendanceLog;
use App\Services\DefaultOfficeSchedule;
use App\Services\Mobile\MobileAttendanceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Isi sinkron untuk tablet: daftar karyawan Lokasi Kerja tablet beserta data
 * wajah dan hash PIN-nya (supaya tablet tetap jalan saat internet putus), jadwal
 * hari ini, sesi yang masih terbuka, dan daftar "Baru saja absen".
 */
class TabletSyncPayload
{
    public function __construct(private readonly DefaultOfficeSchedule $officeSchedule) {}

    /** @return array<string, mixed> */
    public function build(AttendanceTablet $tablet): array
    {
        $tablet->loadMissing('branch');
        $now = now();
        $today = $now->copy()->startOfDay();
        $sessionMinutes = MobileAttendanceService::sessionMaxMinutes();

        $employees = Employee::query()
            ->active()
            ->where('branch_id', $tablet->branch_id)
            ->with(['jobPosition', 'faceProfile'])
            ->with(['schedules' => fn ($q) => $q->whereDate('work_date', $today->toDateString())->with('shift')])
            ->orderBy('full_name')
            ->get();

        $ids = $employees->pluck('id');

        // Absensi yang relevan: jam masuk dalam batas sesi (untuk sesi terbuka) atau hari ini.
        $attendances = Attendance::query()
            ->whereIn('employee_id', $ids)
            ->where(function ($q) use ($now, $sessionMinutes, $today) {
                $q->where('clock_in', '>=', $now->copy()->subMinutes($sessionMinutes))
                    ->orWhereDate('work_date', $today->toDateString());
            })
            ->get()
            ->groupBy('employee_id');

        $rows = $employees->map(function (Employee $employee) use ($attendances, $today, $now, $sessionMinutes) {
            $profile = $employee->faceProfile;
            $schedule = $employee->schedules->first() ?? $this->officeSchedule->scheduleFor($employee, $today);
            $shift = ($schedule && ! $schedule->is_day_off) ? $schedule->shift : null;

            $mine = $attendances->get($employee->id, collect());
            $open = $mine
                ->filter(fn (Attendance $a) => $a->clock_in && ! $a->clock_out && $a->clock_in->lte($now) && $a->clock_in->gte($now->copy()->subMinutes($sessionMinutes)))
                ->sortByDesc('clock_in')
                ->first();
            $todayRow = $mine->first(fn (Attendance $a) => Carbon::parse($a->work_date)->isSameDay($today));

            return [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'nik' => $employee->employee_number,
                'position' => $employee->jobPosition?->name,
                'initials' => $this->initials($employee->full_name),
                'face_model' => $profile?->embedding_model,
                'faces' => $profile?->hasFace() ? array_values($profile->embeddings) : [],
                'pin_hash' => $profile?->pin_hash,
                'schedule' => $shift ? [
                    'start' => substr((string) $shift->start_time, 0, 5),
                    'end' => substr((string) $shift->end_time, 0, 5),
                    'late_tolerance_minutes' => (int) $shift->late_tolerance_minutes,
                ] : null,
                'open_clock_in' => $open?->clock_in?->toIso8601String(),
                'today_clock_in' => $todayRow?->clock_in?->toIso8601String(),
                'today_clock_out' => $todayRow?->clock_out?->toIso8601String(),
            ];
        })->values();

        $recent = TabletAttendanceLog::query()
            ->where('branch_id', $tablet->branch_id)
            ->where('result', TabletAttendanceLog::RESULT_ACCEPTED)
            ->where('captured_at', '>=', $today)
            ->with('employee')
            ->latest('captured_at')
            ->limit(5)
            ->get()
            ->map(fn (TabletAttendanceLog $log) => [
                'employee_id' => $log->employee_id,
                'name' => $this->shortName((string) $log->employee?->full_name),
                'initials' => $this->initials((string) $log->employee?->full_name),
                'type' => $log->type,
                'at' => $log->captured_at->toIso8601String(),
            ]);

        $present = $attendances->flatten()
            ->filter(fn (Attendance $a) => $a->clock_in && Carbon::parse($a->work_date)->isSameDay($today))
            ->pluck('employee_id')
            ->unique()
            ->count();

        return [
            'server_time' => $now->toIso8601String(),
            'timezone' => config('app.timezone'),
            'company' => config('app.name'),
            'tablet' => [
                'id' => $tablet->id,
                'name' => $tablet->name,
                'branch' => ['id' => $tablet->branch_id, 'name' => $tablet->branch?->name],
                'hr_pin_hash' => $tablet->hr_pin_hash,
            ],
            'settings' => TabletSettings::forTablet() + ['session_max_minutes' => $sessionMinutes],
            'employees' => $rows,
            'recent' => $recent,
            'stats' => ['present' => $present, 'total' => $employees->count()],
        ];
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        return Str::upper(collect($parts)->filter()->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode(''));
    }

    /** "Dimas Aditya Pratama" -> "Dimas A." untuk layar umum. */
    private function shortName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        return count($parts) > 1 ? $parts[0].' '.mb_substr($parts[1], 0, 1).'.' : ($parts[0] ?? '');
    }
}
