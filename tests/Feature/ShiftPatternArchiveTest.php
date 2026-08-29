<?php

use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\ScheduleAssignment;
use App\Models\SchedulePattern;
use App\Models\Shift;
use App\Services\ScheduleGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Shift dan pola jadwal diarsipkan, bukan dibuang.
 *
 * Yang dijaga di sini bukan cuma tombolnya, tapi janjinya: data yang sudah menunjuk
 * baris terarsip tidak boleh berubah, dan barisnya harus bisa dikembalikan utuh.
 */
function archivableShift(string $code = 'MLM'): Shift
{
    return Shift::query()->create([
        'code' => $code, 'name' => "Shift {$code}",
        'start_time' => '22:00', 'end_time' => '06:00',
        'break_minutes' => 60, 'is_active' => true,
    ]);
}

test('deleting a shift archives it instead of dropping the row', function () {
    $admin = masterDataAdmin();
    $shift = archivableShift();

    $this->actingAs($admin)->delete(route('attendance.shifts.destroy', $shift))->assertRedirect();

    $this->assertSoftDeleted('shifts', ['id' => $shift->id]);

    // Dicek lewat tautan barisnya, bukan namanya: nama shift juga muncul di pesan
    // sukses yang ikut terbawa ke halaman berikutnya.
    $this->actingAs($admin)->get(route('attendance.shifts.index'))
        ->assertDontSee(route('attendance.shifts.edit', $shift));

    $this->actingAs($admin)->get(route('attendance.shifts.index', ['status' => 'archived']))
        ->assertSee('Shift MLM')
        ->assertSee(route('attendance.shifts.restore', $shift));
});

test('an archived shift can be restored', function () {
    $admin = masterDataAdmin();
    $shift = archivableShift();
    $shift->delete();

    $this->actingAs($admin)->post(route('attendance.shifts.restore', $shift))->assertRedirect();

    expect(Shift::query()->whereKey($shift->id)->exists())->toBeTrue();
    $this->actingAs($admin)->get(route('attendance.shifts.index'))->assertSee('Shift MLM');
});

test('a roster row keeps showing a shift that was archived after it was written', function () {
    $shift = archivableShift();
    $employee = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active']);

    $schedule = EmployeeSchedule::query()->create([
        'employee_id' => $employee->id,
        'work_date' => now()->toDateString(),
        'shift_id' => $shift->id,
    ]);

    $shift->delete();

    // Tanpa withTrashed pada relasinya, sel roster dan rekap absensi bulan lalu
    // mendadak kosong hanya karena shift-nya dirapikan dari daftar.
    expect($schedule->fresh()->shift?->code)->toBe('MLM');
});

test('a code held by an archived shift is refused with a message that says so', function () {
    $admin = masterDataAdmin();
    archivableShift()->delete();

    $response = $this->actingAs($admin)->post(route('attendance.shifts.store'), [
        'code' => 'MLM', 'name' => 'Shift Malam Baru',
        'start_time' => '22:00', 'end_time' => '06:00',
        'break_minutes' => 60, 'is_active' => '1',
    ]);

    $response->assertSessionHasErrors('code');
    expect(session('errors')->first('code'))->toContain('arsip');
});

test('archiving a pattern keeps the assignments that used to be cascaded away', function () {
    $shift = archivableShift('REG');
    $pattern = weeklyPattern($shift->id);
    $employee = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active']);

    $assignment = ScheduleAssignment::query()->create([
        'employee_id' => $employee->id,
        'schedule_pattern_id' => $pattern->id,
        'start_date' => now()->startOfMonth()->toDateString(),
        'end_date' => null,
    ]);

    $pattern->delete();

    expect(ScheduleAssignment::query()->whereKey($assignment->id)->exists())->toBeTrue()
        // Polanya harus tetap terbaca lewat relasinya, kalau tidak generator melihat
        // pola kosong dan meliburkan seluruh hari.
        ->and($assignment->fresh()->pattern?->id)->toBe($pattern->id);

    $written = app(ScheduleGenerator::class)->forEmployee($employee, now()->startOfMonth(), now()->endOfMonth());

    expect($written)->toBeGreaterThan(0);
});

test('the pattern used as the global office default refuses to be archived', function () {
    $admin = masterDataAdmin();
    [, $default] = globalOfficeDefault();

    $this->actingAs($admin)
        ->delete(route('attendance.schedule-patterns.destroy', $default))
        ->assertSessionHas('error');

    expect(SchedulePattern::query()->whereKey($default->id)->exists())->toBeTrue();
});

