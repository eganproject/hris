<?php

use App\Enums\AttendanceStatus;
use App\Enums\LeaveRequestStatus;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Shift;
use App\Models\User;
use App\Services\AttendanceResolver;
use App\Support\AttendanceReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Cuti dan izin dulu memakai satu status absensi yang sama, sehingga seluruh laporan
 * hanya bisa menampilkan satu kolom gabungan. Sekarang izin punya statusnya sendiri
 * dan pemisahannya ditentukan pada jenis cutinya.
 */
function cutiType(): LeaveType
{
    return LeaveType::query()->firstOrCreate(['code' => 'CT'], [
        'name' => 'Cuti Tahunan', 'attendance_status' => AttendanceStatus::Leave->value,
        'is_paid' => true, 'counts_against_balance' => true,
        'default_quota_days' => 12, 'is_active' => true,
    ]);
}

function izinType(): LeaveType
{
    return LeaveType::query()->firstOrCreate(['code' => 'IZ'], [
        'name' => 'Izin', 'attendance_status' => AttendanceStatus::Permit->value,
        'is_paid' => true, 'counts_against_balance' => false,
        'default_quota_days' => null, 'is_active' => true,
    ]);
}

function splitReportViewer(): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $permissions = [
        'dashboard.view', 'reports.attendance.view', 'reports.attendance.export',
        'reports.log.view', 'reports.log.export', 'attendance.view.all',
    ];

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::factory()->create();
    $user->givePermissionTo($permissions);
    $user->forceFill(['bypass_team_scope' => true])->save();

    return $user;
}

function approveLeaveOn(Employee $employee, LeaveType $type, string $date): void
{
    LeaveRequest::query()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $type->id,
        'start_date' => $date,
        'end_date' => $date,
        'reason' => 'Uji '.$type->name,
        'status' => LeaveRequestStatus::Approved->value,
    ]);
}

test('the resolver records cuti and izin as two different statuses', function () {
    $shift = Shift::query()->create([
        'code' => 'REG', 'name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00',
        'break_minutes' => 60, 'late_tolerance_minutes' => 10, 'is_active' => true,
    ]);

    $employee = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active']);

    foreach (['2026-06-01' => cutiType(), '2026-06-02' => izinType()] as $date => $type) {
        EmployeeSchedule::query()->create([
            'employee_id' => $employee->id, 'work_date' => $date,
            'shift_id' => $shift->id, 'is_day_off' => false, 'source' => 'generated',
        ]);
        approveLeaveOn($employee, $type, $date);
    }

    $resolver = app(AttendanceResolver::class);

    expect($resolver->resolve($employee, Carbon::parse('2026-06-01'))->status)->toBe(AttendanceStatus::Leave)
        ->and($resolver->resolve($employee, Carbon::parse('2026-06-02'))->status)->toBe(AttendanceStatus::Permit);
});

test('the attendance recap counts cuti and izin in separate columns', function () {
    $employee = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active']);

    // Dua hari cuti, tiga hari izin.
    foreach (['2026-06-01', '2026-06-02'] as $date) {
        Attendance::query()->create([
            'employee_id' => $employee->id, 'work_date' => $date,
            'status' => AttendanceStatus::Leave->value,
        ]);
    }

    foreach (['2026-06-03', '2026-06-04', '2026-06-05'] as $date) {
        Attendance::query()->create([
            'employee_id' => $employee->id, 'work_date' => $date,
            'status' => AttendanceStatus::Permit->value,
        ]);
    }

    $row = app(AttendanceReport::class)->rows('2026-06-01', '2026-06-30')->firstWhere('employee.id', $employee->id);

    expect($row['cuti'])->toBe(2)
        ->and($row['izin'])->toBe(3)
        // Rinciannya harus tetap berdamai dengan total harinya.
        ->and($row['total_hari'])->toBe(5);
});

test('the recap screen and its downloads show Cuti and Izin as separate columns', function () {
    $user = splitReportViewer();
    $employee = Employee::query()->create(['full_name' => 'Budi Terpisah', 'employment_status' => 'active']);

    Attendance::query()->create([
        'employee_id' => $employee->id, 'work_date' => '2026-06-01',
        'status' => AttendanceStatus::Leave->value,
    ]);
    Attendance::query()->create([
        'employee_id' => $employee->id, 'work_date' => '2026-06-02',
        'status' => AttendanceStatus::Permit->value,
    ]);

    $html = $this->actingAs($user)->get('/reports/attendance?month=2026-06')->assertOk()->getContent();

    expect($html)->toContain('>Cuti<')
        ->and($html)->toContain('>Izin<')
        // Kolom gabungan yang lama tidak boleh tersisa di mana pun.
        ->and($html)->not->toContain('Cuti / Izin');

    $this->actingAs($user)->get('/reports/attendance/export?month=2026-06')->assertOk();
    $this->actingAs($user)->get('/reports/attendance/pdf?month=2026-06')->assertOk();
});

