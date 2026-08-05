<?php

use App\Enums\AttendanceStatus;
use App\Enums\LeaveRequestStatus;
use App\Enums\SchedulePatternType;
use App\Enums\ScheduleSource;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\JobPosition;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\ScheduleAssignment;
use App\Models\SchedulePattern;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\User;
use App\Services\AttendanceResolver;
use App\Services\DefaultOfficeSchedule;
use App\Services\ScheduleGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function scheduleManager(): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $permissions = [...attendanceMenuPermissions(), 'attendance.view.all'];

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

function weeklyPattern(int $regShiftId): SchedulePattern
{
    $pattern = SchedulePattern::query()->create([
        'code' => 'W', 'name' => 'Weekly', 'type' => SchedulePatternType::FixedWeekly, 'cycle_length' => 7, 'is_active' => true,
    ]);

    // dayOfWeek: 0=Sun..6=Sat. Work Mon-Fri, off weekends.
    foreach ([0 => null, 1 => $regShiftId, 2 => $regShiftId, 3 => $regShiftId, 4 => $regShiftId, 5 => $regShiftId, 6 => null] as $index => $shiftId) {
        $pattern->days()->create(['day_index' => $index, 'shift_id' => $shiftId]);
    }

    return $pattern;
}

test('the generator materializes a fixed weekly pattern with days off', function () {
    $reg = Shift::query()->create(['code' => 'REG', 'name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00', 'is_active' => true]);
    $employee = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active']);
    $pattern = weeklyPattern($reg->id);

    $assignment = ScheduleAssignment::query()->create([
        'employee_id' => $employee->id, 'schedule_pattern_id' => $pattern->id,
        'start_date' => '2026-02-01', 'end_date' => '2026-02-07', // Sun..Sat
    ]);

    $written = app(ScheduleGenerator::class)->forAssignment($assignment);

    expect($written)->toBe(7)
        ->and(EmployeeSchedule::query()->where('employee_id', $employee->id)->count())->toBe(7);

    $sunday = EmployeeSchedule::query()->where('work_date', '2026-02-01')->first();
    $monday = EmployeeSchedule::query()->where('work_date', '2026-02-02')->first();

    expect($sunday->is_day_off)->toBeTrue()
        ->and($sunday->shift_id)->toBeNull()
        ->and($monday->is_day_off)->toBeFalse()
        ->and($monday->shift_id)->toBe($reg->id)
        ->and($monday->source)->toBe(ScheduleSource::Generated);
});

test('the generator follows a rotating cycle from its anchor date', function () {
    $pagi = Shift::query()->create(['code' => 'PG', 'name' => 'Pagi', 'start_time' => '07:00', 'end_time' => '15:00', 'is_active' => true]);
    $siang = Shift::query()->create(['code' => 'SG', 'name' => 'Siang', 'start_time' => '15:00', 'end_time' => '23:00', 'is_active' => true]);
    $malam = Shift::query()->create(['code' => 'ML', 'name' => 'Malam', 'start_time' => '23:00', 'end_time' => '07:00', 'crosses_midnight' => true, 'is_active' => true]);
    $employee = Employee::query()->create(['full_name' => 'Ani', 'employment_status' => 'active']);

    $pattern = SchedulePattern::query()->create([
        'code' => 'R', 'name' => 'Rotasi', 'type' => SchedulePatternType::Rotating, 'cycle_length' => 4, 'anchor_date' => '2026-01-01', 'is_active' => true,
    ]);
    foreach ([0 => $pagi->id, 1 => $siang->id, 2 => $malam->id, 3 => null] as $index => $shiftId) {
        $pattern->days()->create(['day_index' => $index, 'shift_id' => $shiftId]);
    }

    $assignment = ScheduleAssignment::query()->create([
        'employee_id' => $employee->id, 'schedule_pattern_id' => $pattern->id,
        'start_date' => '2026-01-01', 'end_date' => '2026-01-05',
    ]);

    app(ScheduleGenerator::class)->forAssignment($assignment);

    $shiftOn = fn (string $date) => EmployeeSchedule::query()->where('work_date', $date)->first();

    expect($shiftOn('2026-01-01')->shift_id)->toBe($pagi->id)   // slot 0
        ->and($shiftOn('2026-01-02')->shift_id)->toBe($siang->id) // slot 1
        ->and($shiftOn('2026-01-03')->shift_id)->toBe($malam->id) // slot 2
        ->and($shiftOn('2026-01-04')->is_day_off)->toBeTrue()      // slot 3 = off
        ->and($shiftOn('2026-01-05')->shift_id)->toBe($pagi->id);  // wraps to slot 0
});

