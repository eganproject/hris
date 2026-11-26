<?php

use App\Enums\LeaveRequestStatus;
use App\Models\Attendance;
use App\Models\EmployeeSchedule;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OvertimeApproval;
use App\Models\ScheduleAcknowledgement;
use App\Models\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

require_once __DIR__.'/MobileHelpers.php';

uses(RefreshDatabase::class);

function teamShift(): Shift
{
    return Shift::query()->create([
        'code' => 'P', 'name' => 'Pagi', 'start_time' => '07:00', 'end_time' => '15:00', 'is_active' => true,
    ]);
}

test('menu atasan tertutup bagi karyawan tanpa bawahan', function () {
    [$user] = mobileEmployee('Budi');

    mobileAs($user)->getJson('/api/mobile/v1/approvals')->assertForbidden();
    mobileAs($user)->getJson('/api/mobile/v1/team/today')->assertForbidden();
    mobileAs($user)->getJson('/api/mobile/v1/team/schedule')->assertForbidden();
});

test('atasan melihat dan menyetujui cuti & lembur bawahan langsung', function () {
    [$bossUser, $boss] = mobileEmployee('Andi Atasan');
    [$staffUser, $staff] = mobileEmployee('Siti', ['manager_id' => $boss->id]);
    $type = LeaveType::query()->create(['code' => 'CT', 'name' => 'Cuti Tahunan', 'attendance_status' => 'leave', 'counts_against_balance' => true, 'default_quota_days' => 12, 'is_active' => true]);

    $leave = LeaveRequest::query()->create([
        'employee_id' => $staff->id, 'leave_type_id' => $type->id, 'supervisor_id' => $boss->id,
        'start_date' => now()->addDays(5), 'end_date' => now()->addDays(6), 'status' => LeaveRequestStatus::PendingSupervisor,
    ]);
    $overtime = OvertimeApproval::query()->create([
        'employee_id' => $staff->id, 'supervisor_id' => $boss->id, 'work_date' => now()->subDay(),
        'start_time' => '17:00', 'end_time' => '19:30', 'requested_minutes' => 150, 'status' => 'pending',
    ]);

    mobileAs($bossUser)->getJson('/api/mobile/v1/approvals')
        ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.employee.name', 'Siti');

    mobileAs($bossUser)->postJson("/api/mobile/v1/approvals/leave/{$leave->id}/approve")->assertOk();
    mobileAs($bossUser)->postJson("/api/mobile/v1/approvals/overtime/{$overtime->id}/approve", ['approved_minutes' => 120])->assertOk();

    expect($leave->fresh()->status)->toBe(LeaveRequestStatus::Approved)
        ->and($overtime->fresh()->status)->toBe('approved')
        ->and($overtime->fresh()->approved_minutes)->toBe(120)
        ->and($staffUser->notifications()->count())->toBe(2);

    // Sudah final: tidak bisa diputuskan dua kali.
    mobileAs($bossUser)->postJson("/api/mobile/v1/approvals/leave/{$leave->id}/reject")->assertStatus(409);
});

test('atasan lain tidak bisa memutuskan pengajuan yang bukan untuknya', function () {
    [, $boss] = mobileEmployee('Andi Atasan');
    [$otherBossUser, $otherBoss] = mobileEmployee('Dewi Atasan');
    mobileEmployee('Rudi', ['manager_id' => $otherBoss->id]);
    [, $staff] = mobileEmployee('Siti', ['manager_id' => $boss->id]);

    $overtime = OvertimeApproval::query()->create([
        'employee_id' => $staff->id, 'supervisor_id' => $boss->id, 'work_date' => now()->subDay(),
        'start_time' => '17:00', 'end_time' => '18:00', 'requested_minutes' => 60, 'status' => 'pending',
    ]);

    mobileAs($otherBossUser)->postJson("/api/mobile/v1/approvals/overtime/{$overtime->id}/approve")->assertForbidden();
});

