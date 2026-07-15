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
use App\Http\Controllers\SearchController;
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

    // Global quick-search (command palette): finds employees within the user's scope.
    Route::get('search', SearchController::class)->middleware('permission:employees.view')->name('search');

    // App settings (HR/admin toggles).
    Route::get('settings', [SettingsController::class, 'index'])->middleware('permission:settings.view')->name('settings.index');
    Route::put('settings', [SettingsController::class, 'update'])->middleware('permission:settings.update')->name('settings.update');

    // In-app notifications (available to every authenticated user).
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::get('count', [NotificationController::class, 'count'])->name('count');
        Route::post('read-all', [NotificationController::class, 'readAll'])->name('read-all');
        Route::get('{id}', [NotificationController::class, 'read'])->name('read');
    });

    // Reports (HR/management). Gated by attendance.view since the data is drawn from
    // attendance records.
    Route::prefix('reports')->name('reports.')->group(function () {
        // Halaman indeks cukup dibuka bila salah satu laporan boleh dilihat.
        Route::get('/', [ReportController::class, 'index'])
            ->middleware('permission:reports.attendance.view|reports.log.view|reports.leave.view')
            ->name('index');

        Route::get('attendance', [ReportController::class, 'attendance'])->middleware('permission:reports.attendance.view')->name('attendance');
        Route::get('attendance/export', [ReportController::class, 'attendanceExport'])->middleware('permission:reports.attendance.export')->name('attendance.export');
        Route::get('attendance/pdf', [ReportController::class, 'attendancePdf'])->middleware('permission:reports.attendance.export')->name('attendance.pdf');

        // Daily attendance log with clock-in/out times (separate prefix so it isn't
        // captured by the attendance/{employee} wildcard below).
        Route::get('attendance-log', [ReportController::class, 'attendanceLog'])->middleware('permission:reports.log.view')->name('attendance-log');
        Route::get('attendance-log/export', [ReportController::class, 'attendanceLogExport'])->middleware('permission:reports.log.export')->name('attendance-log.export');
        Route::get('attendance-log/pdf', [ReportController::class, 'attendanceLogPdf'])->middleware('permission:reports.log.export')->name('attendance-log.pdf');

        Route::get('attendance/{employee}', [ReportController::class, 'employeeAttendance'])->middleware('permission:reports.attendance.view')->name('attendance.detail');

        Route::get('leave', [ReportController::class, 'leave'])->middleware('permission:reports.leave.view')->name('leave');
        Route::get('leave/export', [ReportController::class, 'leaveExport'])->middleware('permission:reports.leave.export')->name('leave.export');
        Route::get('leave/pdf', [ReportController::class, 'leavePdf'])->middleware('permission:reports.leave.export')->name('leave.pdf');
        Route::get('leave/{employee}', [ReportController::class, 'employeeLeave'])->middleware('permission:reports.leave.view')->name('leave.detail');
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
            ->middleware('permission:employees.import')
            ->name('import.template');
        Route::post('import', [EmployeeManagementController::class, 'import'])
            ->middleware('permission:employees.import')
            ->name('import');
        Route::get('import/errors/{token}', [EmployeeManagementController::class, 'importErrors'])
            ->middleware('permission:employees.import')
            ->name('import.errors');
        Route::get('export', [EmployeeManagementController::class, 'export'])
            ->middleware('permission:employees.export')
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
        Route::get('branches', [BranchController::class, 'index'])->middleware('permission:branches.view')->name('branches.index');
        Route::get('branches/create', [BranchController::class, 'create'])->middleware('permission:branches.create')->name('branches.create');
        Route::post('branches', [BranchController::class, 'store'])->middleware('permission:branches.create')->name('branches.store');
        Route::get('branches/{branch}/edit', [BranchController::class, 'edit'])->middleware('permission:branches.update')->name('branches.edit');
        Route::put('branches/{branch}', [BranchController::class, 'update'])->middleware('permission:branches.update')->name('branches.update');
        Route::delete('branches/{branch}', [BranchController::class, 'destroy'])->middleware('permission:branches.delete')->name('branches.destroy');

        Route::get('departments', [DepartmentController::class, 'index'])->middleware('permission:departments.view')->name('departments.index');
        Route::get('departments/create', [DepartmentController::class, 'create'])->middleware('permission:departments.create')->name('departments.create');
        Route::post('departments', [DepartmentController::class, 'store'])->middleware('permission:departments.create')->name('departments.store');
        Route::get('departments/{department}/edit', [DepartmentController::class, 'edit'])->middleware('permission:departments.update')->name('departments.edit');
        Route::put('departments/{department}', [DepartmentController::class, 'update'])->middleware('permission:departments.update')->name('departments.update');
        Route::delete('departments/{department}', [DepartmentController::class, 'destroy'])->middleware('permission:departments.delete')->name('departments.destroy');

        Route::get('job-positions', [JobPositionController::class, 'index'])->middleware('permission:job-positions.view')->name('job-positions.index');
        Route::get('job-positions/create', [JobPositionController::class, 'create'])->middleware('permission:job-positions.create')->name('job-positions.create');
        Route::post('job-positions', [JobPositionController::class, 'store'])->middleware('permission:job-positions.create')->name('job-positions.store');
        Route::get('job-positions/{jobPosition}/edit', [JobPositionController::class, 'edit'])->middleware('permission:job-positions.update')->name('job-positions.edit');
        Route::put('job-positions/{jobPosition}', [JobPositionController::class, 'update'])->middleware('permission:job-positions.update')->name('job-positions.update');
        Route::delete('job-positions/{jobPosition}', [JobPositionController::class, 'destroy'])->middleware('permission:job-positions.delete')->name('job-positions.destroy');
    });

    Route::prefix('attendance')->name('attendance.')->group(function () {
        Route::get('shifts', [ShiftController::class, 'index'])->middleware('permission:shifts.view')->name('shifts.index');
        Route::get('shifts/create', [ShiftController::class, 'create'])->middleware('permission:shifts.create')->name('shifts.create');
        Route::post('shifts', [ShiftController::class, 'store'])->middleware('permission:shifts.create')->name('shifts.store');
        Route::get('shifts/{shift}/edit', [ShiftController::class, 'edit'])->middleware('permission:shifts.update')->name('shifts.edit');
        Route::put('shifts/{shift}', [ShiftController::class, 'update'])->middleware('permission:shifts.update')->name('shifts.update');
        Route::delete('shifts/{shift}', [ShiftController::class, 'destroy'])->middleware('permission:shifts.delete')->name('shifts.destroy');

        Route::get('holidays', [HolidayController::class, 'index'])->middleware('permission:holidays.view')->name('holidays.index');
        Route::get('holidays/create', [HolidayController::class, 'create'])->middleware('permission:holidays.create')->name('holidays.create');
        Route::post('holidays', [HolidayController::class, 'store'])->middleware('permission:holidays.create')->name('holidays.store');
        Route::get('holidays/{holiday}/edit', [HolidayController::class, 'edit'])->middleware('permission:holidays.update')->name('holidays.edit');
        Route::put('holidays/{holiday}', [HolidayController::class, 'update'])->middleware('permission:holidays.update')->name('holidays.update');
        Route::delete('holidays/{holiday}', [HolidayController::class, 'destroy'])->middleware('permission:holidays.delete')->name('holidays.destroy');

        Route::get('devices', [DeviceController::class, 'index'])->middleware('permission:devices.view')->name('devices.index');
        Route::get('devices/monitor', [DeviceController::class, 'monitor'])->middleware('permission:devices.view')->name('devices.monitor');
        Route::get('devices/create', [DeviceController::class, 'create'])->middleware('permission:devices.create')->name('devices.create');
        Route::post('devices', [DeviceController::class, 'store'])->middleware('permission:devices.create')->name('devices.store');
        Route::get('devices/{device}/edit', [DeviceController::class, 'edit'])->middleware('permission:devices.update')->name('devices.edit');
        Route::put('devices/{device}', [DeviceController::class, 'update'])->middleware('permission:devices.update')->name('devices.update');
        Route::delete('devices/{device}', [DeviceController::class, 'destroy'])->middleware('permission:devices.delete')->name('devices.destroy');
        Route::post('devices/{device}/commands', [DeviceController::class, 'command'])->middleware('permission:devices.update')->name('devices.commands.store');
        Route::post('devices/{device}/mappings', [DeviceController::class, 'storeMapping'])->middleware('permission:devices.update')->name('devices.mappings.store');
        Route::delete('devices/mappings/{mapping}', [DeviceController::class, 'destroyMapping'])->middleware('permission:devices.update')->name('devices.mappings.destroy');

        Route::get('punches', [PunchController::class, 'index'])->middleware('permission:punches.view')->name('punches.index');
        Route::post('punches/assign', [PunchController::class, 'assign'])->middleware('permission:punches.update')->name('punches.assign');
        Route::post('punches/{punch}/ignore', [PunchController::class, 'ignore'])->middleware('permission:punches.update')->name('punches.ignore');

        Route::get('daily', [AttendanceController::class, 'index'])->middleware('permission:attendance-daily.view')->name('daily.index');
        Route::get('daily/{employee}/history', [AttendanceController::class, 'history'])->middleware('permission:attendance-daily.view')->name('daily.history');
        Route::post('daily/process', [AttendanceController::class, 'process'])->middleware('permission:attendance-daily.update')->name('daily.process');
        Route::post('daily/punch', [AttendanceController::class, 'storePunch'])->middleware('permission:attendance-daily.update')->name('daily.punch');

        Route::get('corrections', [AttendanceCorrectionController::class, 'index'])->middleware('permission:corrections.view')->name('corrections.index');
        Route::post('corrections/bulk-approve', [AttendanceCorrectionController::class, 'bulkApprove'])->middleware('permission:corrections.update')->name('corrections.bulk-approve');
        Route::patch('corrections/{correction}/approve', [AttendanceCorrectionController::class, 'approve'])->middleware('permission:corrections.update')->name('corrections.approve');
        Route::patch('corrections/{correction}/reject', [AttendanceCorrectionController::class, 'reject'])->middleware('permission:corrections.update')->name('corrections.reject');

        // Overtime is submitted by employees and approved by their supervisor (see the
        // my-overtime routes below). HR only monitors and recaps the approved totals.
        Route::get('overtime', [OvertimeController::class, 'index'])->middleware('permission:overtime.view')->name('overtime.index');
        Route::get('overtime/recap', [OvertimeController::class, 'recap'])->middleware('permission:overtime.view')->name('overtime.recap');

        Route::get('swaps', [ShiftSwapController::class, 'index'])->middleware('permission:swaps.view')->name('swaps.index');
        Route::post('swaps/bulk-approve', [ShiftSwapController::class, 'bulkApprove'])->middleware('permission:swaps.update')->name('swaps.bulk-approve');
        Route::patch('swaps/{swap}/approve', [ShiftSwapController::class, 'approve'])->middleware('permission:swaps.update')->name('swaps.approve');
        Route::patch('swaps/{swap}/reject', [ShiftSwapController::class, 'reject'])->middleware('permission:swaps.update')->name('swaps.reject');

        Route::get('schedule-patterns', [SchedulePatternController::class, 'index'])->middleware('permission:schedule-patterns.view')->name('schedule-patterns.index');
        Route::get('schedule-patterns/create', [SchedulePatternController::class, 'create'])->middleware('permission:schedule-patterns.create')->name('schedule-patterns.create');
        Route::post('schedule-patterns', [SchedulePatternController::class, 'store'])->middleware('permission:schedule-patterns.create')->name('schedule-patterns.store');
        Route::get('schedule-patterns/{schedulePattern}/edit', [SchedulePatternController::class, 'edit'])->middleware('permission:schedule-patterns.update')->name('schedule-patterns.edit');
        Route::put('schedule-patterns/{schedulePattern}', [SchedulePatternController::class, 'update'])->middleware('permission:schedule-patterns.update')->name('schedule-patterns.update');
        Route::delete('schedule-patterns/{schedulePattern}', [SchedulePatternController::class, 'destroy'])->middleware('permission:schedule-patterns.delete')->name('schedule-patterns.destroy');

        Route::get('schedules', [ScheduleController::class, 'index'])->middleware('permission:schedules.view')->name('schedules.index');
        Route::get('schedules/assign', [ScheduleController::class, 'create'])->middleware('permission:schedules.create')->name('schedules.assign');
        Route::post('schedules/assign', [ScheduleController::class, 'store'])->middleware('permission:schedules.create')->name('schedules.store');
        Route::get('schedules/employees/{employee}', [ScheduleController::class, 'show'])->middleware('permission:schedules.view')->name('schedules.show');
        Route::post('schedules/generate', [ScheduleController::class, 'generate'])->middleware('permission:schedules.update')->name('schedules.generate');
        Route::post('schedules/override', [ScheduleController::class, 'override'])->middleware('permission:schedules.update')->name('schedules.override');
        Route::delete('schedules/assignments/{assignment}', [ScheduleController::class, 'destroyAssignment'])->middleware('permission:schedules.delete')->name('schedules.assignments.destroy');

        Route::get('leave', [LeaveController::class, 'index'])->middleware('permission:leave.view')->name('leave.index');
        Route::get('leave/create', [LeaveController::class, 'create'])->middleware('permission:leave.create')->name('leave.create');
        Route::post('leave/bulk-approve', [LeaveController::class, 'bulkApprove'])->middleware('permission:leave.update')->name('leave.bulk-approve');
        Route::post('leave', [LeaveController::class, 'store'])->middleware('permission:leave.create')->name('leave.store');
        Route::patch('leave/{leaveRequest}/approve', [LeaveController::class, 'approve'])->middleware('permission:leave.update')->name('leave.approve');
        Route::patch('leave/{leaveRequest}/reject', [LeaveController::class, 'reject'])->middleware('permission:leave.update')->name('leave.reject');
        // Cuti/izin yang sudah DISETUJUI bersifat final — tidak bisa dibatalkan.
        // Catatan: pengajuan cuti/izin HANYA boleh dihapus oleh karyawan yang
        // mengajukannya (lihat my-leave.destroy). HR menyetujui/menolak/membatalkan,
        // tetapi tidak menghapus pengajuan orang lain.

        // Master data cuti: jenis cuti + kuota per karyawan. Rute literal
        // (create, leave-balances) dideklarasikan sebelum wildcard {leaveType}.
        Route::get('leave-types', [LeaveTypeController::class, 'index'])->middleware('permission:leave-types.view')->name('leave-types.index');
        Route::get('leave-types/create', [LeaveTypeController::class, 'create'])->middleware('permission:leave-types.create')->name('leave-types.create');
        Route::post('leave-types', [LeaveTypeController::class, 'store'])->middleware('permission:leave-types.create')->name('leave-types.store');
        Route::get('leave-balances', [LeaveBalanceController::class, 'index'])->middleware('permission:leave-balances.view')->name('leave-balances.index');
        Route::put('leave-balances', [LeaveBalanceController::class, 'update'])->middleware('permission:leave-balances.update')->name('leave-balances.update');
        Route::get('leave-types/{leaveType}/edit', [LeaveTypeController::class, 'edit'])->middleware('permission:leave-types.update')->name('leave-types.edit');
        Route::put('leave-types/{leaveType}', [LeaveTypeController::class, 'update'])->middleware('permission:leave-types.update')->name('leave-types.update');
        Route::delete('leave-types/{leaveType}', [LeaveTypeController::class, 'destroy'])->middleware('permission:leave-types.delete')->name('leave-types.destroy');
    });

    // Employee self-service: request own leave, and (as a supervisor) approve subordinates.
    Route::prefix('my-leave')->name('my-leave.')->middleware('permission:my-leave.view')->group(function () {
        Route::get('/', [MyLeaveController::class, 'index'])->name('index');
        Route::get('create', [MyLeaveController::class, 'create'])->name('create');
        Route::post('/', [MyLeaveController::class, 'store'])->name('store');
        Route::patch('{leaveRequest}/cancel', [MyLeaveController::class, 'cancel'])->name('cancel');
        Route::delete('{leaveRequest}', [MyLeaveController::class, 'destroy'])->name('destroy');
        Route::patch('{leaveRequest}/approve', [MyLeaveController::class, 'approve'])->name('approve');
        Route::patch('{leaveRequest}/reject', [MyLeaveController::class, 'reject'])->name('reject');
    });

    // Employee self-service: view own attendance and request corrections.
    Route::prefix('my-attendance')->name('my-attendance.')->middleware('permission:my-attendance.view')->group(function () {
        Route::get('/', [MyAttendanceController::class, 'index'])->name('index');
        Route::post('check-in', [MyAttendanceController::class, 'checkIn'])->name('check-in');
        Route::post('check-out', [MyAttendanceController::class, 'checkOut'])->name('check-out');
        Route::post('corrections', [MyAttendanceController::class, 'store'])->name('corrections.store');
        Route::delete('corrections/{correction}', [MyAttendanceController::class, 'cancel'])->name('corrections.cancel');
    });

    // Employee self-service: view own schedule and request shift swaps.
    Route::prefix('my-schedule')->name('my-schedule.')->middleware('permission:my-schedule.view')->group(function () {
        Route::get('/', [MyScheduleController::class, 'index'])->name('index');
        Route::post('swaps', [MyScheduleController::class, 'store'])->name('swaps.store');
        Route::patch('swaps/{swap}/respond', [MyScheduleController::class, 'respond'])->name('swaps.respond');
        Route::delete('swaps/{swap}', [MyScheduleController::class, 'cancel'])->name('swaps.cancel');
    });

    // Employee self-service: submit own overtime, and (as a supervisor) approve
    // subordinates' overtime requests.
    Route::prefix('my-overtime')->name('my-overtime.')->middleware('permission:my-overtime.view')->group(function () {
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
        Route::post('roles', [AccessControlController::class, 'storeRole'])
            ->middleware('permission:access-control.update')
            ->name('roles.store');
        Route::put('roles/{role}', [AccessControlController::class, 'updateRole'])
            ->middleware('permission:access-control.update')
            ->name('roles.update');
        Route::patch('roles/{role}/rename', [AccessControlController::class, 'renameRole'])
            ->middleware('permission:access-control.update')
            ->name('roles.rename');
        Route::delete('roles/{role}', [AccessControlController::class, 'destroyRole'])
            ->middleware('permission:access-control.update')
            ->name('roles.destroy');
        Route::put('job-positions/{jobPosition}', [AccessControlController::class, 'updateJobPosition'])
            ->middleware('permission:access-control.update')
            ->name('job-positions.update');
        Route::put('branches/{branch}/departments', [AccessControlController::class, 'updateBranchDepartments'])
            ->middleware('permission:access-control.update')
            ->name('branches.departments.update');
        Route::put('users/{user}/scope', [AccessControlController::class, 'updateUserScope'])
            ->middleware('permission:access-control.update')
            ->name('user-scope.update');
    });

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
