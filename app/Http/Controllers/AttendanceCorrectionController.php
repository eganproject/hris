<?php

namespace App\Http\Controllers;

use App\Models\AttendanceCorrection;
use App\Services\AttendanceResolver;
use App\Support\ApprovalNotifier;
use App\Support\DataScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceCorrectionController extends Controller
{
    public function __construct(private readonly AttendanceResolver $resolver) {}

    public function index(Request $request): View
    {
        $status = $request->string('status')->toString() ?: 'pending';
        $perPage = min(max((int) $request->input('per_page', 15), 10), 100);
        $branchId = $request->integer('branch_id') ?: null;
        $departmentId = $request->integer('department_id') ?: null;
        $search = $request->string('search')->toString() ?: null;
        $dateFrom = $request->string('date_from')->toString() ?: null;
        $dateTo = $request->string('date_to')->toString() ?: null;

        $scope = DataScope::forAttendance($request->user());

        $corrections = AttendanceCorrection::query()
            ->with(['employee', 'reviewer'])
            ->tap(fn ($query) => $scope->constrain($query))
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($branchId, fn ($q) => $q->whereHas('employee', fn ($e) => $e->byBranch($branchId)))
            ->when($departmentId, fn ($q) => $q->whereHas('employee', fn ($e) => $e->byDepartment($departmentId)))
            ->when($search, fn ($q, $s) => $q->whereHas('employee', fn ($e) => $e
                ->where('full_name', 'like', "%{$s}%")
                ->orWhere('employee_number', 'like', "%{$s}%")))
            ->when($dateFrom, fn ($q, $d) => $q->whereDate('work_date', '>=', $d))
            ->when($dateTo, fn ($q, $d) => $q->whereDate('work_date', '<=', $d))
            ->orderByRaw("status = 'pending' desc")
            ->latest('work_date')
            ->paginate($perPage)
            ->withQueryString();

        return view('attendance.corrections.index', [
            'corrections' => $corrections,
            'status' => $status,
            'filters' => compact('search', 'branchId', 'departmentId', 'dateFrom', 'dateTo'),
            'branches' => $scope->branches(),
            'departments' => $scope->departments(),
            'pendingCount' => AttendanceCorrection::query()
                ->pending()
                ->tap(fn ($query) => $scope->constrain($query))
                ->count(),
        ]);
    }

    /**
     * Approve: apply the requested times to the resolved attendance for that day.
     */
    public function approve(Request $request, AttendanceCorrection $correction): RedirectResponse
    {
        DataScope::forAttendance($request->user())->authorize($correction->employee);
        $this->denySelfDecision($request, $correction);
        abort_unless($correction->isPending(), 403);

        $this->resolver->resolve(
            $correction->employee,
            $correction->work_date,
            $correction->requested_clock_in,
            $correction->requested_clock_out,
            'Koreksi disetujui: '.$correction->reason,
        );

        $correction->forceFill([
            'status' => AttendanceCorrection::STATUS_APPROVED,
            'reviewed_by' => auth()->id(),
            'decided_at' => now(),
        ])->save();

        app(ApprovalNotifier::class)->correctionDecided($correction);

        return redirect()->route('attendance.corrections.index')->with('status', 'Koreksi disetujui & absensi diperbarui.');
    }

    public function reject(Request $request, AttendanceCorrection $correction): RedirectResponse
    {
        DataScope::forAttendance($request->user())->authorize($correction->employee);
        $this->denySelfDecision($request, $correction);
        abort_unless($correction->isPending(), 403);

        $correction->forceFill([
            'status' => AttendanceCorrection::STATUS_REJECTED,
            'reviewed_by' => auth()->id(),
            'decided_at' => now(),
            'decision_notes' => $request->string('decision_notes')->toString() ?: null,
        ])->save();

        app(ApprovalNotifier::class)->correctionDecided($correction);

        return redirect()->route('attendance.corrections.index')->with('status', 'Koreksi ditolak.');
    }

    /** Pemisahan wewenang: tidak bisa memutuskan koreksi absensi milik sendiri. */
    private function denySelfDecision(Request $request, AttendanceCorrection $correction): void
    {
        abort_if(
            $correction->employee?->user_id !== null && $correction->employee->user_id === $request->user()->id,
            403,
            'Anda tidak bisa memutuskan koreksi absensi Anda sendiri.',
        );
    }
}
