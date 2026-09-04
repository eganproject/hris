<?php

use App\Enums\AttendanceStatus;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Shift;
use App\Models\User;
use App\Services\AttendanceResolver;
use App\Services\AttendanceRollup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/** Pengguna yang boleh meninjau Log Punch. */
function punchReviewer(): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $permissions = ['punches.view', 'punches.update', 'attendance.view.all'];

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::factory()->create();
    $user->givePermissionTo($permissions);
    $user->forceFill(['bypass_team_scope' => true])->save();

    return $user;
}

/** @return array{0: Employee, 1: Shift} */
function boardEmployee(): array
{
    $shift = Shift::query()->create([
        'code' => 'REG', 'name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00',
        'crosses_midnight' => false, 'break_minutes' => 60, 'late_tolerance_minutes' => 10,
        'overtime_starts_after_minutes' => 0, 'overtime_min_minutes' => 0, 'is_active' => true,
    ]);

    $employee = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active']);

    foreach (['2026-02-10', '2026-02-11'] as $date) {
        EmployeeSchedule::query()->create([
            'employee_id' => $employee->id, 'work_date' => $date,
            'shift_id' => $shift->id, 'is_day_off' => false, 'source' => 'generated',
        ]);
    }

    return [$employee, $shift];
}

test('koreksi absensi kemarin tidak dibatalkan oleh punch hari ini', function () {
    [$employee] = boardEmployee();
    $kemarin = Carbon::parse('2026-02-10');
    $rollup = app(AttendanceRollup::class);

    // Kemarin: karyawan hanya tap masuk 08:05, lupa tap pulang.
    $employee->punches()->create(['punched_at' => '2026-02-10 08:05:00', 'machine_user_id' => '17', 'status' => 'matched', 'dedup_hash' => 'in-10']);
    $rollup->rebuild($employee, $kemarin);

    // HR membetulkannya di papan Absensi Harian: jam masuk 08:00, pulang 17:00.
    app(AttendanceResolver::class)->resolve($employee, $kemarin, '08:00', '17:00', 'Dibetulkan HR.');

    // Hari ini karyawan tap masuk. Satu punch baru memicu hitung ulang untuk hari
    // ini DAN kemarin (shift malam bisa menyeberang tengah malam), jadi koreksi
    // kemarin ikut terkena.
    $employee->punches()->create(['punched_at' => '2026-02-11 08:00:00', 'machine_user_id' => '17', 'status' => 'matched', 'dedup_hash' => 'in-11']);
    $rollup->rebuild($employee, $kemarin);
    $rollup->rebuild($employee, Carbon::parse('2026-02-11'));

    $absensiKemarin = $employee->attendances()->whereDate('work_date', '2026-02-10')->firstOrFail();

    expect($absensiKemarin->clock_in?->format('H:i'))->toBe('08:00')
        ->and($absensiKemarin->clock_out?->format('H:i'))->toBe('17:00')
        ->and($absensiKemarin->note)->toBe('Dibetulkan HR.');
});

test('menandai punch diabaikan langsung memperbarui absensinya', function () {
    [$employee] = boardEmployee();
    $date = Carbon::parse('2026-02-10');

    $employee->punches()->create(['punched_at' => '2026-02-10 08:05:00', 'machine_user_id' => '17', 'status' => 'matched', 'dedup_hash' => 'in']);
    $salah = $employee->punches()->create(['punched_at' => '2026-02-10 23:50:00', 'machine_user_id' => '17', 'status' => 'matched', 'dedup_hash' => 'salah']);

    app(AttendanceRollup::class)->rebuild($employee, $date);

    expect($employee->attendances()->firstOrFail()->clock_out?->format('H:i'))->toBe('23:50');

    $hr = punchReviewer();

    $this->actingAs($hr)->post(route('attendance.punches.ignore', $salah))->assertRedirect();

    // Jam pulang keliru itu hilang seketika, tanpa menunggu punch berikutnya datang.
    $absensi = $employee->attendances()->firstOrFail();

    expect($absensi->clock_out)->toBeNull()
        ->and($absensi->clock_in?->format('H:i'))->toBe('08:05');
});

test('mengabaikan satu-satunya punch mengosongkan jam masuknya juga', function () {
    [$employee] = boardEmployee();
    $date = Carbon::parse('2026-02-10');

    $punch = $employee->punches()->create(['punched_at' => '2026-02-10 08:05:00', 'machine_user_id' => '17', 'status' => 'matched', 'dedup_hash' => 'satu']);
    app(AttendanceRollup::class)->rebuild($employee, $date);

    $this->actingAs(punchReviewer())->post(route('attendance.punches.ignore', $punch))->assertRedirect();

    $absensi = $employee->attendances()->firstOrFail();

    // Tidak ada lagi bukti kehadiran hari itu, jadi statusnya ikut dihitung ulang.
    expect($absensi->clock_in)->toBeNull()
        ->and($absensi->status)->toBe(AttendanceStatus::Absent);
});

test('jam yang ditulis manusia tidak ikut terhapus saat punch diabaikan', function () {
    [$employee] = boardEmployee();
    $date = Carbon::parse('2026-02-10');

    $salah = $employee->punches()->create(['punched_at' => '2026-02-10 23:50:00', 'machine_user_id' => '17', 'status' => 'matched', 'dedup_hash' => 'salah']);
    app(AttendanceRollup::class)->rebuild($employee, $date);

    // HR sudah membetulkan jam masuk dan pulangnya di papan harian.
    app(AttendanceResolver::class)->resolve($employee, $date, '08:00', '17:00', 'Dibetulkan HR.');

    $this->actingAs(punchReviewer())->post(route('attendance.punches.ignore', $salah))->assertRedirect();

    $absensi = $employee->attendances()->firstOrFail();

    expect($absensi->clock_in?->format('H:i'))->toBe('08:00')
        ->and($absensi->clock_out?->format('H:i'))->toBe('17:00');
});
