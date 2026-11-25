<?php

namespace App\Http\Controllers\Api\Mobile\Hr;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Employee;
use App\Support\ApprovalNotifier;
use App\Support\Mobile\Present;
use App\Support\Mobile\ScheduleDays;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * "Tim hari ini" untuk atasan: siapa yang sudah masuk, telat, belum absen, cuti,
 * atau WFH, dihitung dari jadwal + absensi hari ini seluruh bawahan (semua level).
 */
class TeamController extends HrController
{
    public function __construct(private readonly ScheduleDays $days) {}

    public function today(): JsonResponse
    {
        $leader = $this->leader();
        $today = now()->startOfDay();

        $members = Employee::query()->active()
            ->whereIn('id', $this->teamIds($leader))
            ->with(['jobPosition', 'department', 'branch', 'manager:id,full_name'])
            ->orderBy('full_name')
            ->get();

        $attendances = Attendance::query()
            ->whereIn('employee_id', $members->pluck('id'))
            ->where('work_date', $today->toDateString())
            ->get()
            ->keyBy('employee_id');

        $rows = $members->map(function (Employee $member) use ($attendances, $today, $leader) {
            $day = $this->days->for($member, $today, $today)->first();
            /** @var Attendance|null $att */
            $att = $attendances->get($member->id);
            $state = $this->state($day, $att);

            return [
                'employee' => Present::person($member),
                // Bawahan tidak langsung ditampilkan bersama atasan langsungnya.
                'is_direct' => $member->manager_id === $leader->id,
                'manager_name' => $member->manager_id === $leader->id ? null : $member->manager?->full_name,
                'state' => $state, // present | late | not_yet | leave | wfh | off
                'clock_in' => $att?->clock_in?->format('H:i'),
                'clock_out' => $att?->clock_out?->format('H:i'),
                'late_minutes' => (int) ($att?->late_minutes ?? 0),
                'schedule' => $day,
            ];
        });

        $order = ['not_yet' => 0, 'late' => 1, 'present' => 2, 'wfh' => 3, 'leave' => 4, 'off' => 5];

        return $this->ok([
            'date' => $today->toDateString(),
            'as_of' => now()->format('H:i'),
            'team_size' => $members->count(),
            'counts' => [
                'present' => $rows->whereIn('state', ['present', 'wfh'])->count(),
                'late' => $rows->where('state', 'late')->count(),
                'not_yet' => $rows->where('state', 'not_yet')->count(),
                'leave' => $rows->where('state', 'leave')->count(),
            ],
            'members' => $rows->sortBy(fn ($r) => $order[$r['state']] ?? 9)->values(),
        ]);
    }

    /** Tombol "Ingatkan": notifikasi ke anggota yang belum absen. Dibatasi sekali per 30 menit. */
    public function remind(Employee $employee): JsonResponse
    {
        $leader = $this->leader();
        abort_unless(in_array($employee->id, $this->teamIds($leader), true), 403, 'Karyawan ini bukan anggota tim Anda.');

        $key = "mobile:remind:{$leader->id}:{$employee->id}";
        abort_if(Cache::has($key), 429, 'Pengingat sudah dikirim. Coba lagi nanti.');

        app(ApprovalNotifier::class)->attendanceReminder($employee->loadMissing('user'), $leader->full_name);
        Cache::put($key, true, now()->addMinutes(30));

        return $this->message("Pengingat terkirim ke {$employee->full_name}.");
    }

    private function state(?array $day, ?Attendance $att): string
    {
        if ($day && $day['mode'] === 'leave') {
            return 'leave';
        }

        if ($att?->clock_in) {
            if ($att->status === AttendanceStatus::Wfh || ($day['is_wfh'] ?? false)) {
                return 'wfh';
            }

            return $att->status === AttendanceStatus::Late || (int) $att->late_minutes > 0 ? 'late' : 'present';
        }

        if ($att && in_array($att->status, [AttendanceStatus::Leave, AttendanceStatus::Permit, AttendanceStatus::Sick, AttendanceStatus::BusinessTrip], true)) {
            return 'leave';
        }

        if (! $day || ! $day['is_working']) {
            return 'off';
        }

        return 'not_yet';
    }
}
