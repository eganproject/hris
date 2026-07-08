<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\ShiftSwapRequest;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Validates and applies employee-initiated schedule changes (swap / cover / day-off
 * trade). The applied result is written as manual overrides on employee_schedules,
 * which the roster generator preserves. Conflicts are checked both at submission and
 * again at approval time (the roster can change in between).
 */
class ShiftSwapService
{
    public function __construct(private readonly ScheduleGenerator $generator)
    {
    }

    /**
     * Return a list of human-readable conflicts (empty = OK).
     *
     * @return array<int, string>
     */
    public function conflicts(string $type, Employee $requester, CarbonInterface $requesterDate, Employee $partner, ?CarbonInterface $partnerDate): array
    {
        $errors = [];
        $today = Carbon::today();
        $rDate = Carbon::parse($requesterDate)->startOfDay();
        $pDate = $partnerDate ? Carbon::parse($partnerDate)->startOfDay() : null;
        $needsPartnerDate = $type !== ShiftSwapRequest::TYPE_COVER;

        if ($requester->id === $partner->id) {
            $errors[] = 'Tidak bisa menukar dengan diri sendiri.';

            return $errors;
        }

        if ($rDate->lessThan($today)) {
            $errors[] = 'Tanggal Anda sudah lewat.';
        }
        if ($needsPartnerDate && ! $pDate) {
            $errors[] = 'Tanggal rekan wajib diisi.';

            return $errors;
        }
        if ($pDate && $pDate->lessThan($today)) {
            $errors[] = 'Tanggal rekan sudah lewat.';
        }

        // Requester must actually have a working shift to give up.
        if (! $this->workingShiftId($requester, $rDate)) {
            $errors[] = 'Anda tidak punya shift kerja pada tanggal tersebut.';
        }

        if ($this->onLeaveOrHoliday($requester, $rDate)) {
            $errors[] = 'Tanggal Anda bertepatan dengan cuti/libur.';
        }

        if ($type === ShiftSwapRequest::TYPE_COVER) {
            // Partner takes the requester's shift on the same date; they must be free.
            if (! $this->isFree($partner, $rDate)) {
                $errors[] = 'Rekan sudah terjadwal pada tanggal itu.';
            }
            if ($this->onLeaveOrHoliday($partner, $rDate)) {
                $errors[] = 'Rekan sedang cuti/libur pada tanggal itu.';
            }

            return $errors;
        }

        // swap / dayoff: partner must have a working shift on their date.
        if ($pDate && ! $this->workingShiftId($partner, $pDate)) {
            $errors[] = 'Rekan tidak punya shift kerja pada tanggalnya.';
        }
        if ($pDate && $this->onLeaveOrHoliday($partner, $pDate)) {
            $errors[] = 'Tanggal rekan bertepatan dengan cuti/libur.';
        }

        // Cross-date exchange must not create a double-booking.
        if ($pDate && ! $rDate->equalTo($pDate)) {
            if (! $this->isFree($requester, $pDate)) {
                $errors[] = 'Anda sudah terjadwal pada tanggal rekan (akan bentrok).';
            }
            if (! $this->isFree($partner, $rDate)) {
                $errors[] = 'Rekan sudah terjadwal pada tanggal Anda (akan bentrok).';
            }
        }

        return $errors;
    }

    public function submit(Employee $requester, array $data): ShiftSwapRequest
    {
        return ShiftSwapRequest::query()->create([
            'requester_id' => $requester->id,
            'requester_date' => $data['requester_date'],
            'partner_id' => $data['partner_id'],
            'partner_date' => $data['type'] === ShiftSwapRequest::TYPE_COVER ? null : ($data['partner_date'] ?? null),
            'type' => $data['type'],
            'reason' => $data['reason'] ?? null,
            'status' => ShiftSwapRequest::STATUS_PENDING_PARTNER,
        ]);
    }

    public function partnerRespond(ShiftSwapRequest $request, bool $accept): void
    {
        $request->forceFill([
            'status' => $accept ? ShiftSwapRequest::STATUS_PENDING_HR : ShiftSwapRequest::STATUS_REJECTED,
            'partner_responded_at' => now(),
        ])->save();
    }

    public function hrApprove(ShiftSwapRequest $request, ?string $notes = null): array
    {
        $conflicts = $this->conflicts(
            $request->type,
            $request->requester,
            $request->requester_date,
            $request->partner,
            $request->partner_date,
        );

        if ($conflicts !== []) {
            return $conflicts; // caller reports; request stays pending
        }

        $this->apply($request);

        $request->forceFill([
            'status' => ShiftSwapRequest::STATUS_APPROVED,
            'reviewed_by' => Auth::id(),
            'decided_at' => now(),
            'decision_notes' => $notes,
        ])->save();

        return [];
    }

    public function hrReject(ShiftSwapRequest $request, ?string $notes = null): void
    {
        $request->forceFill([
            'status' => ShiftSwapRequest::STATUS_REJECTED,
            'reviewed_by' => Auth::id(),
            'decided_at' => now(),
            'decision_notes' => $notes,
        ])->save();
    }

    public function cancel(ShiftSwapRequest $request): void
    {
        $request->forceFill(['status' => ShiftSwapRequest::STATUS_CANCELLED])->save();
    }

    /**
     * Write the resulting schedule as manual overrides (preserved by the generator).
     */
    private function apply(ShiftSwapRequest $request): void
    {
        $requester = $request->requester;
        $partner = $request->partner;
        $rDate = $request->requester_date;
        $note = 'Tukar jadwal #'.$request->id;

        $sa = $this->workingShiftId($requester, $rDate);

        if ($request->isCover()) {
            $this->generator->override($requester, $rDate, null, true, $note);
            $this->generator->override($partner, $rDate, $sa, false, $note);

            return;
        }

        $pDate = $request->partner_date;
        $sb = $this->workingShiftId($partner, $pDate);

        if ($rDate->equalTo($pDate)) {
            $this->generator->override($requester, $rDate, $sb, $sb === null, $note);
            $this->generator->override($partner, $pDate, $sa, $sa === null, $note);

            return;
        }

        $this->generator->override($requester, $rDate, null, true, $note);       // requester off on their day
        $this->generator->override($partner, $rDate, $sa, false, $note);         // partner works requester's shift
        $this->generator->override($partner, $pDate, null, true, $note);         // partner off on their day
        $this->generator->override($requester, $pDate, $sb, false, $note);       // requester works partner's shift
    }

    private function workingShiftId(Employee $employee, CarbonInterface $date): ?int
    {
        $schedule = $this->scheduleOn($employee, $date);

        return ($schedule && ! $schedule->is_day_off) ? $schedule->shift_id : null;
    }

    private function isFree(Employee $employee, CarbonInterface $date): bool
    {
        $schedule = $this->scheduleOn($employee, $date);

        return ! $schedule || $schedule->is_day_off;
    }

    private function onLeaveOrHoliday(Employee $employee, CarbonInterface $date): bool
    {
        $dateStr = Carbon::parse($date)->toDateString();

        return $employee->leaveRequests()->approvedOn($dateStr)->exists();
    }

    private function scheduleOn(Employee $employee, CarbonInterface $date): ?EmployeeSchedule
    {
        return $employee->schedules()->whereDate('work_date', Carbon::parse($date)->toDateString())->first();
    }
}
