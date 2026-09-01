<?php

use App\Enums\AttendanceStatus;
use App\Enums\SchedulePatternType;
use App\Models\Employee;
use App\Models\SchedulePattern;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\User;
use App\Services\AttendanceResolver;
use App\Services\DefaultOfficeSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * The office-hours default pattern: 08:00–17:00 shift, worked Monday–Saturday,
 * Sunday off — mirroring OfficeSchedulePatternSeeder. Set as the app default.
 */
function officeDefaultPattern(): Shift
{
    $shift = Shift::query()->create([
        'code' => 'OFFICE', 'name' => 'Jam Kantor',
        'start_time' => '08:00', 'end_time' => '17:00',
        'break_minutes' => 60, 'late_tolerance_minutes' => 10,
        'is_active' => true,
    ]);

    $pattern = SchedulePattern::query()->create([
        'code' => 'OFFICE', 'name' => 'Jam Kantor', 'type' => SchedulePatternType::FixedWeekly,
        'cycle_length' => 7, 'is_active' => true,
    ]);

    // dayOfWeek 0=Sun..6=Sat: Mon–Sat work, Sunday (0) has no slot = off.
    foreach (range(1, 6) as $index) {
        $pattern->days()->create(['day_index' => $index, 'shift_id' => $shift->id]);
    }

    Setting::set(DefaultOfficeSchedule::SETTING_KEY, (string) $pattern->id);

    return $shift;
}

test('an office-hours employee with no schedule is resolved from the default pattern', function () {
    $shift = officeDefaultPattern();
    $employee = Employee::query()->create([
        'full_name' => 'Kantoran', 'employment_status' => 'active', 'follows_office_hours' => true,
    ]);

    // 2026-07-20 is a Monday; clocking in at 08:25 is past the 10-minute tolerance.
    $result = app(AttendanceResolver::class)->compute($employee, Carbon::parse('2026-07-20'), '08:25', '17:00');

    expect($result['status'])->toBe(AttendanceStatus::Late)
        ->and($result['shift_id'])->toBe($shift->id)
        ->and($result['late_minutes'])->toBe(25);
});

test('an office-hours employee is off on the pattern day off (Sunday)', function () {
    officeDefaultPattern();
    $employee = Employee::query()->create([
        'full_name' => 'Kantoran', 'employment_status' => 'active', 'follows_office_hours' => true,
    ]);

    // 2026-07-19 is a Sunday — no slot in the pattern, so it is a day off.
    $result = app(AttendanceResolver::class)->compute($employee, Carbon::parse('2026-07-19'));

    expect($result['status'])->toBe(AttendanceStatus::DayOff)
        ->and($result['shift_id'])->toBeNull();
});

test('an office-hours employee with no punch on a work day is Absent, not treated as a rest day', function () {
    officeDefaultPattern();
    $employee = Employee::query()->create([
        'full_name' => 'Kantoran', 'employment_status' => 'active', 'follows_office_hours' => true,
    ]);

    $result = app(AttendanceResolver::class)->compute($employee, Carbon::parse('2026-07-20'));

    expect($result['status'])->toBe(AttendanceStatus::Absent);
});

test('an employee without the flag is unaffected by the default pattern', function () {
    officeDefaultPattern();
    $employee = Employee::query()->create([
        'full_name' => 'Shift', 'employment_status' => 'active', 'follows_office_hours' => false,
    ]);

    // No schedule + no flag: a Monday punch counts as worked-on-a-rest-day (Present,
    // no shift window), never Late.
    $result = app(AttendanceResolver::class)->compute($employee, Carbon::parse('2026-07-20'), '08:25', '17:00');

    expect($result['status'])->toBe(AttendanceStatus::Present)
        ->and($result['shift_id'])->toBeNull();
});

test('with no default pattern configured the flag does nothing', function () {
    officeDefaultPattern();
    Setting::set(DefaultOfficeSchedule::SETTING_KEY, ''); // clear the default

    $employee = Employee::query()->create([
        'full_name' => 'Kantoran', 'employment_status' => 'active', 'follows_office_hours' => true,
    ]);

    $result = app(AttendanceResolver::class)->compute($employee, Carbon::parse('2026-07-20'), '08:25', '17:00');

    // Falls back to the old behaviour: worked on an unscheduled day.
    expect($result['status'])->toBe(AttendanceStatus::Present)
        ->and($result['shift_id'])->toBeNull();
});

/**
 * Pengguna yang boleh membuka papan Absensi Harian untuk semua karyawan:
 * dikecualikan dari pembatasan bawahan sekaligus punya hak melihat semua data
 * absensi — sama seperti HR/administrator sungguhan.
 */
function officeBoardUser(): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach (['attendance-daily.view', 'attendance.view.all'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::factory()->create();
    $user->givePermissionTo(['attendance-daily.view', 'attendance.view.all']);
    $user->forceFill(['bypass_team_scope' => true])->save();

    return $user;
}

test('the daily board shows an office-hours employee shift instead of "Belum dijadwalkan"', function () {
    officeDefaultPattern();

    Employee::query()->create([
        'full_name' => 'Kantoran Papan', 'employment_status' => 'active', 'follows_office_hours' => true,
    ]);

    // 2026-07-20 jatuh pada hari Senin: pola jam kantor memberi shift OFFICE.
    $this->actingAs(officeBoardUser())->get(route('attendance.daily.index', ['date' => '2026-07-20']))
        ->assertOk()
        ->assertSee('Kantoran Papan')
        ->assertSee('OFFICE')
        ->assertSee('08:00')
        ->assertDontSee('Belum dijadwalkan');
});

test('the daily board shows an office-hours employee as Libur on the pattern day off', function () {
    officeDefaultPattern();

    Employee::query()->create([
        'full_name' => 'Kantoran Papan', 'employment_status' => 'active', 'follows_office_hours' => true,
    ]);

    // 2026-07-19 jatuh pada hari Minggu: tidak ada slot di pola, jadi hari libur.
    $this->actingAs(officeBoardUser())->get(route('attendance.daily.index', ['date' => '2026-07-19']))
        ->assertOk()
        ->assertSee('Kantoran Papan')
        ->assertDontSee('Belum dijadwalkan')
        ->assertDontSee('OFFICE');
});

test('an employee who really has no schedule is still reported as "Belum dijadwalkan"', function () {
    officeDefaultPattern();

    // Tanpa tanda "ikut jam kantor" tidak ada pola yang bisa diturunkan, dan papan
    // harian memang harus mengatakannya — pengisian tadi tidak boleh menutupi
    // karyawan yang benar-benar belum dijadwalkan.
    Employee::query()->create([
        'full_name' => 'Shift Belum Diatur', 'employment_status' => 'active', 'follows_office_hours' => false,
    ]);

    $this->actingAs(officeBoardUser())->get(route('attendance.daily.index', ['date' => '2026-07-20']))
        ->assertOk()
        ->assertSee('Shift Belum Diatur')
        ->assertSee('Belum dijadwalkan');
});
