<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\JobPosition;
use App\Models\Shift;
use Illuminate\View\View;

class DashboardController extends Controller
{
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
                ['name' => 'Payroll', 'description' => 'Salary, payroll runs, and confidential compensation data.', 'count' => 0, 'permission' => 'payroll.view', 'route' => 'payroll.index'],
                ['name' => 'Department', 'description' => 'Organization units and reporting groups.', 'count' => Department::query()->count(), 'permission' => 'organization.view', 'route' => 'organization.departments.index'],
                ['name' => 'Branch', 'description' => 'Office locations, warehouses, and operational areas.', 'count' => Branch::query()->count(), 'permission' => 'organization.view', 'route' => 'organization.branches.index'],
                ['name' => 'Shift', 'description' => 'Work schedules and shift templates.', 'count' => Shift::query()->count(), 'permission' => 'attendance.view', 'route' => 'attendance.shifts.index'],
                ['name' => 'Job Position', 'description' => 'Roles, levels, and position catalog.', 'count' => JobPosition::query()->count(), 'permission' => 'organization.view', 'route' => 'organization.job-positions.index'],
                ['name' => 'Attendance', 'description' => 'Daily clock activity and attendance history.', 'count' => AttendanceRecord::query()->count(), 'permission' => 'attendance.view', 'route' => null],
            ])->filter(fn (array $module) => $user?->can($module['permission']))->values(),
        ]);
    }
}
