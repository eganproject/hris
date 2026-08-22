<?php

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Seorang HR yang boleh melihat papan harian (dan karenanya juga peta), tanpa batas
 * cakupan lokasi/divisi.
 */
function mapViewer(): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('attendance-daily.view', 'web');
    Permission::findOrCreate(User::SCOPE_BYPASS_ATTENDANCE, 'web');

    $user = User::factory()->create();
    $user->givePermissionTo(['attendance-daily.view', User::SCOPE_BYPASS_ATTENDANCE]);

    return $user;
}

/** Karyawan yang dijadwalkan WFH pada tanggal tertentu. */
function wfhEmployee(string $name, string $date): Employee
{
    $shift = Shift::query()->firstOrCreate(
        ['code' => 'REG'],
        ['name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00', 'is_active' => true],
    );

    $employee = Employee::query()->create(['full_name' => $name, 'employment_status' => 'active']);

    EmployeeSchedule::query()->create([
        'employee_id' => $employee->id, 'work_date' => $date,
        'shift_id' => $shift->id, 'is_day_off' => false, 'is_wfh' => true, 'source' => 'generated',
    ]);

    return $employee;
}

test('the map plots WFH employees who checked in, with their coordinates', function () {
    $date = '2026-08-20';
    $user = mapViewer();
    $employee = wfhEmployee('Budi WFH', $date);

    Attendance::query()->create([
        'employee_id' => $employee->id, 'work_date' => $date, 'status' => AttendanceStatus::Wfh->value,
        'clock_in' => $date.' 08:05:00', 'clock_out' => $date.' 17:10:00',
        'clock_in_photo_path' => 'attendance/selfies/2026/08/budi.jpg',
        'clock_in_latitude' => -6.9147444, 'clock_in_longitude' => 107.6098111, 'clock_in_accuracy_m' => 18,
    ]);

    $response = $this->actingAs($user)->get('/attendance/map?date='.$date)->assertOk();

    $points = $response->viewData('points');

    expect($points)->toHaveCount(1)
        ->and($points[0]['name'])->toBe('Budi WFH')
        ->and($points[0]['lat'])->toBe(-6.9147444)
        ->and($points[0]['lng'])->toBe(107.6098111)
        ->and($points[0]['accuracy'])->toBe(18)
        ->and($points[0]['status'])->toBe('wfh')
        ->and($points[0]['photo_url'])->toContain('budi.jpg')
        ->and($response->viewData('pending'))->toHaveCount(0);
});

test('someone scheduled WFH who has not checked in is listed as pending, not plotted', function () {
    $date = '2026-08-20';
    $user = mapViewer();
    wfhEmployee('Belum Absen', $date);

    // Sengaja tanpa baris absensi sama sekali — persis keadaan sebelum HR memproses
    // tanggalnya. Orangnya tetap harus terdaftar sebagai belum absen.
    $response = $this->actingAs($user)->get('/attendance/map?date='.$date)->assertOk();

    expect($response->viewData('points'))->toHaveCount(0)
        ->and($response->viewData('pending'))->toHaveCount(1)
        ->and($response->viewData('pending')[0]['employee']->full_name)->toBe('Belum Absen');
});

test('a WFH day with a clock-in but no coordinates counts as pending', function () {
    $date = '2026-08-20';
    $user = mapViewer();
    $employee = wfhEmployee('Absen Mesin', $date);

    Attendance::query()->create([
        'employee_id' => $employee->id, 'work_date' => $date, 'status' => AttendanceStatus::Wfh->value,
        'clock_in' => $date.' 08:00:00',
    ]);

    $response = $this->actingAs($user)->get('/attendance/map?date='.$date)->assertOk();

    expect($response->viewData('points'))->toHaveCount(0)
        ->and($response->viewData('pending'))->toHaveCount(1);
});

test('employees working at the office never appear on the map', function () {
    $date = '2026-08-20';
    $user = mapViewer();

    $shift = Shift::query()->create(['code' => 'OFC', 'name' => 'Kantor', 'start_time' => '08:00', 'end_time' => '17:00', 'is_active' => true]);
    $employee = Employee::query()->create(['full_name' => 'Karyawan Kantor', 'employment_status' => 'active']);
    EmployeeSchedule::query()->create([
        'employee_id' => $employee->id, 'work_date' => $date,
        'shift_id' => $shift->id, 'is_day_off' => false, 'source' => 'generated',
    ]);
    Attendance::query()->create([
        'employee_id' => $employee->id, 'work_date' => $date, 'status' => AttendanceStatus::Present->value,
        'clock_in' => $date.' 07:55:00',
    ]);

    $response = $this->actingAs($user)->get('/attendance/map?date='.$date)->assertOk();

    expect($response->viewData('points'))->toHaveCount(0)
        ->and($response->viewData('pending'))->toHaveCount(0);
});

test('the map obeys the data scope, so a branch HR sees only their own branch', function () {
    $date = '2026-08-20';

    $mine = Branch::query()->create(['code' => 'BDG', 'name' => 'Bandung', 'is_active' => true]);
    $other = Branch::query()->create(['code' => 'SBY', 'name' => 'Surabaya', 'is_active' => true]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('attendance-daily.view', 'web');
    $user = User::factory()->create();
    $user->givePermissionTo('attendance-daily.view');
    $user->accessBranches()->attach($mine->id); // tanpa izin "semua lokasi"

    foreach ([[$mine, 'Orang Bandung'], [$other, 'Orang Surabaya']] as [$branch, $name]) {
        $employee = wfhEmployee($name, $date);
        $employee->update(['branch_id' => $branch->id]);

        Attendance::query()->create([
            'employee_id' => $employee->id, 'work_date' => $date, 'status' => AttendanceStatus::Wfh->value,
            'clock_in' => $date.' 08:00:00',
            'clock_in_photo_path' => 'attendance/selfies/2026/08/x.jpg',
            'clock_in_latitude' => -6.9, 'clock_in_longitude' => 107.6, 'clock_in_accuracy_m' => 20,
        ]);
    }

    $points = $this->actingAs($user)->get('/attendance/map?date='.$date)->assertOk()->viewData('points');

    expect($points)->toHaveCount(1)
        ->and($points[0]['name'])->toBe('Orang Bandung');
});

test('the map is closed to users without the daily attendance permission', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('attendance-daily.view', 'web');

    $this->actingAs(User::factory()->create())->get('/attendance/map')->assertForbidden();
});
