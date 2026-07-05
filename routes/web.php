<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\AccessControlController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EmployeeManagementController;
use App\Http\Controllers\JobPositionController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\ShiftController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

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
        Route::get('{employee}', [EmployeeManagementController::class, 'show'])
            ->middleware('permission:employees.view')
            ->name('show');
        Route::get('{employee}/edit', [EmployeeManagementController::class, 'edit'])
            ->middleware('permission:employees.update')
            ->name('edit');
        Route::patch('{employee}/resign', [EmployeeManagementController::class, 'resign'])
            ->middleware('permission:employees.update')
            ->name('resign');
        Route::put('{employee}', [EmployeeManagementController::class, 'update'])
            ->middleware('permission:employees.update')
            ->name('update');
        Route::delete('{employee}', [EmployeeManagementController::class, 'destroy'])
            ->middleware('permission:employees.delete')
            ->name('destroy');
    });

    Route::get('payroll', [PayrollController::class, 'index'])
        ->middleware('permission:payroll.view')
        ->name('payroll.index');

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
