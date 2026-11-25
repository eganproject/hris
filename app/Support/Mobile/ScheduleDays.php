<?php

namespace App\Support\Mobile;

use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Holiday;
use App\Services\DefaultOfficeSchedule;
use App\Support\LeaveCalendar;
use App\Support\WorkMode;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Jadwal per hari seorang karyawan dalam satu rentang, untuk aplikasi HP. Memakai
 * sumber yang sama dengan halaman web Jadwal Saya: baris roster, pola jam kantor
 * (DefaultOfficeSchedule), hari libur, dan cuti/izin (LeaveCalendar + WorkMode),
 * sehingga HP dan web tidak pernah menampilkan hari yang sama secara berbeda.
 */
final class ScheduleDays
{
    public function __construct(private readonly DefaultOfficeSchedule $officeSchedule) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function for(Employee $employee, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $from = Carbon::parse($from)->startOfDay();
        $to = Carbon::parse($to)->startOfDay();
        $days = collect(CarbonPeriod::create($from, $to)->toArray());

        $schedules = $this->officeSchedule
            ->fill(
                $employee,
                $employee->schedules()
                    ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
                    ->with('shift')
                    ->get(),
                $days,
            )
            ->keyBy(fn ($row) => $row->work_date->toDateString());

        $holidays = Holiday::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->where(fn ($query) => $query->where('is_national', true)->orWhere('branch_id', $employee->branch_id))
            ->get()
            ->keyBy(fn (Holiday $holiday) => $holiday->date->toDateString());

        $calendar = LeaveCalendar::for($employee, $from, $to);
        $employee->loadMissing('branch');

        return $days->map(function (CarbonInterface $day) use ($schedules, $holidays, $calendar, $employee) {
            $key = $day->toDateString();
            /** @var EmployeeSchedule|null $schedule */
            $schedule = $schedules->get($key);
            $approved = $calendar->approvedOn($key);
            $pending = $calendar->pendingOn($key);
            $mode = WorkMode::for($schedule, $approved);
            $holiday = $holidays->get($key);

            return [
                'date' => $key,
                'mode' => $mode->key, // office | wfh | trip | leave | off | none
                'label' => $holiday && ! $approved ? $holiday->name : $mode->label,
                'is_working' => $mode->isWorking && ! $holiday,
                'is_day_off' => (bool) $schedule?->is_day_off,
                'is_wfh' => $mode->key === 'wfh',
                'is_holiday' => (bool) $holiday,
                'holiday_name' => $holiday?->name,
                'shift' => $schedule?->shift ? [
                    'id' => $schedule->shift->id,
                    'code' => $schedule->shift->code,
                    'name' => $schedule->shift->name,
                    'start_time' => substr((string) $schedule->shift->start_time, 0, 5),
                    'end_time' => substr((string) $schedule->shift->end_time, 0, 5),
                ] : null,
                'location' => $mode->key === 'wfh' ? 'dari rumah' : $employee->branch?->name,
                'leave' => $approved ? ['id' => $approved->id, 'name' => $approved->leaveType?->name] : null,
                'pending_leave' => $pending ? ['id' => $pending->id, 'name' => $pending->leaveType?->name] : null,
                'note' => $schedule?->note,
            ];
        })->values();
    }
}
