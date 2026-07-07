<?php

namespace App\Services;

use App\Enums\LeaveRequestStatus;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;

/**
 * Two-level leave approval: employee → direct supervisor → HR.
 * If the employee has no supervisor the request starts at the HR step.
 */
class LeaveWorkflow
{
    /**
     * @param  array{leave_type_id:int, start_date:string, end_date:string, reason?:?string}  $data
     */
    public function submit(Employee $employee, array $data): LeaveRequest
    {
        $employee->loadMissing('manager');

        return LeaveRequest::query()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $data['leave_type_id'],
            'supervisor_id' => $employee->manager_id,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'reason' => $data['reason'] ?? null,
            'status' => $employee->manager_id
                ? LeaveRequestStatus::PendingSupervisor
                : LeaveRequestStatus::PendingHr,
        ]);
    }

    public function supervisorApprove(LeaveRequest $request, User $actor): void
    {
        $request->update([
            'status' => LeaveRequestStatus::PendingHr,
            'supervisor_approved_by' => $actor->id,
            'supervisor_decided_at' => now(),
        ]);
    }

    public function hrApprove(LeaveRequest $request, User $actor): void
    {
        $request->update([
            'status' => LeaveRequestStatus::Approved,
            'approved_by' => $actor->id,
            'decided_at' => now(),
        ]);
    }

    public function reject(LeaveRequest $request, User $actor, ?string $notes = null): void
    {
        $atSupervisorStep = $request->status === LeaveRequestStatus::PendingSupervisor;

        $request->update([
            'status' => LeaveRequestStatus::Rejected,
            'decision_notes' => $notes,
            'supervisor_approved_by' => $atSupervisorStep ? $actor->id : $request->supervisor_approved_by,
            'supervisor_decided_at' => $atSupervisorStep ? now() : $request->supervisor_decided_at,
            'approved_by' => $atSupervisorStep ? $request->approved_by : $actor->id,
            'decided_at' => $atSupervisorStep ? $request->decided_at : now(),
        ]);
    }

    public function cancel(LeaveRequest $request): void
    {
        $request->update(['status' => LeaveRequestStatus::Cancelled]);
    }
}
