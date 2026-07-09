<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\OvertimeApproval;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Employee self-service overtime: an employee submits an overtime request for a
 * worked day (time window + reason); their direct manager (supervisor) approves or
 * rejects it. Approved overtime feeds the HR recap used for payroll.
 */
class MyOvertimeController extends Controller
{
    public function index(): View
    {
        $employee = $this->employee();

        return view('attendance.my-overtime.index', [
            'myRequests' => OvertimeApproval::query()
                ->where('employee_id', $employee->id)
                ->with('supervisor')
                ->latest('work_date')
                ->latest('id')
                ->get(),
            'pendingForMe' => OvertimeApproval::query()
                ->where('supervisor_id', $employee->id)
                ->where('status', OvertimeApproval::STATUS_PENDING)
                ->with('employee')
                ->latest('work_date')
                ->get(),
            'hasSupervisor' => (bool) $employee->manager_id,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $employee = $this->employee();

        if (! $employee->manager_id) {
            return back()->with('error', 'Atasan langsung Anda belum diatur. Hubungi HR sebelum mengajukan lembur.');
        }

        $data = $request->validate([
            'work_date' => ['required', 'date', 'before_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'reason' => ['required', 'string', 'max:500'],
        ], [], [
            'work_date' => 'tanggal lembur',
            'start_time' => 'jam mulai',
            'end_time' => 'jam selesai',
            'reason' => 'uraian pekerjaan',
        ]);

        $minutes = OvertimeApproval::minutesBetween($data['start_time'], $data['end_time']);

        if ($minutes <= 0) {
            return back()->withInput()->withErrors(['end_time' => 'Jam selesai harus menghasilkan durasi lembur lebih dari 0 menit.']);
        }

        if ($minutes > 720) {
            return back()->withInput()->withErrors(['end_time' => 'Durasi lembur tidak wajar (lebih dari 12 jam). Periksa kembali jam mulai & selesai.']);
        }

        $alreadyRequested = OvertimeApproval::query()
            ->where('employee_id', $employee->id)
            ->where('work_date', $data['work_date'])
            ->whereIn('status', [OvertimeApproval::STATUS_PENDING, OvertimeApproval::STATUS_APPROVED])
            ->exists();

        if ($alreadyRequested) {
            return back()->withInput()->withErrors(['work_date' => 'Anda sudah mengajukan lembur untuk tanggal ini.']);
        }

        $computed = (int) (Attendance::query()
            ->where('employee_id', $employee->id)
            ->where('work_date', $data['work_date'])
            ->value('overtime_minutes') ?? 0);

        OvertimeApproval::query()->create([
            'employee_id' => $employee->id,
            'supervisor_id' => $employee->manager_id,
            'work_date' => $data['work_date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'requested_minutes' => $minutes,
            'reason' => $data['reason'],
            'requested_at' => now(),
            'computed_minutes' => $computed,
            'approved_minutes' => 0,
            'status' => OvertimeApproval::STATUS_PENDING,
        ]);

        return redirect()->route('my-overtime.index')->with('status', 'Pengajuan lembur berhasil dikirim ke atasan Anda.');
    }

    public function cancel(OvertimeApproval $overtime): RedirectResponse
    {
        $employee = $this->employee();

        abort_unless($overtime->employee_id === $employee->id && $overtime->status === OvertimeApproval::STATUS_PENDING, 403);

        $overtime->delete();

        return redirect()->route('my-overtime.index')->with('status', 'Pengajuan lembur dibatalkan.');
    }

    public function approve(Request $request, OvertimeApproval $overtime): RedirectResponse
    {
        $this->authorizeSupervisor($overtime);

        $approved = $request->filled('approved_minutes')
            ? max(0, min(720, (int) $request->input('approved_minutes')))
            : $overtime->requested_minutes;

        $overtime->update([
            'status' => OvertimeApproval::STATUS_APPROVED,
            'approved_minutes' => $approved,
            'reviewed_by' => auth()->id(),
            'decided_at' => now(),
        ]);

        return redirect()->route('my-overtime.index')->with('status', 'Pengajuan lembur bawahan disetujui.');
    }

    public function reject(Request $request, OvertimeApproval $overtime): RedirectResponse
    {
        $this->authorizeSupervisor($overtime);

        $overtime->update([
            'status' => OvertimeApproval::STATUS_REJECTED,
            'approved_minutes' => 0,
            'reviewed_by' => auth()->id(),
            'decided_at' => now(),
            'notes' => $request->string('notes')->toString() ?: null,
        ]);

        return redirect()->route('my-overtime.index')->with('status', 'Pengajuan lembur bawahan ditolak.');
    }

    private function authorizeSupervisor(OvertimeApproval $overtime): void
    {
        abort_unless(
            $overtime->supervisor_id === $this->employee()->id
                && $overtime->status === OvertimeApproval::STATUS_PENDING,
            403,
        );
    }

    private function employee(): Employee
    {
        $employee = auth()->user()->employee;

        abort_unless($employee, 403, 'Akun Anda belum tertaut ke data karyawan.');

        return $employee;
    }
}
