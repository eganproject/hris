<?php

use App\Enums\SchedulePatternType;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\ScheduleAssignment;
use App\Models\SchedulePattern;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * User dengan menu jadwal. $scoped=true → tanpa "attendance.view.all", jadi hanya
 * melihat karyawan dalam cakupan lokasi/divisinya.
 */
function scheduleViewer(bool $scoped = false): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $permissions = attendanceMenuPermissions(['view', 'create']);

    if (! $scoped) {
        $permissions[] = User::SCOPE_BYPASS_ATTENDANCE;
    }

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    Permission::findOrCreate(User::SCOPE_BYPASS_ATTENDANCE, 'web');

    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

function assignPatternTo(Employee $employee): void
{
    $pattern = SchedulePattern::query()->create([
        'code' => 'W'.$employee->id, 'name' => 'Weekly', 'type' => SchedulePatternType::FixedWeekly,
        'cycle_length' => 7, 'is_active' => true,
    ]);

    ScheduleAssignment::query()->create([
        'employee_id' => $employee->id,
        'schedule_pattern_id' => $pattern->id,
        'start_date' => now()->toDateString(),
        'end_date' => null,
    ]);
}

test('it lists active employees with no schedule assignment and hides assigned ones', function () {
    $user = scheduleViewer();

    $withoutPattern = Employee::query()->create(['full_name' => 'Belum Dijadwal', 'employment_status' => 'active']);

    $withPattern = Employee::query()->create(['full_name' => 'Sudah Dijadwal', 'employment_status' => 'active']);
    assignPatternTo($withPattern);

    $this->actingAs($user)->get('/attendance/schedules/unscheduled')
        ->assertOk()
        ->assertSee('Belum Dijadwal')
        ->assertDontSee('Sudah Dijadwal');
});

test('inactive employees are excluded even without a schedule', function () {
    $user = scheduleViewer();

    Employee::query()->create(['full_name' => 'Sudah Keluar', 'employment_status' => 'inactive']);

    $this->actingAs($user)->get('/attendance/schedules/unscheduled')
        ->assertOk()
        ->assertDontSee('Sudah Keluar');
});

test('the unscheduled list can be exported to xlsx', function () {
    Excel::fake();
    $user = scheduleViewer();

    $this->actingAs($user)->get('/attendance/schedules/unscheduled/export')
        ->assertOk();

    Excel::assertDownloaded('karyawan-belum-terjadwal-'.now()->format('Y-m-d').'.xlsx');
});

test('monthly mode hides employees who already have a schedule row this month', function () {
    $user = scheduleViewer();

    $withoutRows = Employee::query()->create(['full_name' => 'Tanpa Baris Jadwal', 'employment_status' => 'active']);

    $withRows = Employee::query()->create(['full_name' => 'Sudah Ada Jadwal', 'employment_status' => 'active']);
    EmployeeSchedule::query()->create([
        'employee_id' => $withRows->id, 'work_date' => now()->toDateString(),
        'is_day_off' => true, 'source' => 'generated',
    ]);

    $this->actingAs($user)->get('/attendance/schedules/unscheduled?mode=no_schedule')
        ->assertOk()
        ->assertSee('Tanpa Baris Jadwal')
        ->assertDontSee('Sudah Ada Jadwal');
});

test('monthly mode distinguishes having a covering pola from having none', function () {
    $user = scheduleViewer();

    // Punya pola yang menutupi bulan ini, tapi roster belum digenerate → perlu generate.
    $needsGenerate = Employee::query()->create(['full_name' => 'Perlu Generate', 'employment_status' => 'active']);
    assignPatternTo($needsGenerate);

    // Tidak punya pola sama sekali → perlu ditugaskan pola.
    Employee::query()->create(['full_name' => 'Perlu Pola', 'employment_status' => 'active']);

    $this->actingAs($user)->get('/attendance/schedules/unscheduled?mode=no_schedule')
        ->assertOk()
        ->assertSee('Perlu Generate')
        ->assertSee('Ada pola · perlu generate')
        ->assertSee('Perlu Pola')
        ->assertSee('Belum ada pola');
});

test('monthly export is named after the selected month', function () {
    Excel::fake();
    $user = scheduleViewer();

    $this->actingAs($user)->get('/attendance/schedules/unscheduled/export?mode=no_schedule&month=2026-03')
        ->assertOk();

    Excel::assertDownloaded('karyawan-belum-terjadwal-2026-03.xlsx');
});

test('a scoped user only sees unscheduled employees in their scope', function () {
    $sby = Branch::query()->create(['code' => 'SBY', 'name' => 'Surabaya', 'is_active' => true]);
    $jkt = Branch::query()->create(['code' => 'JKT', 'name' => 'Jakarta', 'is_active' => true]);

    Employee::query()->create(['full_name' => 'Sby Belum Jadwal', 'branch_id' => $sby->id, 'employment_status' => 'active']);
    Employee::query()->create(['full_name' => 'Jkt Belum Jadwal', 'branch_id' => $jkt->id, 'employment_status' => 'active']);

    $user = scheduleViewer(scoped: true);
    $user->accessBranches()->sync([$sby->id]);

    $this->actingAs($user)->get('/attendance/schedules/unscheduled')
        ->assertOk()
        ->assertSee('Sby Belum Jadwal')
        ->assertDontSee('Jkt Belum Jadwal');
});

