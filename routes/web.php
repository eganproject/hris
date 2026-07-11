<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\AccessControlController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceCorrectionController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\EmployeeManagementController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\IclockController;
use App\Http\Controllers\JobPositionController;
use App\Http\Controllers\LeaveBalanceController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\LeaveTypeController;
use App\Http\Controllers\MyAttendanceController;
use App\Http\Controllers\MyLeaveController;
use App\Http\Controllers\MyOvertimeController;
use App\Http\Controllers\MyRosterController;
use App\Http\Controllers\MyScheduleController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OvertimeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\PunchController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SchedulePatternController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\ShiftSwapController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

// ZKTeco iclock push protocol (Solution X100-C). Public, gated by device serial
// allowlist inside the controller; must run over HTTPS in production.
Route::match(['GET', 'POST'], 'iclock/cdata', function (\Illuminate\Http\Request $request, IclockController $controller) {
    return $request->isMethod('post') ? $controller->receive($request) : $controller->handshake($request);
});
Route::get('iclock/getrequest', [IclockController::class, 'getrequest']);
Route::post('iclock/devicecmd', [IclockController::class, 'devicecmd']);

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::get('dashboard', DashboardController::class)
        ->middleware('permission:dashboard.view')
        ->name('dashboard');

    // Self-service account & profile (available to every authenticated user).
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // Self-service: check own work schedule (read-only, no special permission).
    Route::get('jadwal-saya', [MyRosterController::class, 'index'])->name('my-roster.index');

    // App settings (HR/admin toggles).
    Route::get('settings', [SettingsController::class, 'index'])->middleware('permission:attendance.update')->name('settings.index');
    Route::put('settings', [SettingsController::class, 'update'])->middleware('permission:attendance.update')->name('settings.update');

    // In-app notifications (available to every authenticated user).
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::get('count', [NotificationController::class, 'count'])->name('count');
        Route::post('read-all', [NotificationController::class, 'readAll'])->name('read-all');
        Route::get('{id}', [NotificationController::class, 'read'])->name('read');
    });

    // Reports (HR/management). Gated by attendance.view since the data is drawn from
    // attendance records.
    Route::prefix('reports')->name('reports.')->middleware('permission:attendance.view')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');

        Route::get('attendance', [ReportController::class, 'attendance'])->name('attendance');
        Route::get('attendance/export', [ReportController::class, 'attendanceExport'])->name('attendance.export');
        Route::get('attendance/pdf', [ReportController::class, 'attendancePdf'])->name('attendance.pdf');
        Route::get('attendance/{employee}', [ReportController::class, 'employeeAttendance'])->name('attendance.detail');

        Route::get('leave', [ReportController::class, 'leave'])->name('leave');
        Route::get('leave/export', [ReportController::class, 'leaveExport'])->name('leave.export');
        Route::get('leave/pdf', [ReportController::class, 'leavePdf'])->name('leave.pdf');
        Route::get('leave/{employee}', [ReportController::class, 'employeeLeave'])->name('leave.detail');
    });

    Route::prefix('employees')->name('employees.')->group(function () {
        Route::get('/', [EmployeeManagementController::class, 'index'])
            ->middleware('permission:employees.view')
            ->name('index');
        Route::get('create', [EmployeeManagementController::class, 'create'])
            ->middleware('permission:employees.create')
            ->name('create');
        Route::post('/', [EmployeeManagementController::class, 'store'])
            ->middleware('permission:employees.create')
            ->name('store');
        // Import/export must be declared before the "{employee}" wildcard so the
        // literal segments are not captured as a route-model-bound employee.
        Route::get('import/template', [EmployeeManagementController::class, 'importTemplate'])
            ->middleware('permission:employees.create')
            ->name('import.template');
        Route::post('import', [EmployeeManagementController::class, 'import'])
            ->middleware('permission:employees.create')
            ->name('import');
        Route::get('import/errors/{token}', [EmployeeManagementController::class, 'importErrors'])
            ->middleware('permission:employees.create')
            ->name('import.errors');
        Route::get('export', [EmployeeManagementController::class, 'export'])
            ->middleware('permission:employees.view')
            ->name('export');
        // Bulk actions on selected rows (checklist): declared before the "{employee}"
        // wildcard so the literal segments are not captured as an employee.
        Route::post('bulk/exit', [EmployeeManagementController::class, 'bulkExit'])
            ->middleware('permission:employees.update')
            ->name('bulk.exit');
        Route::post('bulk/renew', [EmployeeManagementController::class, 'bulkRenew'])
            ->middleware('permission:employees.update')
            ->name('bulk.renew');
        Route::post('bulk/delete', [EmployeeManagementController::class, 'bulkDestroy'])
            ->middleware('permission:employees.delete')
            ->name('bulk.destroy');
        Route::get('{employee}', [EmployeeManagementController::class, 'show'])
            ->middleware('permission:employees.view')
            ->name('show');
        Route::get('{employee}/edit', [EmployeeManagementController::class, 'edit'])
            ->middleware('permission:employees.update')
            ->name('edit');
        Route::patch('{employee}/resign', [EmployeeManagementController::class, 'resign'])
            ->middleware('permission:employees.update')
            ->name('resign');
        Route::post('{employee}/renew-contract', [EmployeeManagementController::class, 'renewContract'])
            ->middleware('permission:employees.update')
            ->name('renew-contract');
        Route::put('{employee}', [EmployeeManagementController::class, 'update'])
            ->middleware('permission:employees.update')
            ->name('update');
        Route::delete('{employee}', [EmployeeManagementController::class, 'destroy'])
            ->middleware('permission:employees.delete')
            ->name('destroy');
    });

    Route::get('organization', OrganizationController::class)
        ->middleware('permission:organization.view')
        ->name('organization.index');

    Route::prefix('organization')->name('organization.')->group(function () {
        Route::get('branches', [BranchController::class, 'index'])->middleware('permission:organization.view')->name('branches.index');
        Route::get('branches/create', [BranchController::class, 'create'])->middleware('permission:organization.create')->name('branches.create');
        Route::post('branches', [BranchController::class, 'store'])->middleware('permission:organization.create')->name('branches.store');
        Route::get('branches/{branch}/edit', [BranchController::class, 'edit'])->middleware('permission:organization.update')->name('branches.edit');
        Route::put('branches/{branch}', [BranchController::class, 'update'])->middleware('permission:organization.update')->name('branches.update');
        Route::delete('branches/{branch}', [BranchController::class, 'destroy'])->middleware('permission:organization.delete')->name('branches.destroy');

        Route::get('departments', [DepartmentController::class, 'index'])->middleware('permission:organization.view')->name('departments.index');
        Route::get('departments/create', [DepartmentController::class, 'create'])->middleware('permission:organization.create')->name('departments.create');
        Route::post('departments', [DepartmentController::class, 'store'])->middleware('permission:organization.create')->name('departments.store');
        Route::get('departments/{department}/edit', [DepartmentController::class, 'edit'])->middleware('permission:organization.update')->name('departments.edit');
        Route::put('departments/{department}', [DepartmentController::class, 'update'])->middleware('permission:organization.update')->name('departments.update');
        Route::delete('departments/{department}', [DepartmentController::class, 'destroy'])->middleware('permission:organization.delete')->name('departments.destroy');

        Route::get('job-positions', [JobPositionController::class, 'index'])->middleware('permission:organization.view')->name('job-positions.index');
        Route::get('job-positions/create', [JobPositionController::class, 'create'])->middleware('permission:organization.create')->name('job-positions.create');
        Route::post('job-positions', [JobPositionController::class, 'store'])->middleware('permission:organization.create')->name('job-positions.store');
        Route::get('job-positions/{jobPosition}/edit', [JobPositionController::class, 'edit'])->middleware('permission:organization.update')->name('job-positions.edit');
        Route::put('job-positions/{jobPosition}', [JobPositionController::class, 'update'])->middleware('permission:organization.update')->name('job-positions.update');
        Route::delete('job-positions/{jobPosition}', [JobPositionController::class, 'destroy'])->middleware('permission:organization.delete')->name('job-positions.destroy');
    });

    Route::prefix('attendance')->name('attendance.')->group(function () {
        Route::get('shifts', [ShiftController::class, 'index'])->middleware('permission:attendance.view')->name('shifts.index');
        Route::get('shifts/create', [ShiftController::class, 'create'])->middleware('permission:attendance.create')->name('shifts.create');
        Route::post('shifts', [ShiftController::class, 'store'])->middleware('permission:attendance.create')->name('shifts.store');
        Route::get('shifts/{shift}/edit', [ShiftController::class, 'edit'])->middleware('permission:attendance.update')->name('shifts.edit');
        Route::put('shifts/{shift}', [ShiftController::class, 'update'])->middleware('permission:attendance.update')->name('shifts.update');
        Route::delete('shifts/{shift}', [ShiftController::class, 'destroy'])->middleware('permission:attendance.delete')->name('shifts.destroy');

        Route::get('holidays', [HolidayController::class, 'index'])->middleware('permission:attendance.view')->name('holidays.index');
        Route::get('holidays/create', [HolidayController::class, 'create'])->middleware('permission:attendance.create')->name('holidays.create');
        Route::post('holidays', [HolidayController::class, 'store'])->middleware('permission:attendance.create')->name('holidays.store');
        Route::get('holidays/{holiday}/edit', [HolidayController::class, 'edit'])->middleware('permission:attendance.update')->name('holidays.edit');
        Route::put('holidays/{holiday}', [HolidayController::class, 'update'])->middleware('permission:attendance.update')->name('holidays.update');
        Route::delete('holidays/{holiday}', [HolidayController::class, 'destroy'])->middleware('permission:attendance.delete')->name('holidays.destroy');

        Route::get('devices', [DeviceController::class, 'index'])->middleware('permission:attendance.view')->name('devices.index');
        Route::get('devices/monitor', [DeviceController::class, 'monitor'])->middleware('permission:attendance.view')->name('devices.monitor');
        Route::get('devices/create', [DeviceController::class, 'create'])->middleware('permission:attendance.create')->name('devices.create');
        Route::post('devices', [DeviceController::class, 'store'])->middleware('permission:attendance.create')->name('devices.store');
        Route::get('devices/{device}/edit', [DeviceController::class, 'edit'])->middleware('permission:attendance.update')->name('devices.edit');
        Route::put('devices/{device}', [DeviceController::class, 'update'])->middleware('permission:attendance.update')->name('devices.update');
        Route::delete('devices/{device}', [DeviceController::class, 'destroy'])->middleware('permission:attendance.delete')->name('devices.destroy');
        Route::post('devices/{device}/commands', [DeviceController::class, 'command'])->middleware('permission:attendance.update')->name('devices.commands.store');
        Route::post('devices/{device}/mappings', [DeviceController::class, 'storeMapping'])->middleware('permission:attendance.update')->name('devices.mappings.store');
        Route::delete('devices/mappings/{mapping}', [DeviceController::class, 'destroyMapping'])->middleware('permission:attendance.update')->name('devices.mappings.destroy');

        Route::get('punches', [PunchController::class, 'index'])->middleware('permission:attendance.view')->name('punches.index');
        Route::post('punches/assign', [PunchController::class, 'assign'])->middleware('permission:attendance.update')->name('punches.assign');
        Route::post('punches/{punch}/ignore', [PunchController::class, 'ignore'])->middleware('permission:attendance.update')->name('punches.ignore');

        Route::get('daily', [AttendanceController::class, 'index'])->middleware('permission:attendance.view')->name('daily.index');
        Route::post('daily/process', [AttendanceController::class, 'process'])->middleware('permission:attendance.update')->name('daily.process');
        Route::post('daily/punch', [AttendanceController::class, 'storePunch'])->middleware('permission:attendance.update')->name('daily.punch');

        Route::get('corrections', [AttendanceCorrectionController::class, 'index'])->middleware('permission:attendance.view')->name('corrections.index');
        Route::patch('corrections/{correction}/approve', [AttendanceCorrectionController::class, 'approve'])->middleware('permission:attendance.update')->name('corrections.approve');
        Route::patch('corrections/{correction}/reject', [AttendanceCorrectionController::class, 'reject'])->middleware('permission:attendance.update')->name('corrections.reject');

        // Overtime is submitted by employees and approved by their supervisor (see the
        // my-overtime routes below). HR only monitors and recaps the approved totals.
        Route::get('overtime', [OvertimeController::class, 'index'])->middleware('permission:attendance.view')->name('overtime.index');
        Route::get('overtime/recap', [OvertimeController::class, 'recap'])->middleware('permission:attendance.view')->name('overtime.recap');

        Route::get('swaps', [ShiftSwapController::class, 'index'])->middleware('permission:attendance.view')->name('swaps.index');
        Route::patch('swaps/{swap}/approve', [ShiftSwapController::class, 'approve'])->middleware('permission:attendance.update')->name('swaps.approve');
        Route::patch('swaps/{swap}/reject', [ShiftSwapController::class, 'reject'])->middleware('permission:attendance.update')->name('swaps.reject');

        Route::get('schedule-patterns', [SchedulePatternController::class, 'index'])->middleware('permission:attendance.view')->name('schedule-patterns.index');
        Route::get('schedule-patterns/create', [SchedulePatternController::class, 'create'])->middleware('permission:attendance.create')->name('schedule-patterns.create');
        Route::post('schedule-patterns', [SchedulePatternController::class, 'store'])->middleware('permission:attendance.create')->name('schedule-patterns.store');
        Route::get('schedule-patterns/{schedulePattern}/edit', [SchedulePatternController::class, 'edit'])->middleware('permission:attendance.update')->name('schedule-patterns.edit');
        Route::put('schedule-patterns/{schedulePattern}', [SchedulePatternController::class, 'update'])->middleware('permission:attendance.update')->name('schedule-patterns.update');
        Route::delete('schedule-patterns/{schedulePattern}', [SchedulePatternController::class, 'destroy'])->middleware('permission:attendance.delete')->name('schedule-patterns.destroy');

        Route::get('schedules', [ScheduleController::class, 'index'])->middleware('permission:attendance.view')->name('schedules.index');
        Route::get('schedules/assign', [ScheduleController::class, 'create'])->middleware('permission:attendance.create')->name('schedules.assign');
        Route::post('schedules/assign', [ScheduleController::class, 'store'])->middleware('permission:attendance.create')->name('schedules.store');
        Route::post('schedules/generate', [ScheduleController::class, 'generate'])->middleware('permission:attendance.update')->name('schedules.generate');
        Route::post('schedules/override', [ScheduleController::class, 'override'])->middleware('permission:attendance.update')->name('schedules.override');
        Route::delete('schedules/assignments/{assignment}', [ScheduleController::class, 'destroyAssignment'])->middleware('permission:attendance.delete')->name('schedules.assignments.destroy');

        Route::get('leave', [LeaveController::class, 'index'])->middleware('permission:attendance.view')->name('leave.index');
        Route::get('leave/create', [LeaveController::class, 'create'])->middleware('permission:attendance.create')->name('leave.create');
        Route::post('leave', [LeaveController::class, 'store'])->middleware('permission:attendance.create')->name('leave.store');
        Route::patch('leave/{leaveRequest}/approve', [LeaveController::class, 'approve'])->middleware('permission:attendance.update')->name('leave.approve');
        Route::patch('leave/{leaveRequest}/reject', [LeaveController::class, 'reject'])->middleware('permission:attendance.update')->name('leave.reject');
        Route::delete('leave/{leaveRequest}', [LeaveController::class, 'destroy'])->middleware('permission:attendance.delete')->name('leave.destroy');

        // Master data cuti: jenis cuti + kuota per karyawan. Rute literal
        // (create, leave-balances) dideklarasikan sebelum wildcard {leaveType}.
        Route::get('leave-types', [LeaveTypeController::class, 'index'])->middleware('permission:attendance.view')->name('leave-types.index');
        Route::get('leave-types/create', [LeaveTypeController::class, 'create'])->middleware('permission:attendance.create')->name('leave-types.create');
        Route::post('leave-types', [LeaveTypeController::class, 'store'])->middleware('permission:attendance.create')->name('leave-types.store');
        Route::get('leave-balances', [LeaveBalanceController::class, 'index'])->middleware('permission:attendance.view')->name('leave-balances.index');
        Route::put('leave-balances', [LeaveBalanceController::class, 'update'])->middleware('permission:attendance.update')->name('leave-balances.update');
        Route::get('leave-types/{leaveType}/edit', [LeaveTypeController::class, 'edit'])->middleware('permission:attendance.update')->name('leave-types.edit');
        Route::put('leave-types/{leaveType}', [LeaveTypeController::class, 'update'])->middleware('permission:attendance.update')->name('leave-types.update');
        Route::delete('leave-types/{leaveType}', [LeaveTypeController::class, 'destroy'])->middleware('permission:attendance.delete')->name('leave-types.destroy');
    });

    // Employee self-service: request own leave, and (as a supervisor) approve subordinates.
    Route::prefix('my-leave')->name('my-leave.')->middleware('permission:leave.request')->group(function () {
        Route::get('/', [MyLeaveController::class, 'index'])->name('index');
        Route::get('create', [MyLeaveController::class, 'create'])->name('create');
        Route::post('/', [MyLeaveController::class, 'store'])->name('store');
        Route::patch('{leaveRequest}/cancel', [MyLeaveController::class, 'cancel'])->name('cancel');
        Route::patch('{leaveRequest}/approve', [MyLeaveController::class, 'approve'])->name('approve');
        Route::patch('{leaveRequest}/reject', [MyLeaveController::class, 'reject'])->name('reject');
    });

    // Employee self-service: view own attendance and request corrections.
    Route::prefix('my-attendance')->name('my-attendance.')->middleware('permission:attendance.correction')->group(function () {
        Route::get('/', [MyAttendanceController::class, 'index'])->name('index');
        Route::post('corrections', [MyAttendanceController::class, 'store'])->name('corrections.store');
        Route::delete('corrections/{correction}', [MyAttendanceController::class, 'cancel'])->name('corrections.cancel');
    });

    // Employee self-service: view own schedule and request shift swaps.
    Route::prefix('my-schedule')->name('my-schedule.')->middleware('permission:schedule.swap')->group(function () {
        Route::get('/', [MyScheduleController::class, 'index'])->name('index');
        Route::post('swaps', [MyScheduleController::class, 'store'])->name('swaps.store');
        Route::patch('swaps/{swap}/respond', [MyScheduleController::class, 'respond'])->name('swaps.respond');
        Route::delete('swaps/{swap}', [MyScheduleController::class, 'cancel'])->name('swaps.cancel');
    });

    // Employee self-service: submit own overtime, and (as a supervisor) approve
    // subordinates' overtime requests.
    Route::prefix('my-overtime')->name('my-overtime.')->middleware('permission:overtime.request')->group(function () {
        Route::get('/', [MyOvertimeController::class, 'index'])->name('index');
        Route::post('/', [MyOvertimeController::class, 'store'])->name('store');
        Route::delete('{overtime}', [MyOvertimeController::class, 'cancel'])->name('cancel');
        Route::patch('{overtime}/approve', [MyOvertimeController::class, 'approve'])->name('approve');
        Route::patch('{overtime}/reject', [MyOvertimeController::class, 'reject'])->name('reject');
    });

    Route::prefix('access-control')->name('access-control.')->group(function () {
        Route::get('/', [AccessControlController::class, 'index'])
            ->middleware('permission:access-control.view')
            ->name('index');
        Route::put('roles/{role}', [AccessControlController::class, 'updateRole'])
            ->middleware('permission:access-control.update')
            ->name('roles.update');
        Route::put('job-positions/{jobPosition}', [AccessControlController::class, 'updateJobPosition'])
            ->middleware('permission:access-control.update')
            ->name('job-positions.update');
        Route::put('branches/{branch}/departments', [AccessControlController::class, 'updateBranchDepartments'])
            ->middleware('permission:access-control.update')
            ->name('branches.departments.update');
    });

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