test('a manual override is never clobbered when the roster is regenerated', function () {
    $reg = Shift::query()->create(['code' => 'REG', 'name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00', 'is_active' => true]);
    $extra = Shift::query()->create(['code' => 'EXT', 'name' => 'Ekstra', 'start_time' => '10:00', 'end_time' => '19:00', 'is_active' => true]);
    $employee = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active']);
    $pattern = weeklyPattern($reg->id);

    $assignment = ScheduleAssignment::query()->create([
        'employee_id' => $employee->id, 'schedule_pattern_id' => $pattern->id,
        'start_date' => '2026-02-01', 'end_date' => '2026-02-07',
    ]);

    $generator = app(ScheduleGenerator::class);
    $generator->forAssignment($assignment);

    // Override the Monday to a different shift, manually.
    $generator->override($employee, Carbon::parse('2026-02-02'), $extra->id, false, 'Tukar shift');

    // Regenerate the whole window.
    $generator->forEmployee($employee, Carbon::parse('2026-02-01'), Carbon::parse('2026-02-07'));

    $monday = EmployeeSchedule::query()->where('work_date', '2026-02-02')->first();

    expect($monday->shift_id)->toBe($extra->id)
        ->and($monday->source)->toBe(ScheduleSource::Manual)
        ->and($monday->note)->toBe('Tukar shift');
});

test('assigning a pattern via the controller generates the schedule', function () {
    $user = scheduleManager();
    $reg = Shift::query()->create(['code' => 'REG', 'name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00', 'is_active' => true]);
    $employee = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active']);
    $pattern = weeklyPattern($reg->id);

    $this->actingAs($user)->post('/attendance/schedules/assign', [
        'employee_ids' => [$employee->id],
        'schedule_pattern_id' => $pattern->id,
        'start_date' => '2026-02-01',
        'end_date' => '2026-02-28',
    ])->assertRedirect();

    expect(ScheduleAssignment::query()->where('employee_id', $employee->id)->exists())->toBeTrue()
        ->and(EmployeeSchedule::query()->where('employee_id', $employee->id)->count())->toBe(28);
});

test('assigning a pattern recalculates an existing day-off attendance', function () {
    $user = scheduleManager();
    $reg = Shift::query()->create(['code' => 'REG', 'name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00', 'is_active' => true]);
    $employee = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active']);
    $pattern = weeklyPattern($reg->id);

    $attendance = app(AttendanceResolver::class)->resolve($employee, Carbon::parse('2026-02-02'));
    expect($attendance->status)->toBe(AttendanceStatus::DayOff);

    $this->actingAs($user)->post('/attendance/schedules/assign', [
        'employee_ids' => [$employee->id],
        'schedule_pattern_id' => $pattern->id,
        'start_date' => '2026-02-01',
        'end_date' => '2026-02-28',
    ])->assertRedirect();

    expect($attendance->fresh()->status)->toBe(AttendanceStatus::Absent)
        ->and($attendance->fresh()->shift_id)->toBe($reg->id);
});

test('generating a roster recalculates existing attendance in that month', function () {
    $user = scheduleManager();
    $reg = Shift::query()->create(['code' => 'REG', 'name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00', 'is_active' => true]);
    $employee = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active']);
    $pattern = weeklyPattern($reg->id);

    ScheduleAssignment::query()->create([
        'employee_id' => $employee->id,
        'schedule_pattern_id' => $pattern->id,
        'start_date' => '2026-02-01',
        'end_date' => '2026-02-28',
        'created_by' => $user->id,
    ]);
    $attendance = app(AttendanceResolver::class)->resolve($employee, Carbon::parse('2026-02-02'));

    $this->actingAs($user)->post('/attendance/schedules/generate', [
        'month' => '2026-02',
    ])->assertRedirect();

    expect($attendance->fresh()->status)->toBe(AttendanceStatus::Absent)
        ->and($attendance->fresh()->shift_id)->toBe($reg->id);
});

