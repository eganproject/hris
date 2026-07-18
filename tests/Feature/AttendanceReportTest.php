<?php

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\OvertimeApproval;
use App\Models\User;
use App\Support\AttendanceReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function reportViewer(): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $permissions = ['reports.attendance.view', 'reports.attendance.export', 'attendance.view.all'];

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

const REPORT_FROM = '2026-06-01';
const REPORT_TO = '2026-06-30';

function reportAtt(Employee $employee, string $date, string $status, int $late = 0, int $work = 0): void
{
    Attendance::query()->create([
        'employee_id' => $employee->id,
        'work_date' => $date,
        'status' => $status,
        'late_minutes' => $late,
        'work_minutes' => $work,
    ]);
}

/** @return array<string, mixed>|null */
function reportRowFor($rows, Employee $employee): ?array
{
    return $rows->first(fn ($row) => $row['employee']->id === $employee->id);
}

test('it aggregates each status and reconciles the working-day total', function () {
    $e = Employee::query()->create(['full_name' => 'Andi', 'employment_status' => 'active', 'join_date' => '2026-01-01']);

    reportAtt($e, '2026-06-01', 'present', work: 480);
    reportAtt($e, '2026-06-02', 'late', late: 15, work: 465);
    reportAtt($e, '2026-06-03', 'early_leave', work: 300);
    reportAtt($e, '2026-06-04', 'wfh', work: 480);
    reportAtt($e, '2026-06-05', 'business_trip');
    reportAtt($e, '2026-06-06', 'absent');
    reportAtt($e, '2026-06-08', 'leave');
    reportAtt($e, '2026-06-09', 'sick');
    // Rest days must NOT inflate "Hari".
    reportAtt($e, '2026-06-07', 'day_off');
    reportAtt($e, '2026-06-17', 'holiday');

    $row = reportRowFor(app(AttendanceReport::class)->rows(REPORT_FROM, REPORT_TO), $e);

    expect($row['hadir'])->toBe(5)          // present + late + early_leave + wfh + business_trip
        ->and($row['terlambat'])->toBe(1)
        ->and($row['pulang_cepat'])->toBe(1)
        ->and($row['alfa'])->toBe(1)
        ->and($row['cuti'])->toBe(1)
        ->and($row['sakit'])->toBe(1)
        ->and($row['terlambat_menit'])->toBe(15)
        ->and($row['kerja_menit'])->toBe(480 + 465 + 300 + 480)
        ->and($row['total_hari'])->toBe(8);  // 10 rows minus day_off & holiday

    // The breakdown must add up to the working-day total (was impossible before the fix).
    expect($row['hadir'] + $row['alfa'] + $row['cuti'] + $row['sakit'])->toBe($row['total_hari']);
});

test('only approved overtime is summed into lembur', function () {
    $e = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active', 'join_date' => '2026-01-01']);
    reportAtt($e, '2026-06-02', 'present', work: 480);

    OvertimeApproval::query()->create(['employee_id' => $e->id, 'work_date' => '2026-06-02', 'status' => 'approved', 'approved_minutes' => 60]);
    OvertimeApproval::query()->create(['employee_id' => $e->id, 'work_date' => '2026-06-03', 'status' => 'approved', 'approved_minutes' => 30]);
    OvertimeApproval::query()->create(['employee_id' => $e->id, 'work_date' => '2026-06-04', 'status' => 'pending', 'approved_minutes' => 999]);

    $row = reportRowFor(app(AttendanceReport::class)->rows(REPORT_FROM, REPORT_TO), $e);

    expect($row['lembur_menit'])->toBe(90);
});

test('active employees with no attendance still appear with zeros', function () {
    $e = Employee::query()->create(['full_name' => 'Belum Absen', 'employment_status' => 'active', 'join_date' => '2026-01-01']);

    $row = reportRowFor(app(AttendanceReport::class)->rows(REPORT_FROM, REPORT_TO), $e);

    expect($row)->not->toBeNull()
        ->and($row['total_hari'])->toBe(0)
        ->and($row['hadir'])->toBe(0)
        ->and($row['alfa'])->toBe(0);
});

test('employees hired after the period are excluded', function () {
    $joinedLater = Employee::query()->create(['full_name' => 'Baru Masuk', 'employment_status' => 'active', 'join_date' => '2026-07-15']);

    $rows = app(AttendanceReport::class)->rows(REPORT_FROM, REPORT_TO);

    expect($rows->contains(fn ($row) => $row['employee']->id === $joinedLater->id))->toBeFalse();
});

test('the recap page renders a totals row and lists every division of an employee', function () {
    $ops = Department::query()->create(['code' => 'OPS', 'name' => 'Operasional', 'is_active' => true]);
    $fin = Department::query()->create(['code' => 'FIN', 'name' => 'Keuangan', 'is_active' => true]);

    // Home division (department_id) is auto-synced into the pivot on create; attach
    // only the additional division so we don't duplicate the home one.
    $e = Employee::query()->create(['full_name' => 'Multi Divisi', 'employment_status' => 'active', 'join_date' => '2026-01-01', 'department_id' => $ops->id]);
    $e->departments()->syncWithoutDetaching([$fin->id]);
    reportAtt($e, '2026-06-02', 'present', work: 480);

    $this->actingAs(reportViewer())->get('/reports/attendance?month=2026-06')
        ->assertOk()
        ->assertSee('Operasional')
        ->assertSee('Keuangan')
        ->assertSee('1 karyawan'); // totals row footer
});

test('the recap pdf renders without error', function () {
    $e = Employee::query()->create(['full_name' => 'Andi', 'employment_status' => 'active', 'join_date' => '2026-01-01']);
    reportAtt($e, '2026-06-02', 'present', work: 480);

    $response = $this->actingAs(reportViewer())->get('/reports/attendance/pdf?month=2026-06');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

test('the branch filter narrows the recap', function () {
    $sby = Branch::query()->create(['code' => 'SBY', 'name' => 'Surabaya', 'is_active' => true]);
    $jkt = Branch::query()->create(['code' => 'JKT', 'name' => 'Jakarta', 'is_active' => true]);

    $inSby = Employee::query()->create(['full_name' => 'Sby', 'branch_id' => $sby->id, 'employment_status' => 'active', 'join_date' => '2026-01-01']);
    $inJkt = Employee::query()->create(['full_name' => 'Jkt', 'branch_id' => $jkt->id, 'employment_status' => 'active', 'join_date' => '2026-01-01']);
    reportAtt($inSby, '2026-06-02', 'present', work: 480);
    reportAtt($inJkt, '2026-06-02', 'present', work: 480);

    $rows = app(AttendanceReport::class)->rows(REPORT_FROM, REPORT_TO, $sby->id);

    expect($rows->contains(fn ($row) => $row['employee']->id === $inSby->id))->toBeTrue()
        ->and($rows->contains(fn ($row) => $row['employee']->id === $inJkt->id))->toBeFalse();
});
