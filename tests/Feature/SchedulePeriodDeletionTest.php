<?php

use App\Enums\AttendanceStatus;
use App\Enums\ScheduleSource;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\ScheduleAssignment;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Membuang sisa jadwal satu bulan milik satu karyawan.
 *
 * Menghapus pola atau penugasannya sengaja tidak menghapus baris yang terlanjur
 * dibuat, jadi tanpa aksi ini jadwal dari pola yang sudah tidak ada bisa menetap
 * selamanya. Yang dijaga di sini adalah dua hal yang TIDAK boleh ikut terbawa.
 */
function periodShift(): Shift
{
    return Shift::query()->create([
        'code' => 'REG', 'name' => 'Reguler',
        'start_time' => '08:00', 'end_time' => '17:00', 'is_active' => true,
    ]);
}

function periodScheduleRow(Employee $employee, string $date, int $shiftId, ScheduleSource $source = ScheduleSource::Generated): EmployeeSchedule
{
    return EmployeeSchedule::query()->create([
        'employee_id' => $employee->id,
        'work_date' => $date,
        'shift_id' => $shiftId,
        'source' => $source,
    ]);
}

test('it clears the generated schedule of the month being viewed', function () {
    $user = scheduleManager();
    $shift = periodShift();
    $employee = Employee::query()->create(['full_name' => 'Bima', 'employment_status' => 'active']);

    foreach (['2026-07-01', '2026-07-02', '2026-07-03'] as $date) {
        periodScheduleRow($employee, $date, $shift->id);
    }

    // Bulan lain tidak boleh ikut terbawa.
    periodScheduleRow($employee, '2026-08-03', $shift->id);

    $this->actingAs($user)
        ->delete(route('attendance.schedules.period.destroy', $employee), ['month' => '2026-07'])
        ->assertRedirect(route('attendance.schedules.show', ['employee' => $employee, 'month' => '2026-07']));

    expect(EmployeeSchedule::query()->where('employee_id', $employee->id)->pluck('work_date')
        ->map(fn ($date) => $date->toDateString())->all())->toBe(['2026-08-03']);
});

test('a manual override survives the cleanup', function () {
    $user = scheduleManager();
    $shift = periodShift();
    $employee = Employee::query()->create(['full_name' => 'Bima', 'employment_status' => 'active']);

    periodScheduleRow($employee, '2026-07-01', $shift->id);
    periodScheduleRow($employee, '2026-07-02', $shift->id, ScheduleSource::Manual);

    $this->actingAs($user)
        ->delete(route('attendance.schedules.period.destroy', $employee), ['month' => '2026-07'])
        ->assertRedirect();

    expect(EmployeeSchedule::query()->where('employee_id', $employee->id)->count())->toBe(1)
        ->and(EmployeeSchedule::query()->where('employee_id', $employee->id)->value('source'))->toBe(ScheduleSource::Manual);

    expect(session('status'))->toContain('override manual dipertahankan');
});

test('a day whose attendance is already recorded keeps its schedule', function () {
    $user = scheduleManager();
    $shift = periodShift();
    $employee = Employee::query()->create(['full_name' => 'Bima', 'employment_status' => 'active']);

    periodScheduleRow($employee, '2026-07-01', $shift->id);
    periodScheduleRow($employee, '2026-07-02', $shift->id);

    // Baris jadwal inilah dasar perhitungan absensi hari itu; mencabutnya membuat
    // hari yang sudah ditutup berubah hasilnya begitu diproses ulang.
    Attendance::query()->create([
        'employee_id' => $employee->id,
        'work_date' => '2026-07-01',
        'shift_id' => $shift->id,
        'status' => AttendanceStatus::Present,
    ]);

    $this->actingAs($user)
        ->delete(route('attendance.schedules.period.destroy', $employee), ['month' => '2026-07'])
        ->assertRedirect();

    expect(EmployeeSchedule::query()->where('employee_id', $employee->id)->pluck('work_date')
        ->map(fn ($date) => $date->toDateString())->all())->toBe(['2026-07-01']);

    expect(session('status'))->toContain('absensinya sudah tercatat');
});

test('it warns when a live assignment will simply rebuild the month', function () {
    $user = scheduleManager();
    $shift = periodShift();
    $employee = Employee::query()->create(['full_name' => 'Bima', 'employment_status' => 'active']);

    periodScheduleRow($employee, '2026-07-01', $shift->id);

    ScheduleAssignment::query()->create([
        'employee_id' => $employee->id,
        'schedule_pattern_id' => weeklyPattern($shift->id)->id,
        'start_date' => '2026-06-01',
        'end_date' => null,
    ]);

    $this->actingAs($user)
        ->delete(route('attendance.schedules.period.destroy', $employee), ['month' => '2026-07'])
        ->assertRedirect();

    expect(session('status'))->toContain('akan terbentuk lagi');
});

test('the button only shows up when there is something to delete', function () {
    $user = scheduleManager();
    $shift = periodShift();
    $employee = Employee::query()->create(['full_name' => 'Bima', 'employment_status' => 'active']);

    $url = route('attendance.schedules.period.destroy', $employee);

    $this->actingAs($user)
        ->get(route('attendance.schedules.show', ['employee' => $employee, 'month' => '2026-07']))
        ->assertDontSee($url);

    periodScheduleRow($employee, '2026-07-01', $shift->id);

    $this->actingAs($user)
        ->get(route('attendance.schedules.show', ['employee' => $employee, 'month' => '2026-07']))
        ->assertSee($url);
});

test('office-hours cells are not mistaken for something deletable', function () {
    $user = scheduleManager();
    [, $pattern] = globalOfficeDefault();

    $employee = Employee::query()->create([
        'full_name' => 'Kantoran', 'employment_status' => 'active',
        'follows_office_hours' => true, 'office_pattern_id' => $pattern->id,
    ]);

    // Bulan ini terisi penuh di layar, tapi tidak ada satu baris pun di database —
    // selnya diturunkan dari pola saat dibaca, jadi tak ada yang bisa dihapus.
    $this->actingAs($user)
        ->get(route('attendance.schedules.show', ['employee' => $employee, 'month' => '2026-07']))
        ->assertDontSee(route('attendance.schedules.period.destroy', $employee));
});

test('someone without the delete permission cannot clear a period', function () {
    $shift = periodShift();
    $employee = Employee::query()->create(['full_name' => 'Bima', 'employment_status' => 'active']);
    periodScheduleRow($employee, '2026-07-01', $shift->id);

    $viewer = scheduleViewerWithoutDelete();

    $this->actingAs($viewer)
        ->delete(route('attendance.schedules.period.destroy', $employee), ['month' => '2026-07'])
        ->assertForbidden();

    expect(EmployeeSchedule::query()->where('employee_id', $employee->id)->count())->toBe(1);
});

/** Pengguna yang boleh melihat jadwal tapi tidak boleh menghapusnya. */
function scheduleViewerWithoutDelete(): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $permissions = array_values(array_diff(attendanceMenuPermissions(), ['schedules.delete']));
    $permissions[] = 'attendance.view.all';

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::factory()->create();
    $user->givePermissionTo($permissions);
    // Absensi Harian & Jadwal Kerja dipersempit ke bawahan; pengguna ini
    // mewakili HR/administrator yang dikecualikan lewat Kontrol Akses.
    $user->forceFill(['bypass_team_scope' => true])->save();

    return $user;
}
