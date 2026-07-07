<?php

namespace App\Http\Controllers;

use App\Enums\LeaveRequestStatus;
use App\Http\Requests\StoreLeaveRequest;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\LeaveWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeaveController extends Controller
{
    public function __construct(private readonly LeaveWorkflow $workflow)
    {
    }

    public function index(Request $request): View
    {
        $perPage = min(max((int) $request->input('per_page', 15), 10), 100);

        $leaveRequests = LeaveRequest::query()
            ->with(['employee', 'leaveType', 'supervisor', 'approver'])
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

    public function create(): View
    {
        return view('attendance.leave.create', [
            'employees' => Employee::query()->active()->orderBy('full_name')->get(),
            'leaveTypes' => LeaveType::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreLeaveRequest $request): RedirectResponse
    {
        $employee = Employee::findOrFail($request->integer('employee_id'));

        $this->workflow->submit($employee, $request->validated());

        return redirect()->route('attendance.leave.index')->with('status', 'Pengajuan cuti/izin berhasil dibuat.');
    }

    /**
     * HR override: advance whichever step the request is currently on.
     */
    public function approve(LeaveRequest $leaveRequest): RedirectResponse
    {
        if ($leaveRequest->status === LeaveRequestStatus::PendingSupervisor) {
            $this->workflow->supervisorApprove($leaveRequest, auth()->user());
        } elseif ($leaveRequest->status === LeaveRequestStatus::PendingHr) {
            $this->workflow->hrApprove($leaveRequest, auth()->user());
        }

        return redirect()->route('attendance.leave.index')->with('status', 'Pengajuan disetujui.');
    }

    public function reject(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        if ($leaveRequest->status->isPending()) {
            $this->workflow->reject($leaveRequest, auth()->user(), $request->string('decision_notes')->toString() ?: null);
        }

        return redirect()->route('attendance.leave.index')->with('status', 'Pengajuan ditolak.');
    }

    public function destroy(LeaveRequest $leaveRequest): RedirectResponse
    {
        $leaveRequest->delete();

        return redirect()->route('attendance.leave.index')->with('status', 'Pengajuan dihapus.');
    }
}
