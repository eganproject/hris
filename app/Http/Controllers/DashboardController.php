<?php

namespace App\Http\Controllers;

use App\Enums\LeaveRequestStatus;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\JobPosition;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OvertimeApproval;
use App\Models\Shift;
use App\Models\ShiftSwapRequest;
use App\Services\LeaveBalanceService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly LeaveBalanceService $balances) {}

    public function __invoke(): View
    {
        $user = auth()->user();

        return view('dashboard', [
            'metrics' => collect([
                ['label' => 'Karyawan Aktif', 'value' => Employee::query()->active()->count(), 'tone' => 'emerald', 'icon' => 'user-check', 'permission' => 'employees.view'],
                ['label' => 'Departments', 'value' => Department::query()->count(), 'tone' => 'sky', 'icon' => 'layers', 'permission' => 'organization.view'],
                ['label' => 'Lokasi Kerja', 'value' => Branch::query()->where('is_active', true)->count(), 'tone' => 'amber', 'icon' => 'map-pin', 'permission' => 'organization.view'],
                ['label' => 'Kontrak Habis 30 Hari', 'value' => EmployeeContract::query()->expiringWithin(30)->count(), 'tone' => 'rose', 'icon' => 'calendar-clock', 'permission' => 'employees.view'],
            ])->filter(fn (array $metric) => $user?->can($metric['permission']))->values(),
            'modules' => collect([
                ['name' => 'Employee', 'description' => 'People records, contracts, and assignments.', 'count' => Employee::query()->count(), 'permission' => 'employees.view', 'route' => 'employees.index'],
                ['name' => 'Department', 'description' => 'Organization units and reporting groups.', 'count' => Department::query()->count(), 'permission' => 'organization.view', 'route' => 'organization.departments.index'],
                ['name' => 'Branch', 'description' => 'Office locations, warehouses, and operational areas.', 'count' => Branch::query()->count(), 'permission' => 'organization.view', 'route' => 'organization.branches.index'],
                ['name' => 'Shift', 'description' => 'Work schedules and shift templates.', 'count' => Shift::query()->count(), 'permission' => 'attendance.view', 'route' => 'attendance.shifts.index'],
                ['name' => 'Job Position', 'description' => 'Roles, levels, and position catalog.', 'count' => JobPosition::query()->count(), 'permission' => 'organization.view', 'route' => 'organization.job-positions.index'],
                ['name' => 'Attendance', 'description' => 'Daily clock activity and attendance history.', 'count' => AttendanceRecord::query()->count(), 'permission' => 'attendance.view', 'route' => null],
            ])->filter(fn (array $module) => $user?->can($module['permission']))->values(),
            'personal' => $user?->employee ? $this->personalData($user->employee) : null,
        ]);
    }

    /**
     * Self-service snapshot for an employee: leave balances, upcoming schedule, and
     * how many requests are in flight or waiting on them as a supervisor.
     *
     * @return array<string, mixed>
     */
    private function personalData(Employee $employee): array
    {
        $year = (int) now()->year;
        $employee->loadMissing(['department', 'jobPosition']);

        return [
            'employee' => $employee,
            'balances' => LeaveType::query()
                ->where('is_active', true)
                ->where('counts_against_balance', true)
                ->orderBy('name')
                ->get()
                ->map(fn (LeaveType $type) => [
                    'name' => $type->name,
                    'remaining' => $this->balances->remaining($employee, $type, $year),
                ]),
            'schedule' => $employee->schedules()
                ->whereBetween('work_date', [now()->toDateString(), now()->addDays(6)->toDateString()])
                ->with('shift')
                ->orderBy('work_date')
                ->get(),
            'myPending' => LeaveRequest::query()->where('employee_id', $employee->id)
                ->whereIn('status', [LeaveRequestStatus::PendingSupervisor->value, LeaveRequestStatus::PendingHr->value])->count()
                + OvertimeApproval::query()->where('employee_id', $employee->id)->where('status', OvertimeApproval::STATUS_PENDING)->count()
                + $employee->swapRequests()->whereIn('status', [ShiftSwapRequest::STATUS_PENDING_PARTNER, ShiftSwapRequest::STATUS_PENDING_HR])->count()
                + $employee->attendanceCorrections()->where('status', AttendanceCorrection::STATUS_PENDING)->count(),
            'needApproval' => LeaveRequest::query()->where('supervisor_id', $employee->id)->where('status', LeaveRequestStatus::PendingSupervisor->value)->count()
                + OvertimeApproval::query()->where('supervisor_id', $employee->id)->where('status', OvertimeApproval::STATUS_PENDING)->count()
                + $employee->swapRequestsAsPartner()->where('status', ShiftSwapRequest::STATUS_PENDING_PARTNER)->count(),
        ];
    }
}
