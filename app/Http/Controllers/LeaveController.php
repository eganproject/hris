<?php

namespace App\Http\Controllers;

use App\Enums\LeaveRequestStatus;
use App\Http\Requests\StoreLeaveRequest;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\LeaveWorkflow;
use App\Support\ApprovalNotifier;
use App\Support\DataScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeaveController extends Controller
{
    public function __construct(private readonly LeaveWorkflow $workflow) {}

    public function index(Request $request): View
    {
        $perPage = min(max((int) $request->input('per_page', 15), 10), 100);

        $scope = DataScope::forAttendance($request->user());

        $leaveRequests = LeaveRequest::query()
            ->with(['employee', 'leaveType', 'supervisor', 'approver'])
            ->tap(fn ($query) => $scope->constrain($query))
            ->when($request->string('status')->toString(), fn ($query, string $status) => $query->where('status', $status))
            ->when($request->string('search')->toString(), function ($query, string $search): void {
                $query->whereHas('employee', fn ($query) => $query->where('full_name', 'like', "%{$search}%"));
            })
            ->latest('start_date')
            ->paginate($perPage)
            ->withQueryString();

        return view('attendance.leave.index', [
            'leaveRequests' => $leaveRequests,
            'filters' => $request->only(['search', 'status']),
            'perPage' => $perPage,
            'statuses' => LeaveRequestStatus::options(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('attendance.leave.create', [
            'employees' => DataScope::forAttendance($request->user())->employees()->active()->orderBy('full_name')->get(),
            'leaveTypes' => LeaveType::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreLeaveRequest $request): RedirectResponse
    {
        $employee = Employee::findOrFail($request->integer('employee_id'));
        DataScope::forAttendance($request->user())->authorize($employee);

        $leave = $this->workflow->submit($employee, $request->validated());

        // Diajukan HR, bukan oleh karyawannya sendiri — beri tahu yang bersangkutan.
        app(ApprovalNotifier::class)->leaveFiledForEmployee($leave);

        return redirect()->route('attendance.leave.index')->with('status', 'Pengajuan cuti/izin berhasil dibuat.');
    }

    /**
     * HR override: advance whichever step the request is currently on. A request
     * that already has a final decision is never touched again — two HR users with
     * the list open at the same time must not both "decide" it.
     */
    public function approve(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        DataScope::forAttendance($request->user())->authorize($leaveRequest->employee);
        abort_unless($leaveRequest->status->isPending(), 403);

        if ($leaveRequest->status === LeaveRequestStatus::PendingSupervisor) {
            $this->workflow->supervisorApprove($leaveRequest, auth()->user());
        } else {
            $this->workflow->hrApprove($leaveRequest, auth()->user());
        }

        return redirect()->route('attendance.leave.index')->with('status', 'Pengajuan disetujui.');
    }

    public function reject(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        DataScope::forAttendance($request->user())->authorize($leaveRequest->employee);
        abort_unless($leaveRequest->status->isPending(), 403);

        $this->workflow->reject($leaveRequest, auth()->user(), $request->string('decision_notes')->toString() ?: null);

        return redirect()->route('attendance.leave.index')->with('status', 'Pengajuan ditolak.');
    }
}
