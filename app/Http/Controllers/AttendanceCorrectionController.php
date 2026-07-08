<?php

namespace App\Http\Controllers;

use App\Models\AttendanceCorrection;
use App\Services\AttendanceResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceCorrectionController extends Controller
{
    public function __construct(private readonly AttendanceResolver $resolver)
    {
    }

    public function index(Request $request): View
    {
        $status = $request->string('status')->toString() ?: 'pending';
        $perPage = min(max((int) $request->input('per_page', 15), 10), 100);

        $corrections = AttendanceCorrection::query()
            ->with(['employee', 'reviewer'])
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->orderByRaw("status = 'pending' desc")
            ->latest('work_date')
            ->paginate($perPage)
            ->withQueryString();

        return view('attendance.corrections.index', [
            'corrections' => $corrections,
            'status' => $status,
            'pendingCount' => AttendanceCorrection::query()->pending()->count(),
        ]);
    }

    /**
     * Approve: apply the requested times to the resolved attendance for that day.
     */
    public function approve(AttendanceCorrection $correction): RedirectResponse
    {
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

        return redirect()->route('attendance.corrections.index')->with('status', 'Koreksi disetujui & absensi diperbarui.');
    }

    public function reject(Request $request, AttendanceCorrection $correction): RedirectResponse
    {
        abort_unless($correction->isPending(), 403);

        $correction->forceFill([
            'status' => AttendanceCorrection::STATUS_REJECTED,
            'reviewed_by' => auth()->id(),
            'decided_at' => now(),
            'decision_notes' => $request->string('decision_notes')->toString() ?: null,
        ])->save();

        return redirect()->route('attendance.corrections.index')->with('status', 'Koreksi ditolak.');
    }
}
