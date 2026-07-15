<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Shift;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Turns the raw inputs for one employee-day — the materialized schedule, holidays,
 * approved leave and any clock punches — into a single resolved AttendanceStatus
 * plus its derived numbers (lateness, work minutes, overtime).
 *
 * Precedence: Holiday > approved Leave > scheduled shift/day-off vs. punches.
 */
class AttendanceResolver
{
    /**
     * Compute (without persisting) the attendance for an employee on a date.
     * Punches are optional times ("H:i"); clock-out past midnight is inferred.
     *
     * @return array<string, mixed>
     */
    public function compute(Employee $employee, CarbonInterface $date, ?string $clockIn = null, ?string $clockOut = null): array
    {
        $date = Carbon::parse($date)->startOfDay();

        $schedule = $employee->schedules()->whereDate('work_date', $date->toDateString())->with('shift')->first();
        $shift = ($schedule && ! $schedule->is_day_off) ? $schedule->shift : null;

        $in = $this->punch($date, $clockIn);
        $out = $this->punch($date, $clockOut);

        // Overnight / late clock-out rolls to the next day.
        if ($in && $out && $out->lessThanOrEqualTo($in)) {
            $out->addDay();
        }

        $result = [
            'shift_id' => $shift?->id,
            'clock_in' => $in,
            'clock_out' => $out,
            'late_minutes' => 0,
            'early_leave_minutes' => 0,
            'work_minutes' => 0,
            'overtime_minutes' => 0,
            'leave_request_id' => null,
            'holiday_id' => null,
        ];

        // 1. Holiday wins. Work done on a holiday counts entirely as overtime.
        $holiday = Holiday::query()->appliesTo($employee->branch_id)->whereDate('date', $date->toDateString())->first();

        if ($holiday) {
            $result['holiday_id'] = $holiday->id;
            $result['status'] = AttendanceStatus::Holiday;

            if ($in && $out) {
                $result['work_minutes'] = $this->minutes($in, $out);
                $result['overtime_minutes'] = $result['work_minutes'];
            }

            return $result;
        }

        // 2. Approved leave. WFH is a WORKING arrangement (the employee clocks in from
        // home via self check-in), so it flows through the normal shift computation and
        // only keeps the WFH label. Every other leave type (cuti/izin/sakit/dinas) is
        // non-working and stops here.
        $leave = $employee->leaveRequests()->approvedOn($date->toDateString())->with('leaveType')->first();
        $isWfh = false;

        if ($leave) {
            $result['leave_request_id'] = $leave->id;
            $leaveStatus = $leave->leaveType?->attendance_status ?? AttendanceStatus::Leave;

            if ($leaveStatus !== AttendanceStatus::Wfh) {
                $result['status'] = $leaveStatus;

                return $result;
            }

            $isWfh = true;
        }

        // 3. No shift scheduled (day off or nothing). Punches = worked on a rest day.
        if (! $shift) {
            if ($in && $out) {
                $result['status'] = $isWfh ? AttendanceStatus::Wfh : AttendanceStatus::Present;
                $result['work_minutes'] = $this->minutes($in, $out);
                $result['overtime_minutes'] = $result['work_minutes'];
            } else {
                $result['status'] = $isWfh ? AttendanceStatus::Wfh : AttendanceStatus::DayOff;
            }

            return $result;
        }

        // 4. Scheduled shift with no punch. On a WFH day that just means the employee
        // has not clocked in from home yet (still WFH, nol jam); otherwise = absent.
        if (! $in) {
            $result['status'] = $isWfh ? AttendanceStatus::Wfh : AttendanceStatus::Absent;

            return $result;
        }

        // 5. Scheduled shift with punches: derive lateness, early-leave and overtime.
        $window = $shift->windowFor($date);

        $lateMinutes = $in->greaterThan($window['start']) ? $this->minutes($window['start'], $in) : 0;
        $earlyMinutes = ($out && $out->lessThan($window['end'])) ? $this->minutes($out, $window['end']) : 0;

        $result['late_minutes'] = $lateMinutes;
        $result['early_leave_minutes'] = $earlyMinutes;

        if ($out) {
            $result['work_minutes'] = max(0, $this->minutes($in, $out) - (int) $shift->break_minutes);
            $result['overtime_minutes'] = $shift->overtimeMinutesFor($out, $date);
        }

        // A WFH day keeps the WFH label even when late/early — the worked minutes and
        // overtime above are still computed and count for payroll.
        $result['status'] = $isWfh ? AttendanceStatus::Wfh : match (true) {
            $lateMinutes > (int) $shift->late_tolerance_minutes => AttendanceStatus::Late,
            $earlyMinutes > (int) $shift->early_leave_tolerance_minutes => AttendanceStatus::EarlyLeave,
            default => AttendanceStatus::Present,
        };

        return $result;
    }

    /**
     * Compute and persist the attendance row (idempotent per employee-day).
     */
    public function resolve(Employee $employee, CarbonInterface $date, ?string $clockIn = null, ?string $clockOut = null, ?string $note = null): Attendance
    {
        $computed = $this->compute($employee, $date, $clockIn, $clockOut);
        $computed['status'] = $computed['status']->value;
        $computed['note'] = $note;

        return Attendance::query()->updateOrCreate(
            ['employee_id' => $employee->id, 'work_date' => Carbon::parse($date)->toDateString()],
            $computed,
        );
    }

    /**
     * Re-run the resolver for an employee-day, keeping any punches/note already
     * recorded. Used by the bulk "process this date" action.
     */
    public function reprocess(Employee $employee, CarbonInterface $date): Attendance
    {
        $existing = $employee->attendances()->whereDate('work_date', Carbon::parse($date)->toDateString())->first();

        return $this->resolve(
            $employee,
            $date,
            $existing?->clock_in?->format('H:i'),
            $existing?->clock_out?->format('H:i'),
            $existing?->note,
        );
    }

    private function punch(CarbonInterface $date, ?string $time): ?Carbon
    {
        if (! $time) {
            return null;
        }

        return Carbon::parse($date)->startOfDay()->setTimeFromTimeString($time);
    }

    private function minutes(CarbonInterface $from, CarbonInterface $to): int
    {
        return (int) intdiv($to->getTimestamp() - $from->getTimestamp(), 60);
    }
}
