<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAttendanceCorrectionRequest;
use App\Models\AttendanceCorrection;
use App\Models\Employee;
use App\Support\ApprovalNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MyAttendanceController extends Controller
{
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
        ]);
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