/** A pattern that works Mon–Sat, usable as the bulk-assign target. */
function bulkPattern(): SchedulePattern
{
    $shift = Shift::query()->create([
        'code' => 'REG', 'name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00', 'is_active' => true,
    ]);

    $pattern = SchedulePattern::query()->create([
        'code' => 'BULK', 'name' => 'Reguler Mingguan', 'type' => SchedulePatternType::FixedWeekly,
        'cycle_length' => 7, 'is_active' => true,
    ]);

    foreach (range(1, 6) as $index) {
        $pattern->days()->create(['day_index' => $index, 'shift_id' => $shift->id]);
    }

    return $pattern;
}

test('the list offers a checkbox per employee and a bulk assign panel', function () {
    $user = scheduleViewer();
    bulkPattern();
    Employee::query()->create(['full_name' => 'Belum Dijadwal', 'employment_status' => 'active']);

    $this->actingAs($user)->get('/attendance/schedules/unscheduled')
        ->assertOk()
        ->assertSee('Penjadwalan Massal')
        ->assertSee('name="employee_ids[]"', false)
        ->assertSee('data-bulk-all', false)
        ->assertSee('Reguler Mingguan');
});

test('one pattern can be assigned to several selected employees at once', function () {
    $user = scheduleViewer();
    $pattern = bulkPattern();

    $first = Employee::query()->create(['full_name' => 'Andi', 'employment_status' => 'active']);
    $second = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active']);
    $untouched = Employee::query()->create(['full_name' => 'Citra', 'employment_status' => 'active']);

    $this->actingAs($user)->post('/attendance/schedules/unscheduled/assign', [
        'employee_ids' => [$first->id, $second->id],
        'schedule_pattern_id' => $pattern->id,
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-31',
    ])->assertRedirect(route('attendance.unscheduled.index'));

    expect(ScheduleAssignment::query()->where('employee_id', $first->id)->count())->toBe(1)
        ->and(ScheduleAssignment::query()->where('employee_id', $second->id)->count())->toBe(1)
        ->and(ScheduleAssignment::query()->where('employee_id', $untouched->id)->count())->toBe(0)
        // The roster is materialized straight away, so they leave this list.
        ->and(EmployeeSchedule::query()->where('employee_id', $first->id)->count())->toBe(31)
        ->and(EmployeeSchedule::query()->where('employee_id', $second->id)->count())->toBe(31);
});

test('bulk assign returns to the same filtered view it was launched from', function () {
    $user = scheduleViewer();
    $pattern = bulkPattern();
    $employee = Employee::query()->create(['full_name' => 'Andi', 'employment_status' => 'active']);

    $this->actingAs($user)->post('/attendance/schedules/unscheduled/assign', [
        'employee_ids' => [$employee->id],
        'schedule_pattern_id' => $pattern->id,
        'start_date' => '2026-03-01',
        'mode' => 'no_schedule',
        'month' => '2026-03',
        'search' => 'Andi',
    ])->assertRedirect(route('attendance.unscheduled.index', [
        'mode' => 'no_schedule', 'month' => '2026-03', 'search' => 'Andi',
    ]));

    expect(session('status'))->toContain('1 karyawan');
});

test('bulk assign rejects an empty selection', function () {
    $user = scheduleViewer();
    $pattern = bulkPattern();

    $this->actingAs($user)->post('/attendance/schedules/unscheduled/assign', [
        'employee_ids' => [],
        'schedule_pattern_id' => $pattern->id,
        'start_date' => '2026-03-01',
    ])->assertSessionHasErrors('employee_ids');

    expect(ScheduleAssignment::query()->count())->toBe(0);
});

test('bulk assign cannot reach an employee outside the users scope', function () {
    $sby = Branch::query()->create(['code' => 'SBY', 'name' => 'Surabaya', 'is_active' => true]);
    $jkt = Branch::query()->create(['code' => 'JKT', 'name' => 'Jakarta', 'is_active' => true]);
    $pattern = bulkPattern();

    $outsider = Employee::query()->create(['full_name' => 'Jkt', 'branch_id' => $jkt->id, 'employment_status' => 'active']);

    $user = scheduleViewer(scoped: true);
    $user->accessBranches()->sync([$sby->id]);

    $this->actingAs($user)->post('/attendance/schedules/unscheduled/assign', [
        'employee_ids' => [$outsider->id],
        'schedule_pattern_id' => $pattern->id,
        'start_date' => '2026-03-01',
    ])->assertForbidden();

    expect(ScheduleAssignment::query()->count())->toBe(0);
});

test('the empty list renders without the bulk panel', function () {
    $user = scheduleViewer();
    bulkPattern();

    $this->actingAs($user)->get('/attendance/schedules/unscheduled')
        ->assertOk()
        ->assertDontSee('Penjadwalan Massal')
        ->assertSee('Semua sudah punya pola');
});