test('the attendance log labels the two apart instead of lumping them', function () {
    $user = splitReportViewer();
    $employee = Employee::query()->create(['full_name' => 'Budi Log', 'employment_status' => 'active']);

    Attendance::query()->create([
        'employee_id' => $employee->id, 'work_date' => '2026-06-01',
        'status' => AttendanceStatus::Permit->value,
    ]);

    $this->actingAs($user)->get('/reports/attendance-log?month=2026-06')
        ->assertOk()
        ->assertSee('Izin')
        ->assertDontSee('Cuti / Izin');

    $this->actingAs($user)->get('/reports/attendance-log/export?month=2026-06')->assertOk();
});

test('a leave type can be mapped to Izin from the leave type screen', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach (['dashboard.view', 'leave-types.view', 'leave-types.create'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::factory()->create();
    $user->givePermissionTo(['dashboard.view', 'leave-types.view', 'leave-types.create']);

    // Pemisahan cuti/izin ditentukan HR di sini, bukan ditebak laporan dari nama.
    $this->actingAs($user)->post('/attendance/leave-types', [
        'code' => 'IZK', 'name' => 'Izin Khusus',
        'attendance_status' => AttendanceStatus::Permit->value,
        'is_paid' => 1, 'counts_against_balance' => 0, 'is_active' => 1,
    ])->assertRedirect();

    expect(LeaveType::query()->where('code', 'IZK')->first()?->attendance_status)
        ->toBe(AttendanceStatus::Permit);
});

test('the migration moves existing izin records off the shared cuti status', function () {
    // Keadaan sebelum migrasi: jenis "IZ" masih dipetakan ke Cuti, dan absensinya
    // ikut tercatat sebagai Cuti.
    $izin = LeaveType::query()->create([
        'code' => 'IZ', 'name' => 'Izin', 'attendance_status' => AttendanceStatus::Leave->value,
        'is_paid' => true, 'counts_against_balance' => false, 'is_active' => true,
    ]);
    $cuti = cutiType();

    $employee = Employee::query()->create(['full_name' => 'Budi Lama', 'employment_status' => 'active']);

    $izinRequest = LeaveRequest::query()->create([
        'employee_id' => $employee->id, 'leave_type_id' => $izin->id,
        'start_date' => '2026-05-01', 'end_date' => '2026-05-01',
        'reason' => 'Izin lama.', 'status' => LeaveRequestStatus::Approved->value,
    ]);
    $cutiRequest = LeaveRequest::query()->create([
        'employee_id' => $employee->id, 'leave_type_id' => $cuti->id,
        'start_date' => '2026-05-02', 'end_date' => '2026-05-02',
        'reason' => 'Cuti lama.', 'status' => LeaveRequestStatus::Approved->value,
    ]);

    $izinRow = Attendance::query()->create([
        'employee_id' => $employee->id, 'work_date' => '2026-05-01',
        'status' => AttendanceStatus::Leave->value, 'leave_request_id' => $izinRequest->id,
    ]);
    $cutiRow = Attendance::query()->create([
        'employee_id' => $employee->id, 'work_date' => '2026-05-02',
        'status' => AttendanceStatus::Leave->value, 'leave_request_id' => $cutiRequest->id,
    ]);

    $migration = require database_path('migrations/2026_09_03_140000_split_izin_from_cuti_attendance_status.php');
    $migration->up();

    expect($izin->fresh()->attendance_status)->toBe(AttendanceStatus::Permit)
        ->and($izinRow->fresh()->status)->toBe(AttendanceStatus::Permit)
        // Cuti sungguhan tidak boleh ikut terbawa.
        ->and($cuti->fresh()->attendance_status)->toBe(AttendanceStatus::Leave)
        ->and($cutiRow->fresh()->status)->toBe(AttendanceStatus::Leave);

    // Dan bisa dikembalikan utuh.
    $migration->down();

    expect($izin->fresh()->attendance_status)->toBe(AttendanceStatus::Leave)
        ->and($izinRow->fresh()->status)->toBe(AttendanceStatus::Leave);
});
