<?php

namespace App\Http\Controllers\Api\Mobile\Hr;

use App\Enums\LeaveRequestStatus;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\OvertimeApproval;
use App\Models\User;
use App\Services\LeaveWorkflow;
use App\Services\OvertimeWorkflow;
use App\Support\DataScope;
use App\Support\Mobile\Present;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Persetujuan atasan di aplikasi HP: cuti/izin/sakit dan lembur bawahan langsung.
 *
 * HR bisa mengambil alih: pemegang leave.update (cuti) atau overtime.update (lembur)
 * juga melihat pengajuan yang masih menunggu di cakupan timnya (DataScope::forTeam),
 * sama seperti jalan keluar HR di web. Koreksi absensi TIDAK ada di sini: koreksi
 * tetap diputuskan HR dari web. Tidak ada yang bisa memutuskan pengajuannya sendiri.
 */
class ApprovalController extends HrController
{
    public function __construct(
        private readonly LeaveWorkflow $leaves,
        private readonly OvertimeWorkflow $overtimes,
    ) {}

    /** ?type=semua|cuti|izin|sakit|lembur */
    public function index(Request $request): JsonResponse
    {
        $type = $request->string('type')->toString() ?: 'semua';
        $items = $this->pending($request->user())
            ->when($type !== 'semua', fn (Collection $c) => $c->where('category', $type))
            ->values();

        return $this->ok($items, 200, ['meta' => [
            'total' => $items->count(),
            'counts' => $this->pending($request->user())->countBy('category'),
        ]]);
    }

    public function approve(Request $request, string $kind, int $id): JsonResponse
    {
        $user = $request->user();

        if ($kind === 'leave') {
            $leave = LeaveRequest::query()->findOrFail($id);
            $this->authorizeLeave($user, $leave);
            $this->leaves->approve($leave, $user);

            return $this->message('Pengajuan disetujui.');
        }

        abort_unless($kind === 'overtime', 404);

        $overtime = OvertimeApproval::query()->findOrFail($id);
        $asHr = $this->authorizeOvertime($user, $overtime);
        $data = $request->validate(['approved_minutes' => ['nullable', 'integer', 'min:0']]);
        $this->overtimes->approve($overtime, $user, $data['approved_minutes'] ?? null, $asHr ? 'HR' : 'atasan');

        return $this->message('Lembur disetujui.');
    }

    public function reject(Request $request, string $kind, int $id): JsonResponse
    {
        $user = $request->user();
        $notes = $request->validate(['notes' => ['nullable', 'string', 'max:500']])['notes'] ?? null;

        if ($kind === 'leave') {
            $leave = LeaveRequest::query()->findOrFail($id);
            $this->authorizeLeave($user, $leave);
            $this->leaves->reject($leave, $user, $notes);

            return $this->message('Pengajuan ditolak.');
        }

        abort_unless($kind === 'overtime', 404);

        $overtime = OvertimeApproval::query()->findOrFail($id);
        $asHr = $this->authorizeOvertime($user, $overtime);
        $this->overtimes->reject($overtime, $user, $notes, $asHr ? 'HR' : 'atasan');

        return $this->message('Lembur ditolak.');
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function pending(User $user): Collection
    {
        $me = $user->employee;
        $meId = $me?->id ?? 0;
        $hrLeave = $user->can('leave.update');
        $hrOvertime = $user->can('overtime.update');

        abort_unless($me?->subordinates()->exists() || $hrLeave || $hrOvertime, 403, 'Menu ini hanya untuk atasan yang punya anggota tim.');

        $scope = DataScope::forTeam($user);

        $leaves = LeaveRequest::query()
            ->where('employee_id', '!=', $meId)
            ->where(function ($q) use ($meId, $hrLeave, $scope): void {
                $q->where(fn ($q) => $q->where('supervisor_id', $meId)->where('status', LeaveRequestStatus::PendingSupervisor->value));

                if ($hrLeave) {
                    $q->orWhere(function ($q) use ($scope): void {
                        $q->whereIn('status', [LeaveRequestStatus::PendingSupervisor->value, LeaveRequestStatus::PendingHr->value]);
                        $scope->constrain($q);
                    });
                }
            })
            ->with(['employee.jobPosition', 'employee.department', 'leaveType'])
            ->orderBy('start_date')
            ->get()
            ->map(fn (LeaveRequest $leave) => [
                ...Present::leave($leave),
                'employee' => Present::person($leave->employee),
                'as_hr' => $leave->supervisor_id !== $meId,
            ]);

        $overtimes = OvertimeApproval::query()
            ->where('employee_id', '!=', $meId)
            ->where('status', OvertimeApproval::STATUS_PENDING)
            ->where(function ($q) use ($meId, $hrOvertime, $scope): void {
                $q->where('supervisor_id', $meId);

                if ($hrOvertime) {
                    // whereNotNull menjaga grupnya tetap ada bila cakupan HR tak
                    // terbatas (constrain() lalu tidak menambah syarat apa pun).
                    $q->orWhere(function ($q) use ($scope): void {
                        $q->whereNotNull('employee_id');
                        $scope->constrain($q);
                    });
                }
            })
            ->with(['employee.jobPosition', 'employee.department'])
            ->orderBy('work_date')
            ->get()
            ->map(fn (OvertimeApproval $o) => [
                ...Present::overtime($o),
                'employee' => Present::person($o->employee),
                'as_hr' => $o->supervisor_id !== $meId,
            ]);

        return $leaves->concat($overtimes)->values();
    }

    private function authorizeLeave(User $user, LeaveRequest $leave): void
    {
        $me = $user->employee;
        abort_if($me && $leave->employee_id === $me->id, 403, 'Anda tidak bisa memutuskan pengajuan Anda sendiri.');
        abort_unless($leave->status->isPending(), 409, 'Pengajuan ini sudah diputuskan.');

        $isSupervisor = $me && $leave->supervisor_id === $me->id && $leave->status === LeaveRequestStatus::PendingSupervisor;
        $isHr = $user->can('leave.update') && DataScope::forTeam($user)->allows($leave->employee);

        abort_unless($isSupervisor || $isHr, 403);
    }

    /** @return bool true bila diputuskan sebagai HR (bukan atasan langsung) */
    private function authorizeOvertime(User $user, OvertimeApproval $overtime): bool
    {
        $me = $user->employee;
        abort_if($me && $overtime->employee_id === $me->id, 403, 'Anda tidak bisa memutuskan pengajuan lembur Anda sendiri.');
        abort_unless($overtime->status === OvertimeApproval::STATUS_PENDING, 409, 'Pengajuan ini sudah diputuskan.');

        if ($me && $overtime->supervisor_id === $me->id) {
            return false;
        }

        abort_unless(
            $user->can('overtime.update') && DataScope::forTeam($user)->allows(Employee::find($overtime->employee_id)),
            403,
        );

        return true;
    }
}
