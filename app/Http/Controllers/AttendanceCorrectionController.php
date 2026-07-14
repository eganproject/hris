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

        $scope = DataScope::forAttendance($request->user());

        $corrections = AttendanceCorrection::query()
            ->with(['employee', 'reviewer'])
            ->tap(fn ($query) => $scope->constrain($query))
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->orderByRaw("status = 'pending' desc")
            ->latest('work_date')
            ->paginate($perPage)
            ->withQueryString();

        return view('attendance.corrections.index', [
            'corrections' => $corrections,
            'status' => $status,
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
}