test('storing a pattern persists its slots', function () {
    $user = scheduleManager();
    $reg = Shift::query()->create(['code' => 'REG', 'name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00', 'is_active' => true]);

    $this->actingAs($user)->post('/attendance/schedule-patterns', [
        'code' => 'OFF5',
        'name' => 'Kantor',
        'type' => 'fixed_weekly',
        'is_active' => '1',
        'days' => [1 => $reg->id, 2 => $reg->id, 3 => $reg->id, 4 => $reg->id, 5 => $reg->id],
    ])->assertRedirect('/attendance/schedule-patterns');

    $pattern = SchedulePattern::query()->firstWhere('code', 'OFF5');

    expect($pattern->days()->count())->toBe(7)
        ->and($pattern->days()->where('day_index', 1)->value('shift_id'))->toBe($reg->id)
        ->and($pattern->days()->where('day_index', 0)->value('shift_id'))->toBeNull();
});

test('a manual override can be set through the controller', function () {
    $user = scheduleManager();
    $reg = Shift::query()->create(['code' => 'REG', 'name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00', 'is_active' => true]);
    $employee = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active']);

    $this->actingAs($user)->post('/attendance/schedules/override', [
        'employee_id' => $employee->id,
        'work_date' => '2026-02-10',
        'shift_id' => $reg->id,
        'note' => 'Ganti',
    ])->assertRedirect();

    $row = EmployeeSchedule::query()->where('employee_id', $employee->id)->where('work_date', '2026-02-10')->first();

    expect($row)->not->toBeNull()
        ->and($row->shift_id)->toBe($reg->id)
        ->and($row->source)->toBe(ScheduleSource::Manual);
});

test('a manual override recalculates an existing day-off attendance', function () {
    $user = scheduleManager();
    $reg = Shift::query()->create(['code' => 'REG', 'name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00', 'is_active' => true]);
    $employee = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active']);
    $attendance = app(AttendanceResolver::class)->resolve($employee, Carbon::parse('2026-02-10'));

    $this->actingAs($user)->post('/attendance/schedules/override', [
        'employee_id' => $employee->id,
        'work_date' => '2026-02-10',
        'shift_id' => $reg->id,
    ])->assertRedirect();

    expect($attendance->fresh()->status)->toBe(AttendanceStatus::Absent)
        ->and($attendance->fresh()->shift_id)->toBe($reg->id);
});