test('HR bisa mengambil alih persetujuan lembur dengan izin overtime.update', function () {
    [, $boss] = mobileEmployee('Andi Atasan');
    [, $staff] = mobileEmployee('Siti', ['manager_id' => $boss->id]);
    [$hrUser] = mobileEmployee('Rina HR');

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('overtime.update', 'web');
    Permission::findOrCreate('attendance.view.all', 'web');
    $hrUser->givePermissionTo(['overtime.update', 'attendance.view.all']);
    $hrUser->forceFill(['bypass_team_scope' => true])->save();

    $overtime = OvertimeApproval::query()->create([
        'employee_id' => $staff->id, 'supervisor_id' => $boss->id, 'work_date' => now()->subDay(),
        'start_time' => '17:00', 'end_time' => '18:00', 'requested_minutes' => 60, 'status' => 'pending',
    ]);

    mobileAs($hrUser)->getJson('/api/mobile/v1/approvals')->assertOk()->assertJsonPath('data.0.as_hr', true);
    mobileAs($hrUser)->postJson("/api/mobile/v1/approvals/overtime/{$overtime->id}/reject", ['notes' => 'Tidak sesuai'])->assertOk();

    expect($overtime->fresh()->status)->toBe('rejected')
        ->and($staff->user->notifications()->first()->data['message'])->toContain('oleh HR');
});

test('tim hari ini menghitung hadir, telat, dan belum absen', function () {
    [$bossUser, $boss] = mobileEmployee('Andi Atasan');
    [, $a] = mobileEmployee('Ayu', ['manager_id' => $boss->id]);
    [, $b] = mobileEmployee('Siti', ['manager_id' => $boss->id]);
    [, $c] = mobileEmployee('Rudi', ['manager_id' => $boss->id]);
    $shift = teamShift();
    $today = now()->toDateString();

    foreach ([$a, $b, $c] as $member) {
        EmployeeSchedule::query()->create(['employee_id' => $member->id, 'work_date' => $today, 'shift_id' => $shift->id]);
    }
    Attendance::query()->create(['employee_id' => $a->id, 'work_date' => $today, 'status' => 'present', 'clock_in' => now()->setTime(6, 55)]);
    Attendance::query()->create(['employee_id' => $b->id, 'work_date' => $today, 'status' => 'late', 'clock_in' => now()->setTime(7, 21), 'late_minutes' => 21]);

    mobileAs($bossUser)->getJson('/api/mobile/v1/team/today')
        ->assertOk()
        ->assertJsonPath('data.team_size', 3)
        ->assertJsonPath('data.counts.present', 1)
        ->assertJsonPath('data.counts.late', 1)
        ->assertJsonPath('data.counts.not_yet', 1)
        ->assertJsonPath('data.members.0.employee.name', 'Rudi');
});

test('tim hari ini bisa difilter tanggal, nama, status, dan bawahan langsung', function () {
    [$bossUser, $boss] = mobileEmployee('Andi Atasan');
    [, $a] = mobileEmployee('Ayu', ['manager_id' => $boss->id]);
    [, $b] = mobileEmployee('Siti', ['manager_id' => $boss->id]);
    [, $c] = mobileEmployee('Rudi Cucu', ['manager_id' => $a->id]);
    $shift = teamShift();
    $yesterday = now()->subDay()->toDateString();

    foreach ([$a, $b, $c] as $member) {
        EmployeeSchedule::query()->create(['employee_id' => $member->id, 'work_date' => $yesterday, 'shift_id' => $shift->id]);
    }
    Attendance::query()->create(['employee_id' => $b->id, 'work_date' => $yesterday, 'status' => 'late', 'clock_in' => now()->subDay()->setTime(7, 21), 'late_minutes' => 21]);
    Attendance::query()->create(['employee_id' => $c->id, 'work_date' => $yesterday, 'status' => 'present', 'clock_in' => now()->subDay()->setTime(6, 50)]);

    // Kemarin: Siti telat, Rudi hadir, Ayu tidak absen.
    mobileAs($bossUser)->getJson("/api/mobile/v1/team/today?date={$yesterday}&state=late")
        ->assertOk()
        ->assertJsonPath('data.date', $yesterday)
        ->assertJsonPath('data.is_today', false)
        ->assertJsonCount(1, 'data.members')
        ->assertJsonPath('data.members.0.employee.name', 'Siti')
        ->assertJsonPath('data.by_state.not_yet', 1)
        ->assertJsonPath('data.by_state.present', 1);

    mobileAs($bossUser)->getJson("/api/mobile/v1/team/today?date={$yesterday}&q=rudi")
        ->assertJsonCount(1, 'data.members')
        ->assertJsonPath('data.members.0.employee.name', 'Rudi Cucu');

    mobileAs($bossUser)->getJson("/api/mobile/v1/team/today?date={$yesterday}&direct=1")
        ->assertJsonPath('data.team_size', 2);

    mobileAs($bossUser)->getJson('/api/mobile/v1/team/today?date='.now()->addDay()->toDateString())->assertStatus(422);
});

