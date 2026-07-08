<?php

use App\Models\Attendance;
use App\Models\AttendancePunch;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Device;
use App\Models\Employee;
use App\Models\EmployeeDevice;
use App\Models\Holiday;
use App\Models\JobPosition;
use App\Models\LeaveType;
use App\Models\ScheduleAssignment;
use App\Models\SchedulePattern;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Full-app smoke test: every GET page must render without a server error for an
 * authorised user. Guards against runtime errors that unit tests miss (undefined
 * Blade variables, missing relations/methods, mass-assignment regressions).
 */
test('every page renders without a server error', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    foreach (config('rbac.permissions') as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    $user = User::factory()->create();
    $user->givePermissionTo(config('rbac.permissions'));

    // --- Master data -------------------------------------------------------
    $role = Role::findOrCreate('employee', 'web');
    $branch = Branch::query()->create(['code' => 'SBY-01', 'name' => 'Surabaya Office', 'type' => 'office', 'city' => 'Surabaya', 'province' => 'Jawa Timur', 'is_active' => true]);
    $department = Department::query()->create(['code' => 'OPS', 'name' => 'Operasional', 'is_active' => true]);
    $position = JobPosition::query()->create(['default_role_id' => $role->id, 'code' => 'STF', 'name' => 'Staf', 'level' => 'Staff', 'is_active' => true]);
    $position->departments()->attach($department->id, ['is_active' => true]);
    $branch->departments()->attach($department->id, ['is_primary' => true, 'is_active' => true]);

    // Employee linked to the acting user (needed for the self-service pages).
    $employee = Employee::query()->create([
        'user_id' => $user->id, 'branch_id' => $branch->id, 'department_id' => $department->id, 'job_position_id' => $position->id,
        'employee_number' => 'EMP-SMOKE', 'full_name' => 'Smoke Test', 'join_date' => now()->subYear()->toDateString(), 'employment_status' => 'active',
    ]);
    $employee->contracts()->create(['contract_number' => 'CTR-SMOKE', 'contract_type' => 'PKWT', 'start_date' => now()->subYear()->toDateString(), 'end_date' => now()->addMonths(2)->toDateString(), 'status' => 'active']);

    // --- Attendance domain -------------------------------------------------
    $shift = Shift::query()->create(['code' => 'REG', 'name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00', 'break_minutes' => 60, 'is_active' => true]);
    $holiday = Holiday::query()->create(['date' => now()->addMonth()->toDateString(), 'name' => 'Libur Uji', 'is_national' => true]);
    LeaveType::query()->create(['code' => 'CT', 'name' => 'Cuti Tahunan', 'attendance_status' => 'leave', 'is_paid' => true, 'counts_against_balance' => true, 'default_quota_days' => 12, 'is_active' => true]);

    $pattern = SchedulePattern::query()->create(['code' => 'W', 'name' => 'Mingguan', 'type' => 'fixed_weekly', 'cycle_length' => 7, 'is_active' => true]);
    $pattern->days()->create(['day_index' => 1, 'shift_id' => $shift->id]);
    ScheduleAssignment::query()->create(['employee_id' => $employee->id, 'schedule_pattern_id' => $pattern->id, 'start_date' => now()->startOfMonth()->toDateString(), 'end_date' => null]);

    $device = Device::query()->create(['serial_number' => 'SN-SMOKE', 'name' => 'Mesin Uji', 'branch_id' => $branch->id, 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
    EmployeeDevice::query()->create(['employee_id' => $employee->id, 'device_id' => $device->id, 'machine_user_id' => '7']);
    AttendancePunch::query()->create(['device_id' => $device->id, 'machine_user_id' => '999', 'punched_at' => now(), 'status' => 'unmatched', 'dedup_hash' => 'smoke-hash']);
    Attendance::query()->create(['employee_id' => $employee->id, 'work_date' => now()->toDateString(), 'shift_id' => $shift->id, 'status' => 'present', 'clock_in' => now()->setTime(8, 0), 'clock_out' => now()->setTime(17, 0)]);

    // --- Every GET page ----------------------------------------------------
    $urls = [
        route('dashboard'),
        route('employees.index'),
        route('employees.create'),
        route('employees.show', $employee),
        route('employees.edit', $employee),
        route('organization.index'),
        route('organization.branches.index'),
        route('organization.branches.create'),
        route('organization.branches.edit', $branch),
        route('organization.departments.index'),
        route('organization.departments.create'),
        route('organization.departments.edit', $department),
        route('organization.job-positions.index'),
        route('organization.job-positions.create'),
        route('organization.job-positions.edit', $position),
        route('attendance.daily.index'),
        route('attendance.devices.index'),
        route('attendance.devices.monitor'),
        route('attendance.devices.create'),
        route('attendance.devices.edit', $device),
        route('attendance.punches.index'),
        route('attendance.schedule-patterns.index'),
        route('attendance.schedule-patterns.create'),
        route('attendance.schedule-patterns.edit', $pattern),
        route('attendance.schedules.index'),
        route('attendance.schedules.assign'),
        route('attendance.shifts.index'),
        route('attendance.shifts.create'),
        route('attendance.shifts.edit', $shift),
        route('attendance.holidays.index'),
        route('attendance.holidays.create'),
        route('attendance.holidays.edit', $holiday),
        route('attendance.leave.index'),
        route('attendance.leave.create'),
        route('attendance.corrections.index'),
        route('attendance.overtime.index'),
        route('attendance.overtime.recap'),
        route('attendance.swaps.index'),
        route('my-leave.index'),
        route('my-leave.create'),
        route('my-attendance.index'),
        route('my-schedule.index'),
        route('access-control.index'),
    ];

    foreach ($urls as $url) {
        $response = $this->actingAs($user)->get($url);

        expect($response->getStatusCode())
            ->toBeLessThan(400, "URL {$url} returned HTTP {$response->getStatusCode()}");
    }
});