test('approved leave shows on the roster and on the per-employee schedule', function () {
    $user = scheduleManager();
    $reg = Shift::query()->create(['code' => 'REG', 'name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00', 'is_active' => true]);
    $pattern = weeklyPattern($reg->id);

    $branch = Branch::query()->create(['code' => 'HO', 'name' => 'Kantor Pusat', 'is_active' => true]);
    $department = Department::query()->create(['code' => 'IT', 'name' => 'Teknologi', 'is_active' => true]);
    $position = JobPosition::query()->create(['code' => 'STF', 'name' => 'Staff IT', 'is_active' => true]);

    $employee = Employee::query()->create([
        'full_name' => 'Budi Cuti', 'employment_status' => 'active',
        'branch_id' => $branch->id, 'department_id' => $department->id, 'job_position_id' => $position->id,
    ]);

    $assignment = ScheduleAssignment::query()->create([
        'employee_id' => $employee->id, 'schedule_pattern_id' => $pattern->id,
        'start_date' => now()->startOfMonth()->toDateString(), 'end_date' => now()->endOfMonth()->toDateString(),
    ]);
    app(ScheduleGenerator::class)->forAssignment($assignment);

    $leaveType = LeaveType::query()->create([
        'code' => 'CT', 'name' => 'Cuti Tahunan', 'attendance_status' => 'leave',
        'is_paid' => true, 'counts_against_balance' => true, 'default_quota_days' => 12, 'is_active' => true,
    ]);

    LeaveRequest::query()->create([
        'employee_id' => $employee->id, 'leave_type_id' => $leaveType->id,
        'start_date' => now()->startOfMonth()->addDays(9)->toDateString(),
        'end_date' => now()->startOfMonth()->addDays(11)->toDateString(),
        'reason' => 'Liburan keluarga.', 'status' => LeaveRequestStatus::Approved->value,
    ]);

    // Still awaiting a decision: the roster must not treat this day as time off.
    $pendingType = LeaveType::query()->create([
        'code' => 'IZ', 'name' => 'Izin Khusus', 'attendance_status' => 'leave',
        'is_paid' => false, 'counts_against_balance' => false, 'default_quota_days' => 0, 'is_active' => true,
    ]);
    LeaveRequest::query()->create([
        'employee_id' => $employee->id, 'leave_type_id' => $pendingType->id,
        'start_date' => now()->startOfMonth()->addDays(20)->toDateString(),
        'end_date' => now()->startOfMonth()->addDays(20)->toDateString(),
        'reason' => 'Urusan keluarga.', 'status' => LeaveRequestStatus::PendingSupervisor->value,
    ]);

    $month = now()->format('Y-m');

    $this->actingAs($user)->get("/attendance/schedules?month={$month}")
        ->assertOk()
        ->assertSee('Cuti Tahunan (disetujui)')
        ->assertSee('Cuti/izin disetujui', escape: false)
        ->assertDontSee('Izin Khusus');

    $this->actingAs($user)->get("/attendance/schedules/employees/{$employee->id}?month={$month}")
        ->assertOk()
        ->assertSee('Budi Cuti')
        ->assertSee('Staff IT')
        ->assertSee('Kantor Pusat')
        ->assertSee('Cuti Tahunan disetujui')
        ->assertSee('3</span> hari cuti/izin', escape: false)
        ->assertDontSee('Izin Khusus');
});

test('the roster fills office-hours employees from the default pattern without materialized rows', function () {
    $user = scheduleManager();
    $reg = Shift::query()->create(['code' => 'REG', 'name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00', 'is_active' => true]);
    $pattern = weeklyPattern($reg->id);
    Setting::set(DefaultOfficeSchedule::SETTING_KEY, (string) $pattern->id);

    $employee = Employee::query()->create([
        'full_name' => 'Karyawan Kantoran', 'employment_status' => 'active',
        'join_date' => now()->toDateString(), 'follows_office_hours' => true,
    ]);

    $month = now()->format('Y-m');

    $this->actingAs($user)->get("/attendance/schedules?month={$month}")
        ->assertOk()
        ->assertSee('Karyawan Kantoran')
        ->assertSee('Jam kantor') // badge next to the name
        ->assertSee('REG');       // synthesized shift code appears in weekday cells

    // The grid is virtual only: no schedule rows are written for this employee.
    expect(EmployeeSchedule::query()->where('employee_id', $employee->id)->count())->toBe(0);
});

test('the roster can be filtered by division, position and name', function () {
    $user = scheduleManager();
    $branch = Branch::query()->create(['code' => 'SBY', 'name' => 'Surabaya', 'is_active' => true]);
    $ops = Department::query()->create(['code' => 'OPS', 'name' => 'Operasional', 'is_active' => true]);
    $acc = Department::query()->create(['code' => 'ACC', 'name' => 'Accounting', 'is_active' => true]);
    $staf = JobPosition::query()->create(['code' => 'STF', 'name' => 'Staf', 'is_active' => true]);
    $spv = JobPosition::query()->create(['code' => 'SPV', 'name' => 'Supervisor', 'is_active' => true]);

    $make = fn (string $name, $dept, $pos) => Employee::query()->create([
        'branch_id' => $branch->id, 'department_id' => $dept->id, 'job_position_id' => $pos->id,
        'full_name' => $name, 'employment_status' => 'active', 'join_date' => now()->toDateString(),
    ]);

    $make('Budi Operasional Staf', $ops, $staf);
    $make('Sari Accounting Staf', $acc, $staf);
    $make('Tono Operasional Spv', $ops, $spv);

    // Filter divisi Operasional.
    $this->actingAs($user)->get('/attendance/schedules?department_id='.$ops->id)
        ->assertOk()->assertSee('Budi Operasional Staf')->assertSee('Tono Operasional Spv')->assertDontSee('Sari Accounting Staf');

    // Filter jabatan Supervisor.
    $this->actingAs($user)->get('/attendance/schedules?job_position_id='.$spv->id)
        ->assertOk()->assertSee('Tono Operasional Spv')->assertDontSee('Budi Operasional Staf');

    // Cari nama.
    $this->actingAs($user)->get('/attendance/schedules?search=Sari')
        ->assertOk()->assertSee('Sari Accounting Staf')->assertDontSee('Budi Operasional Staf');
});

