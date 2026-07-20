<?php

use App\Enums\SchedulePatternType;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\ScheduleAssignment;
use App\Models\SchedulePattern;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * HR penjadwalan dengan cakupan penuh + satu karyawan bershift hari ini.
 *
 * @return array{user: User, employee: Employee, shift: Shift, date: string}
 */
function rosterAjaxFixture(): array
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $permissions = [...attendanceMenuPermissions(), 'attendance.view.all'];

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    $employee = Employee::query()->create(['full_name' => 'Rian', 'employment_status' => 'active']);
    $shift = Shift::query()->create([
        'code' => 'PG', 'name' => 'Pagi', 'start_time' => '07:00', 'end_time' => '15:00', 'is_active' => true,
    ]);

    $date = now()->toDateString();
    EmployeeSchedule::query()->create([
        'employee_id' => $employee->id, 'work_date' => $date,
        'shift_id' => null, 'is_day_off' => true, 'source' => 'generated',
    ]);

    return compact('user', 'employee', 'shift', 'date');
}

test('an ajax override returns the re-rendered cell instead of a redirect', function () {
    $f = rosterAjaxFixture();

    $response = $this->actingAs($f['user'])->postJson('/attendance/schedules/override', [
        'employee_id' => $f['employee']->id,
        'work_date' => $f['date'],
        'shift_id' => $f['shift']->id,
    ]);

    $response->assertOk()->assertJsonStructure(['status', 'cell']);

    // Sel yang dikirim balik sudah memuat shift baru dan penanda override manual.
    expect($response->json('cell'))->toContain('PG')
        ->and($response->json('cell'))->toContain('data-cell')
        ->and($response->json('status'))->toBe('Jadwal harian diperbarui.');

    $this->assertDatabaseHas('employee_schedules', [
        'employee_id' => $f['employee']->id,
        'work_date' => $f['date'],
        'shift_id' => $f['shift']->id,
        'is_day_off' => false,
    ]);
});

test('a non-ajax override still redirects, so the page keeps working without js', function () {
    $f = rosterAjaxFixture();

    $this->actingAs($f['user'])->post('/attendance/schedules/override', [
        'employee_id' => $f['employee']->id,
        'work_date' => $f['date'],
        'shift_id' => $f['shift']->id,
    ])->assertRedirect();
});

test('an ajax generate returns a json status', function () {
    $f = rosterAjaxFixture();

    $this->actingAs($f['user'])->postJson('/attendance/schedules/generate', [
        'month' => now()->format('Y-m'),
    ])->assertOk()->assertJsonStructure(['status', 'days']);
});

test('an ajax assignment delete returns json and removes the row', function () {
    $f = rosterAjaxFixture();

    $pattern = SchedulePattern::query()->create([
        'code' => 'W', 'name' => 'Weekly', 'type' => SchedulePatternType::FixedWeekly,
        'cycle_length' => 7, 'is_active' => true,
    ]);

    $assignment = ScheduleAssignment::query()->create([
        'employee_id' => $f['employee']->id, 'schedule_pattern_id' => $pattern->id,
        'start_date' => now()->startOfMonth()->toDateString(), 'end_date' => null,
        'created_by' => $f['user']->id,
    ]);

    $this->actingAs($f['user'])
        ->deleteJson("/attendance/schedules/assignments/{$assignment->id}")
        ->assertOk()
        ->assertJsonStructure(['status']);

    $this->assertDatabaseMissing('schedule_assignments', ['id' => $assignment->id]);
});