test('atasan mengatur, menyalin, dan menerbitkan jadwal tim', function () {
    [$bossUser, $boss] = mobileEmployee('Andi Atasan');
    [$staffUser, $staff] = mobileEmployee('Dimas', ['manager_id' => $boss->id]);
    [, $outsider] = mobileEmployee('Orang Lain');
    $shift = teamShift();
    $weekStart = now()->addWeek()->startOfWeek();

    // Minggu lalu (relatif terhadap minggu target) punya jadwal Pagi di hari Senin.
    EmployeeSchedule::query()->create(['employee_id' => $staff->id, 'work_date' => $weekStart->copy()->subWeek()->toDateString(), 'shift_id' => $shift->id]);

    mobileAs($bossUser)->postJson('/api/mobile/v1/team/schedule/copy-last-week', ['week_start' => $weekStart->toDateString()])
        ->assertOk()->assertJsonPath('data.members.0.days.0.code', 'P');

    mobileAs($bossUser)->putJson('/api/mobile/v1/team/schedule', [
        'employee_id' => $staff->id, 'date' => $weekStart->copy()->addDay()->toDateString(), 'is_day_off' => true,
    ])->assertOk()->assertJsonPath('data.members.0.days.1.code', 'L');

    // Bukan anggota tim, dan hari yang sudah lewat, ditolak.
    mobileAs($bossUser)->putJson('/api/mobile/v1/team/schedule', [
        'employee_id' => $outsider->id, 'date' => $weekStart->toDateString(), 'shift_id' => $shift->id,
    ])->assertForbidden();
    mobileAs($bossUser)->putJson('/api/mobile/v1/team/schedule', [
        'employee_id' => $staff->id, 'date' => now()->subDay()->toDateString(), 'shift_id' => $shift->id,
    ])->assertUnprocessable();

    mobileAs($bossUser)->postJson('/api/mobile/v1/team/schedule/publish', ['week_start' => $weekStart->toDateString()])->assertOk();

    expect(ScheduleAcknowledgement::query()->where('employee_id', $staff->id)->exists())->toBeTrue()
        ->and($staffUser->notifications()->first()->data['category'])->toBe('schedule');

    // Karyawan melihat permintaan konfirmasi di Jadwal saya, lalu mengonfirmasi.
    mobileAs($staffUser)->getJson('/api/mobile/v1/schedule')
        ->assertOk()->assertJsonPath('data.pending_confirmations.0.week_start', $weekStart->toDateString());
    mobileAs($staffUser)->postJson('/api/mobile/v1/schedule/confirm', ['week_start' => $weekStart->toDateString()])->assertOk();

    expect(ScheduleAcknowledgement::query()->first()->confirmed_at)->not->toBeNull();
});

test('tim hari ini mencakup bawahan semua level, lengkap dengan atasan langsungnya', function () {
    [$egaUser, $ega] = mobileEmployee('Ega');
    [, $okan] = mobileEmployee('Okan', ['manager_id' => $ega->id]);
    [, $budi] = mobileEmployee('Budi', ['manager_id' => $okan->id]);
    [, $cici] = mobileEmployee('Cici', ['manager_id' => $budi->id]);
    mobileEmployee('Orang Lain');

    $response = mobileAs($egaUser)->getJson('/api/mobile/v1/team/today')
        ->assertOk()
        ->assertJsonPath('data.team_size', 3);

    $byName = collect($response->json('data.members'))->keyBy('employee.name');
    expect($byName->keys()->sort()->values()->all())->toBe(['Budi', 'Cici', 'Okan'])
        ->and($byName['Okan']['is_direct'])->toBeTrue()
        ->and($byName['Budi']['manager_name'])->toBe('Okan')
        ->and($byName['Cici']['manager_name'])->toBe('Budi');

    // Okan hanya melihat timnya sendiri.
    expect($okan->user)->not->toBeNull();
    mobileAs($okan->user)->getJson('/api/mobile/v1/team/today')->assertJsonPath('data.team_size', 2);

    // Pengingat juga boleh ke bawahan tidak langsung.
    mobileAs($egaUser)->postJson("/api/mobile/v1/team/{$cici->id}/remind")->assertOk();
});
