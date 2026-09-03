<?php

use App\Enums\AttendanceStatus;
use App\Enums\LeaveRequestStatus;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Penjaga lebar: satu status absensi baru (Izin) tidak boleh membuat halaman mana
 * pun gagal dirender. Enum ini dibaca lencana, penyaring, kartu, tabel, PDF, dan
 * Excel di banyak tempat sekaligus.
 */
test('every page that renders an attendance status still loads with an Izin row', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('superadmin', 'web'));

    // Semua izin dari katalog RBAC, supaya tiap halaman benar-benar terbuka.
    $all = [];
    foreach (collect(config('rbac.menus'))->collapse() as $menu => $meta) {
        foreach ($meta['actions'] ?? [] as $action) {
            $all[] = $menu.'.'.$action;
        }
    }
    $all[] = 'employees.view.all';
    $all[] = 'attendance.view.all';
    foreach ($all as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    $user->givePermissionTo($all);
    $user->forceFill(['bypass_team_scope' => true])->save();

    $izin = LeaveType::query()->create([
        'code' => 'IZ', 'name' => 'Izin', 'attendance_status' => AttendanceStatus::Permit->value,
        'is_paid' => true, 'counts_against_balance' => false, 'is_active' => true,
    ]);

    $employee = Employee::query()->create([
        'user_id' => $user->id, 'full_name' => 'Budi Izin', 'employment_status' => 'active',
        'join_date' => now()->subYear()->toDateString(),
    ]);

    $request = LeaveRequest::query()->create([
        'employee_id' => $employee->id, 'leave_type_id' => $izin->id,
        'start_date' => now()->toDateString(), 'end_date' => now()->toDateString(),
        'reason' => 'Izin.', 'status' => LeaveRequestStatus::Approved->value,
    ]);

    Attendance::query()->create([
        'employee_id' => $employee->id, 'work_date' => now()->toDateString(),
        'status' => AttendanceStatus::Permit->value, 'leave_request_id' => $request->id,
    ]);

    $pages = [
        '/dashboard',
        '/attendance/daily',
        '/attendance/daily?status=permit',
        "/attendance/daily/{$employee->id}/history",
        '/attendance/map',
        '/attendance/leave',
        '/attendance/leave-types',
        '/attendance/schedules',
        "/attendance/schedules/employees/{$employee->id}",
        '/attendance/overtime',
        '/attendance/overtime/recap',
        '/attendance/corrections',
        '/my-attendance',
        '/jadwal-saya',
        '/my-schedule',
        '/reports',
        '/reports/attendance',
        '/reports/attendance-log',
        '/reports/attendance-log?status=permit',
        '/reports/leave',
        "/reports/attendance/{$employee->id}",
        "/reports/leave/{$employee->id}",
        '/employees',
    ];

    foreach ($pages as $url) {
        $response = $this->actingAs($user)->get($url);
        expect($response->status())->toBe(200, "gagal: {$url}");
    }

    $downloads = [
        '/reports/attendance/export', '/reports/attendance/pdf',
        '/reports/attendance-log/export', '/reports/attendance-log/pdf',
        '/reports/leave/export', '/reports/leave/pdf',
    ];

    foreach ($downloads as $url) {
        $this->actingAs($user)->get($url)->assertOk();
    }
});
