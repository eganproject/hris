<?php

use App\Enums\ScheduleSource;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\ScheduleAssignment;
use App\Models\Shift;
use App\Services\ScheduleGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/**
 * Memindahkan karyawan ke "ikut jam kantor" harus benar-benar melepas penjadwalan
 * lamanya. Jadwal jam kantor diturunkan dari pola saat dibaca, dan baris jadwal nyata
 * selalu menang atas turunan itu — jadi penugasan pola dan baris hasil generate yang
 * tertinggal membuat flag-nya cuma jadi label.
 */
function transitionShift(): Shift
{
    return Shift::query()->create([
        'code' => 'REG', 'name' => 'Reguler',
        'start_time' => '08:00', 'end_time' => '17:00', 'is_active' => true,
    ]);
}

/** Karyawan shift dengan penugasan pola terbuka yang sudah berjalan sejak bulan lalu. */
function scheduledEmployee(string $name = 'Budi'): array
{
    $shift = transitionShift();
    $employee = Employee::query()->create(['full_name' => $name, 'employment_status' => 'active']);
    $pattern = weeklyPattern($shift->id);

    $assignment = ScheduleAssignment::query()->create([
        'employee_id' => $employee->id,
        'schedule_pattern_id' => $pattern->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => null,
    ]);

    app(ScheduleGenerator::class)->forEmployee($employee, now()->subMonth(), now()->addMonth());

    return [$employee, $assignment, $shift];
}

test('the generator writes nothing for an employee on office hours', function () {
    [$employee] = scheduledEmployee();

    $employee->update(['follows_office_hours' => true]);

    $before = EmployeeSchedule::query()->where('employee_id', $employee->id)->count();
    $written = app(ScheduleGenerator::class)->forEmployee($employee->fresh(), now(), now()->addMonth());

    expect($written)->toBe(0)
        ->and(EmployeeSchedule::query()->where('employee_id', $employee->id)->count())->toBe($before);
});

test('the bulk action stops the pattern assignment and clears the schedule ahead', function () {
    [$employee, $assignment] = scheduledEmployee();
    $user = employeeManager();

    $aheadBefore = EmployeeSchedule::query()
        ->where('employee_id', $employee->id)
        ->whereDate('work_date', '>=', now()->toDateString())
        ->count();

    expect($aheadBefore)->toBeGreaterThan(0);

    $this->actingAs($user)
        ->post('/employees/bulk/office-hours', ['employee_ids' => [$employee->id], 'follows' => 1])
        ->assertRedirect('/employees');

    expect($assignment->fresh()->end_date->toDateString())->toBe(now()->subDay()->toDateString())
        ->and(EmployeeSchedule::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', '>=', now()->toDateString())
            ->count())->toBe(0);
});

test('the cleanup leaves history and manual overrides alone', function () {
    [$employee, , $shift] = scheduledEmployee();
    $user = employeeManager();

    $pastDays = EmployeeSchedule::query()
        ->where('employee_id', $employee->id)
        ->whereDate('work_date', '<', now()->toDateString())
        ->count();

    // Satu hari ke depan diubah manual: override milik pengguna tidak boleh ikut hilang.
    $overrideDate = now()->addWeek()->toDateString();
    app(ScheduleGenerator::class)->override($employee, Carbon::parse($overrideDate), $shift->id, false, 'tukar dinas');

    $this->actingAs($user)
        ->post('/employees/bulk/office-hours', ['employee_ids' => [$employee->id], 'follows' => 1])
        ->assertRedirect('/employees');

    expect(EmployeeSchedule::query()
        ->where('employee_id', $employee->id)
        ->whereDate('work_date', '<', now()->toDateString())
        ->count())->toBe($pastDays)
        ->and(EmployeeSchedule::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $overrideDate)
            ->value('source'))->toBe(ScheduleSource::Manual);
});

test('an assignment that has not started yet is dropped instead of closed', function () {
    $shift = transitionShift();
    $employee = Employee::query()->create(['full_name' => 'Sari', 'employment_status' => 'active']);

    $assignment = ScheduleAssignment::query()->create([
        'employee_id' => $employee->id,
        'schedule_pattern_id' => weeklyPattern($shift->id)->id,
        'start_date' => now()->addWeek()->toDateString(),
        'end_date' => null,
    ]);

    $this->actingAs(employeeManager())
        ->post('/employees/bulk/office-hours', ['employee_ids' => [$employee->id], 'follows' => 1])
        ->assertRedirect('/employees');

    expect(ScheduleAssignment::query()->whereKey($assignment->id)->exists())->toBeFalse();
});

test('returning someone to manual scheduling touches no schedule at all', function () {
    [$employee, $assignment] = scheduledEmployee();
    $employee->update(['follows_office_hours' => true]);

    $total = EmployeeSchedule::query()->where('employee_id', $employee->id)->count();

    $this->actingAs(employeeManager())
        ->post('/employees/bulk/office-hours', ['employee_ids' => [$employee->id], 'follows' => 0])
        ->assertRedirect('/employees');

    expect(EmployeeSchedule::query()->where('employee_id', $employee->id)->count())->toBe($total)
        ->and($assignment->fresh()->end_date)->toBeNull();
});