test('the assign page shows each employee org info and their existing schedule period', function () {
    $user = scheduleManager();
    $reg = Shift::query()->create(['code' => 'REG', 'name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00', 'is_active' => true]);
    $pattern = weeklyPattern($reg->id);

    $branch = Branch::query()->create(['code' => 'HO', 'name' => 'Kantor Pusat', 'is_active' => true]);
    $department = Department::query()->create(['code' => 'IT', 'name' => 'Teknologi', 'is_active' => true]);
    $position = JobPosition::query()->create(['code' => 'STF', 'name' => 'Staff IT', 'is_active' => true]);

    $employee = Employee::query()->create([
        'full_name' => 'Budi', 'employment_status' => 'active',
        'branch_id' => $branch->id, 'department_id' => $department->id, 'job_position_id' => $position->id,
    ]);

    // Still running today, so it must be visible on the picker.
    ScheduleAssignment::query()->create([
        'employee_id' => $employee->id, 'schedule_pattern_id' => $pattern->id,
        'start_date' => now()->subMonth()->toDateString(), 'end_date' => null,
    ]);

    $this->actingAs($user)->get('/attendance/schedules/assign')
        ->assertOk()
        ->assertSee('Staff IT')
        ->assertSee('Teknologi')
        ->assertSee('Kantor Pusat')
        ->assertSee('Weekly')
        ->assertSee(now()->subMonth()->translatedFormat('d M Y'))
        ->assertSee('seterusnya')
        // Filter pemilihan karyawan (lokasi/divisi/jabatan) tersedia.
        ->assertSee('Semua lokasi')
        ->assertSee('Semua divisi')
        ->assertSee('Semua jabatan')
        ->assertSee('data-filter-search', escape: false)
        ->assertSee('data-employee-row', escape: false)
        // Script filter benar-benar ikut ter-render oleh @stack('scripts').
        ->assertSee('visibleRows', escape: false);
});

test('the assign page hides schedule periods that have already ended', function () {
    $user = scheduleManager();
    $reg = Shift::query()->create(['code' => 'REG', 'name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00', 'is_active' => true]);
    $employee = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active']);
    $pattern = weeklyPattern($reg->id);

    ScheduleAssignment::query()->create([
        'employee_id' => $employee->id, 'schedule_pattern_id' => $pattern->id,
        'start_date' => now()->subYear()->toDateString(), 'end_date' => now()->subDay()->toDateString(),
    ]);

    $this->actingAs($user)->get('/attendance/schedules/assign')
        ->assertOk()
        ->assertDontSee(now()->subDay()->translatedFormat('d M Y'))
        ->assertSee('Belum ada jadwal');
});

test('scheduling pages render', function () {
    $user = scheduleManager();

    foreach ([
        '/attendance/schedule-patterns',
        '/attendance/schedule-patterns/create',
        '/attendance/schedules',
        '/attendance/schedules/assign',
    ] as $url) {
        $this->actingAs($user)->get($url)->assertOk();
    }
});

/**
 * A pattern that works every day of the week, so tests that touch "today" do not
 * depend on which weekday the suite happens to run on.
 */
function everydayPattern(int $shiftId, string $code = 'ALL'): SchedulePattern
{
    $pattern = SchedulePattern::query()->create([
        'code' => $code, 'name' => 'Setiap Hari '.$code, 'type' => SchedulePatternType::FixedWeekly,
        'cycle_length' => 7, 'is_active' => true,
    ]);

    foreach (range(0, 6) as $index) {
        $pattern->days()->create(['day_index' => $index, 'shift_id' => $shiftId]);
    }

    return $pattern;
}

