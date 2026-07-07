<?php

namespace App\Http\Controllers;

use App\Enums\LeaveRequestStatus;
use App\Http\Requests\StoreMyLeaveRequest;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\LeaveBalanceService;
use App\Services\LeaveWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MyLeaveController extends Controller
{
    public function __construct(
        private readonly LeaveWorkflow $workflow,
        private readonly LeaveBalanceService $balances,
    ) {
    }

    public function index(): View
    {
        $employee = $this->employee();

        return view('attendance.my-leave.index', [
            'myRequests' => LeaveRequest::query()
                ->where('employee_id', $employee->id)
                ->with(['leaveType', 'supervisor', 'approver'])
                ->latest('start_date')
                ->get(),
            'pendingForMe' => LeaveRequest::query()
                ->where('supervisor_id', $employee->id)
                ->where('status', LeaveRequestStatus::PendingSupervisor->value)
                ->with(['employee', 'leaveType'])
                ->latest('start_date')
                ->get(),
            'balances' => $this->balances($employee),
        ]);
    }

    public function create(): View
    {
        $employee = $this->employee();

        return view('attendance.my-leave.create', [
            'leaveTypes' => LeaveType::query()->where('is_active', true)->orderBy('name')->get(),
            'balances' => $this->balances($employee),
        ]);
    }

    public function store(StoreMyLeaveRequest $request): RedirectResponse
    {
        $this->workflow->submit($this->employee(), $request->validated());

        return redirect()->route('my-leave.index')->with('status', 'Pengajuan berhasil dikirim.');
    }

    public function cancel(LeaveRequest $leaveRequest): RedirectResponse
    {
        $employee = $this->employee();

        abort_unless($leaveRequest->employee_id === $employee->id && $leaveRequest->status->isPending(), 403);

        $this->workflow->cancel($leaveRequest);

        return redirect()->route('my-leave.index')->with('status', 'Pengajuan dibatalkan.');
    }

    public function approve(LeaveRequest $leaveRequest): RedirectResponse
    {
        $this->authorizeSupervisor($leaveRequest);

        $this->workflow->supervisorApprove($leaveRequest, auth()->user());

        return redirect()->route('my-leave.index')->with('status', 'Pengajuan bawahan disetujui, diteruskan ke HR.');
    }

    public function reject(LeaveRequest $leaveRequest): RedirectResponse
    {
        $this->authorizeSupervisor($leaveRequest);

        $this->workflow->reject($leaveRequest, auth()->user());

        return redirect()->route('my-leave.index')->with('status', 'Pengajuan bawahan ditolak.');
    }

    private function authorizeSupervisor(LeaveRequest $leaveRequest): void
    {
        abort_unless(
            $leaveRequest->supervisor_id === $this->employee()->id
                && $leaveRequest->status === LeaveRequestStatus::PendingSupervisor,
            403,
        );
    }

    private function employee(): Employee
    {
        $employee = auth()->user()->employee;

        abort_unless($employee, 403, 'Akun Anda belum tertaut ke data karyawan.');

        return $employee;
    }

    /**
     * @return array<int, array{type: LeaveType, quota: int, used: int, remaining: int}>
     */
    private function balances(Employee $employee): array
    {
        $year = now()->year;

        return LeaveType::query()
            ->where('is_active', true)
            ->where('counts_against_balance', true)
            ->orderBy('name')
            ->get()
            ->map(fn (LeaveType $type) => [
                'type' => $type,
                'quota' => $this->balances->quota($employee, $type, $year),
                'used' => $this->balances->used($employee, $type, $year),
                'remaining' => $this->balances->remaining($employee, $type, $year),
            ])
            ->all();
    }
}
