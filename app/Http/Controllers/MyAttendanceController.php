<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Http\Requests\StoreAttendanceCorrectionRequest;
use App\Models\AttendanceCorrection;
use App\Models\Employee;
use App\Services\AttendanceResolver;
use App\Support\ApprovalNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MyAttendanceController extends Controller
{
    public function __construct(private readonly AttendanceResolver $resolver) {}

    /**
     * The employee's own attendance history plus their correction requests.
     */
    public function index(): View
    {
        $employee = $this->employee();

        return view('attendance.my-attendance.index', [
            'attendances' => $employee->attendances()
                ->whereDate('work_date', '>=', now()->subDays(30)->toDateString())
                ->with('shift')
                ->orderByDesc('work_date')
                ->get(),
            'corrections' => $employee->attendanceCorrections()
                ->with('reviewer')
                ->latest('id')
                ->get(),
            // WFH self check-in: only offered when today is an approved WFH day.
            'wfhToday' => $this->isWfhApprovedToday($employee),
            'todayAttendance' => $employee->attendances()->whereDate('work_date', now()->toDateString())->first(),
        ]);
    }

    /**
     * Record the WFH clock-in for today, straight into the attendance row (no machine
     * needed at home). Only allowed on a day the employee is approved to work from home.
     */
    public function checkIn(): RedirectResponse
    {
        $employee = $this->employee();

        if (! $this->isWfhApprovedToday($employee)) {
            return back()->with('error', 'Absen mandiri hanya untuk hari WFH yang sudah disetujui.');
        }

        $today = now();
        $existing = $employee->attendances()->whereDate('work_date', $today->toDateString())->first();

        if ($existing?->clock_in) {
            return back()->with('error', 'Anda sudah absen masuk hari ini pukul '.$existing->clock_in->format('H:i').'.');
        }

        $this->resolver->resolve($employee, $today, $today->format('H:i'), $existing?->clock_out?->format('H:i'), $existing?->note);

        return back()->with('status', 'Absen masuk WFH tercatat pukul '.$today->format('H:i').'.');
    }

    public function checkOut(): RedirectResponse
    {
        $employee = $this->employee();

        if (! $this->isWfhApprovedToday($employee)) {
            return back()->with('error', 'Absen mandiri hanya untuk hari WFH yang sudah disetujui.');
        }

        $today = now();
        $existing = $employee->attendances()->whereDate('work_date', $today->toDateString())->first();

        if (! $existing?->clock_in) {
            return back()->with('error', 'Absen masuk dulu sebelum absen pulang.');
        }

        if ($existing->clock_out) {
            return back()->with('error', 'Anda sudah absen pulang hari ini pukul '.$existing->clock_out->format('H:i').'.');
        }

        $this->resolver->resolve($employee, $today, $existing->clock_in->format('H:i'), $today->format('H:i'), $existing->note);

        return back()->with('status', 'Absen pulang WFH tercatat pukul '.$today->format('H:i').'.');
    }

    /**
     * Is today a WFH day for this employee — whether from the roster (a scheduled
     * WFH day) or from an approved WFH request?
     */
    private function isWfhApprovedToday(Employee $employee): bool
    {
        $today = now()->toDateString();

        $scheduled = $employee->schedules()
            ->whereDate('work_date', $today)
            ->where('is_wfh', true)
            ->exists();

        if ($scheduled) {
            return true;
        }

        return $employee->leaveRequests()
            ->approvedOn($today)
            ->whereHas('leaveType', fn ($query) => $query->where('attendance_status', AttendanceStatus::Wfh->value))
            ->exists();
    }

    public function store(StoreAttendanceCorrectionRequest $request): RedirectResponse
    {
        $correction = $this->employee()->attendanceCorrections()->create([
            ...$request->validated(),
            'status' => AttendanceCorrection::STATUS_PENDING,
        ]);

        app(ApprovalNotifier::class)->correctionSubmitted($correction);

        return redirect()->route('my-attendance.index')->with('status', 'Pengajuan koreksi absensi terkirim.');
    }

    public function cancel(AttendanceCorrection $correction): RedirectResponse
    {
        abort_unless($correction->employee_id === $this->employee()->id && $correction->isPending(), 403);

        // HR tidak perlu lagi memutuskannya — beri tahu sebelum datanya hilang.
        app(ApprovalNotifier::class)->correctionCancelled($correction);

        $correction->delete();

        return redirect()->route('my-attendance.index')->with('status', 'Pengajuan koreksi dibatalkan.');
    }

    private function employee(): Employee
    {
        $employee = auth()->user()->employee;

        abort_unless($employee, 403, 'Akun Anda belum tertaut ke data karyawan.');

        return $employee;
    }
}