test('the nightly roster generator recalculates attendance already recorded for today', function () {
    $user = scheduleManager();
    $reg = Shift::query()->create(['code' => 'REG', 'name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00', 'is_active' => true]);
    $employee = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active']);

    ScheduleAssignment::query()->create([
        'employee_id' => $employee->id,
        'schedule_pattern_id' => everydayPattern($reg->id)->id,
        'start_date' => Carbon::today()->toDateString(),
        'end_date' => null,
        'created_by' => $user->id,
    ]);

    // Closed out before the roster existed, so it was recorded as a rest day.
    $attendance = app(AttendanceResolver::class)->resolve($employee, Carbon::today());
    expect($attendance->status)->toBe(AttendanceStatus::DayOff);

    $this->artisan('schedule:generate-roster', ['--days' => 1])->assertSuccessful();

    expect($attendance->fresh()->status)->toBe(AttendanceStatus::Absent)
        ->and($attendance->fresh()->shift_id)->toBe($reg->id);
});

test('any schedule written through the generator refreshes attendance, not just the controllers', function () {
    $reg = Shift::query()->create(['code' => 'REG', 'name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00', 'is_active' => true]);
    $employee = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active']);
    $date = Carbon::yesterday();

    $attendance = app(AttendanceResolver::class)->resolve($employee, $date);
    expect($attendance->status)->toBe(AttendanceStatus::DayOff);

    // The path ShiftSwapService and the importer take — no controller involved.
    app(ScheduleGenerator::class)->override($employee, $date, $reg->id, false);

    expect($attendance->fresh()->status)->toBe(AttendanceStatus::Absent);
});

test('marking a scheduled day off turns a recorded Alfa back into Libur', function () {
    $reg = Shift::query()->create(['code' => 'REG', 'name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00', 'is_active' => true]);
    $employee = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active']);
    $date = Carbon::yesterday();
    $generator = app(ScheduleGenerator::class);

    $generator->override($employee, $date, $reg->id, false);
    $attendance = app(AttendanceResolver::class)->resolve($employee, $date);
    expect($attendance->status)->toBe(AttendanceStatus::Absent);

    // The correction runs the other way too: the day was not a work day after all.
    $generator->override($employee, $date, null, true);

    expect($attendance->fresh()->status)->toBe(AttendanceStatus::DayOff);
});

test('re-assigning a pattern over the same period replaces the old one', function () {
    $pagi = Shift::query()->create(['code' => 'PG', 'name' => 'Pagi', 'start_time' => '07:00', 'end_time' => '15:00', 'is_active' => true]);
    $malam = Shift::query()->create(['code' => 'ML', 'name' => 'Malam', 'start_time' => '23:00', 'end_time' => '07:00', 'is_active' => true]);
    $employee = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active']);
    $generator = app(ScheduleGenerator::class);

    $old = ScheduleAssignment::query()->create([
        'employee_id' => $employee->id, 'schedule_pattern_id' => everydayPattern($pagi->id)->id,
        'start_date' => '2026-09-01', 'end_date' => '2026-09-30',
    ]);
    $generator->forAssignment($old);

    // Same employee, same period, different pattern — an explicit replacement.
    $new = ScheduleAssignment::query()->create([
        'employee_id' => $employee->id, 'schedule_pattern_id' => everydayPattern($malam->id, 'ALL2')->id,
        'start_date' => '2026-09-01', 'end_date' => '2026-09-30',
    ]);
    $generator->forAssignment($new);

    $day = EmployeeSchedule::query()->where('work_date', '2026-09-10')->firstOrFail();

    expect($day->shift_id)->toBe($malam->id)
        ->and($day->schedule_assignment_id)->toBe($new->id);
});