test('an archived pattern can be restored', function () {
    $admin = masterDataAdmin();
    $pattern = weeklyPattern(archivableShift('REG')->id);
    $pattern->delete();

    $this->actingAs($admin)->post(route('attendance.schedule-patterns.restore', $pattern))->assertRedirect();

    expect(SchedulePattern::query()->whereKey($pattern->id)->exists())->toBeTrue();
});

test('the pattern form still offers a shift the pattern uses after that shift is archived', function () {
    $admin = masterDataAdmin();
    $shift = archivableShift('REG');
    $pattern = weeklyPattern($shift->id);

    $shift->delete();

    // Kalau opsinya hilang, select jatuh ke "Libur" dan sekali simpan pola itu
    // kehilangan seluruh hari kerjanya tanpa ada yang mengubahnya.
    $this->actingAs($admin)
        ->get(route('attendance.schedule-patterns.edit', $pattern))
        ->assertSee('REG — Shift REG (arsip)');
});

test('the pattern list counts people once, however many assignments they have', function () {
    $admin = masterDataAdmin();
    $pattern = weeklyPattern(archivableShift('REG')->id);
    $employee = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active']);

    // Perpanjangan: satu orang, dua penugasan pada pola yang sama.
    foreach ([['2026-01-01', '2026-06-30'], ['2026-07-01', null]] as [$start, $end]) {
        ScheduleAssignment::query()->create([
            'employee_id' => $employee->id,
            'schedule_pattern_id' => $pattern->id,
            'start_date' => $start,
            'end_date' => $end,
        ]);
    }

    $this->actingAs($admin)
        ->get(route('attendance.schedule-patterns.index'))
        ->assertOk()
        ->assertSee('1 penugasan');
});

test('the archived tab lists archived patterns with a restore action', function () {
    $admin = masterDataAdmin();
    $pattern = weeklyPattern(archivableShift('REG')->id);
    $pattern->delete();

    $this->actingAs($admin)
        ->get(route('attendance.schedule-patterns.index', ['status' => 'archived']))
        ->assertOk()
        ->assertSee($pattern->name)
        ->assertSee(route('attendance.schedule-patterns.restore', $pattern));
});

test('the pattern employees page lists all three ways of reaching the pattern', function () {
    $admin = masterDataAdmin();
    [, $default] = globalOfficeDefault();

    $assigned = Employee::query()->create(['full_name' => 'Shift Worker', 'employment_status' => 'active']);
    ScheduleAssignment::query()->create([
        'employee_id' => $assigned->id,
        'schedule_pattern_id' => $default->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => null,
    ]);

    $ownPattern = Employee::query()->create([
        'full_name' => 'Kantoran Pilih Sendiri', 'employment_status' => 'active',
        'follows_office_hours' => true, 'office_pattern_id' => $default->id,
    ]);

    $followsDefault = Employee::query()->create([
        'full_name' => 'Kantoran Ikut Default', 'employment_status' => 'active',
        'follows_office_hours' => true, 'office_pattern_id' => null,
    ]);

    $unrelated = Employee::query()->create(['full_name' => 'Tidak Terkait', 'employment_status' => 'active']);

    $this->actingAs($admin)
        ->get(route('attendance.schedule-patterns.employees', $default))
        ->assertOk()
        ->assertSee($assigned->full_name)
        ->assertSee($ownPattern->full_name)
        ->assertSee($followsDefault->full_name)
        ->assertDontSee($unrelated->full_name);
});

test('an employee following the global default is not listed under an unrelated pattern', function () {
    $admin = masterDataAdmin();
    globalOfficeDefault();
    $other = weeklyPattern(archivableShift('REG')->id);

    $kantoran = Employee::query()->create([
        'full_name' => 'Kantoran Ikut Default', 'employment_status' => 'active',
        'follows_office_hours' => true, 'office_pattern_id' => null,
    ]);

    $this->actingAs($admin)
        ->get(route('attendance.schedule-patterns.employees', $other))
        ->assertOk()
        ->assertDontSee($kantoran->full_name);
});

test('the employees page still opens for an archived pattern', function () {
    $admin = masterDataAdmin();
    $pattern = weeklyPattern(archivableShift('REG')->id);

    $employee = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active']);
    ScheduleAssignment::query()->create([
        'employee_id' => $employee->id,
        'schedule_pattern_id' => $pattern->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => null,
    ]);

    $pattern->delete();

    // Justru setelah diarsipkan pertanyaannya paling mendesak: siapa yang terdampak?
    $this->actingAs($admin)
        ->get(route('attendance.schedule-patterns.employees', $pattern))
        ->assertOk()
        ->assertSee('Diarsipkan')
        ->assertSee($employee->full_name);
});