test('a newer assignment wins even over an older one that starts later', function () {
    $pagi = Shift::query()->create(['code' => 'PG', 'name' => 'Pagi', 'start_time' => '07:00', 'end_time' => '15:00', 'is_active' => true]);
    $malam = Shift::query()->create(['code' => 'ML', 'name' => 'Malam', 'start_time' => '23:00', 'end_time' => '07:00', 'is_active' => true]);
    $employee = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active']);

    // The old assignment starts later in the month, so under a start-date rule it
    // would keep the second half. Assigning again must override it outright.
    ScheduleAssignment::query()->create([
        'employee_id' => $employee->id, 'schedule_pattern_id' => everydayPattern($malam->id)->id,
        'start_date' => '2026-09-15', 'end_date' => '2026-09-30',
    ]);
    $newer = ScheduleAssignment::query()->create([
        'employee_id' => $employee->id, 'schedule_pattern_id' => everydayPattern($pagi->id, 'ALL2')->id,
        'start_date' => '2026-09-01', 'end_date' => '2026-09-30',
    ]);

    app(ScheduleGenerator::class)->forEmployee($employee, Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'));

    expect(EmployeeSchedule::query()->where('work_date', '2026-09-20')->value('shift_id'))->toBe($pagi->id)
        ->and(EmployeeSchedule::query()->where('work_date', '2026-09-20')->value('schedule_assignment_id'))->toBe($newer->id)
        ->and(EmployeeSchedule::query()->where('work_date', '2026-09-05')->value('shift_id'))->toBe($pagi->id);
});

test('an older assignment resumes on the days the newer one does not cover', function () {
    $pagi = Shift::query()->create(['code' => 'PG', 'name' => 'Pagi', 'start_time' => '07:00', 'end_time' => '15:00', 'is_active' => true]);
    $malam = Shift::query()->create(['code' => 'ML', 'name' => 'Malam', 'start_time' => '23:00', 'end_time' => '07:00', 'is_active' => true]);
    $employee = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active']);

    // Standing pattern, open-ended.
    $standing = ScheduleAssignment::query()->create([
        'employee_id' => $employee->id, 'schedule_pattern_id' => everydayPattern($pagi->id)->id,
        'start_date' => '2026-09-01', 'end_date' => null,
    ]);
    // A short replacement in the middle of it.
    $temporary = ScheduleAssignment::query()->create([
        'employee_id' => $employee->id, 'schedule_pattern_id' => everydayPattern($malam->id, 'ALL2')->id,
        'start_date' => '2026-09-10', 'end_date' => '2026-09-20',
    ]);

    app(ScheduleGenerator::class)->forEmployee($employee, Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'));

    $on = fn (string $date) => EmployeeSchedule::query()->where('work_date', $date)->first();

    expect($on('2026-09-05')->schedule_assignment_id)->toBe($standing->id)
        ->and($on('2026-09-15')->schedule_assignment_id)->toBe($temporary->id)
        ->and($on('2026-09-15')->shift_id)->toBe($malam->id)
        // Past the replacement window the standing pattern takes over again.
        ->and($on('2026-09-25')->schedule_assignment_id)->toBe($standing->id)
        ->and($on('2026-09-25')->shift_id)->toBe($pagi->id);
});

test('re-assigning reports the days it left alone because they were edited manually', function () {
    $user = scheduleManager();
    $pagi = Shift::query()->create(['code' => 'PG', 'name' => 'Pagi', 'start_time' => '07:00', 'end_time' => '15:00', 'is_active' => true]);
    $malam = Shift::query()->create(['code' => 'ML', 'name' => 'Malam', 'start_time' => '23:00', 'end_time' => '07:00', 'is_active' => true]);
    $employee = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active']);

    // Two days edited by hand (or written by the roster import).
    app(ScheduleGenerator::class)->override($employee, Carbon::parse('2026-09-10'), $pagi->id, false);
    app(ScheduleGenerator::class)->override($employee, Carbon::parse('2026-09-11'), $pagi->id, false);

    $this->actingAs($user)->post('/attendance/schedules/assign', [
        'employee_ids' => [$employee->id],
        'schedule_pattern_id' => everydayPattern($malam->id)->id,
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-30',
    ])->assertRedirect();

    expect(session('status'))->toContain('2 hari dilewati karena sudah diubah manual')
        // The manual days genuinely keep their old shift.
        ->and(EmployeeSchedule::query()->where('work_date', '2026-09-10')->value('shift_id'))->toBe($pagi->id)
        ->and(EmployeeSchedule::query()->where('work_date', '2026-09-12')->value('shift_id'))->toBe($malam->id);
});

test('generating a roster for many employees stays within a sane query budget', function () {
    $shift = Shift::query()->create([
        'code' => 'REG', 'name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00', 'is_active' => true,
    ]);
    $pattern = everydayPattern($shift->id);
    $generator = app(ScheduleGenerator::class);
    $start = Carbon::today();

    $employees = collect(range(1, 50))->map(fn ($n) => Employee::query()->create([
        'full_name' => 'Karyawan '.$n, 'employment_status' => 'active',
    ]));

    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    foreach ($employees as $employee) {
        $generator->forAssignment($employee->scheduleAssignments()->create([
            'schedule_pattern_id' => $pattern->id,
            'start_date' => $start->toDateString(),
            'end_date' => null,
        ]));
    }

    $days = ScheduleGenerator::DEFAULT_HORIZON_DAYS + 1;

    // Writing a day at a time cost ~190 queries per employee and blew past the
    // request timeout on a bulk assign; the range is written as one upsert now.
    expect(EmployeeSchedule::query()->count())->toBe(50 * $days)
        ->and($queries)->toBeLessThan(50 * 20);
});

test('the roster marks which of two overlapping assignments is actually in force', function () {
    $user = scheduleManager();
    $pagi = Shift::query()->create(['code' => 'PG', 'name' => 'Pagi', 'start_time' => '07:00', 'end_time' => '15:00', 'is_active' => true]);
    $malam = Shift::query()->create(['code' => 'ML', 'name' => 'Malam', 'start_time' => '23:00', 'end_time' => '07:00', 'is_active' => true]);
    $employee = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active']);

    ScheduleAssignment::query()->create([
        'employee_id' => $employee->id, 'schedule_pattern_id' => everydayPattern($pagi->id)->id,
        'start_date' => '2026-09-01', 'end_date' => '2026-09-30', 'created_by' => $user->id,
    ]);
    ScheduleAssignment::query()->create([
        'employee_id' => $employee->id, 'schedule_pattern_id' => everydayPattern($malam->id, 'ALL2')->id,
        'start_date' => '2026-09-01', 'end_date' => '2026-09-30', 'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->get('/attendance/schedules?month=2026-09')->assertOk();

    // Both rows are listed, but only the newer one decides any day of the month.
    $response->assertSee('Berlaku')->assertSee('Tergantikan');

    expect(substr_count($response->getContent(), 'Berlaku'))->toBe(1)
        ->and(substr_count($response->getContent(), 'Tergantikan'))->toBe(1);
});

test('an older assignment still counts as in force where the newer one does not reach', function () {
    $user = scheduleManager();
    $pagi = Shift::query()->create(['code' => 'PG', 'name' => 'Pagi', 'start_time' => '07:00', 'end_time' => '15:00', 'is_active' => true]);
    $malam = Shift::query()->create(['code' => 'ML', 'name' => 'Malam', 'start_time' => '23:00', 'end_time' => '07:00', 'is_active' => true]);
    $employee = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active']);

    ScheduleAssignment::query()->create([
        'employee_id' => $employee->id, 'schedule_pattern_id' => everydayPattern($pagi->id)->id,
        'start_date' => '2026-09-01', 'end_date' => null, 'created_by' => $user->id,
    ]);
    ScheduleAssignment::query()->create([
        'employee_id' => $employee->id, 'schedule_pattern_id' => everydayPattern($malam->id, 'ALL2')->id,
        'start_date' => '2026-09-10', 'end_date' => '2026-09-20', 'created_by' => $user->id,
    ]);

    $content = $this->actingAs($user)->get('/attendance/schedules?month=2026-09')->assertOk()->getContent();

    // The standing pattern still owns 1-9 and 21-30, so neither is superseded.
    expect(substr_count($content, 'Berlaku'))->toBe(2)
        ->and(substr_count($content, 'Tergantikan'))->toBe(0);
});
