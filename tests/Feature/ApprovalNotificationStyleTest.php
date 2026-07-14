<?php

use App\Models\AttendanceCorrection;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\OvertimeApproval;
use App\Models\Shift;
use App\Models\ShiftSwapRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Karyawan + atasan (keduanya punya akun login) + satu HR yang boleh memutuskan
 * koreksi & tukar shift.
 *
 * @return array{employee: Employee, employeeUser: User, supervisor: Employee, supervisorUser: User, hr: User}
 */
function approvalStyleFixture(): array
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $selfService = ['my-overtime.view', 'my-attendance.view', 'my-schedule.view'];
    $hrPermissions = ['corrections.view', 'corrections.update', 'swaps.view', 'swaps.update', 'attendance.view.all'];

    foreach ([...$selfService, ...$hrPermissions] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $supervisorUser = User::factory()->create();
    $supervisorUser->givePermissionTo($selfService);
    $supervisor = Employee::query()->create([
        'user_id' => $supervisorUser->id, 'full_name' => 'Sari Atasan', 'employment_status' => 'active',
    ]);

    $employeeUser = User::factory()->create();
    $employeeUser->givePermissionTo($selfService);
    $employee = Employee::query()->create([
        'user_id' => $employeeUser->id, 'full_name' => 'Budi Staf', 'employment_status' => 'active',
        'manager_id' => $supervisor->id,
    ]);

    $hr = User::factory()->create();
    $hr->givePermissionTo($hrPermissions);

    return compact('employee', 'employeeUser', 'supervisor', 'supervisorUser', 'hr');
}

/** @return array<int, array{title: string, message: string}> */
function inboxOf(User $user): array
{
    return $user->notifications()->get()
        ->map(fn ($n) => ['title' => $n->data['title'], 'message' => $n->data['message']])
        ->all();
}

test('overtime notifications carry the date, the hours and who decided', function () {
    ['employeeUser' => $employeeUser, 'supervisorUser' => $supervisorUser] = approvalStyleFixture();

    $date = now()->subDay();

    $this->actingAs($employeeUser)->post('/my-overtime', [
        'work_date' => $date->toDateString(),
        'start_time' => '17:00',
        'end_time' => '19:00',
        'reason' => 'Kejar target produksi.',
    ])->assertRedirect('/my-overtime');

    $overtime = OvertimeApproval::query()->firstOrFail();
    $supervisorInbox = inboxOf($supervisorUser);

    expect($supervisorInbox[0]['title'])->toBe('Pengajuan lembur baru')
        ->and($supervisorInbox[0]['message'])->toContain('Budi Staf mengajukan lembur')
        ->and($supervisorInbox[0]['message'])->toContain($date->translatedFormat('D, d M Y'))
        ->and($supervisorInbox[0]['message'])->toContain('17:00–19:00, 2j 0m')
        ->and($supervisorInbox[0]['message'])->toContain('Kejar target produksi.');

    // Disetujui sebagian: yang ditampilkan adalah menit yang DISETUJUI.
    $this->actingAs($supervisorUser)
        ->patch("/my-overtime/{$overtime->id}/approve", ['approved_minutes' => 60])
        ->assertRedirect('/my-overtime');

    $employeeInbox = inboxOf($employeeUser);

    expect($employeeInbox[0]['title'])->toBe('Lembur disetujui')
        ->and($employeeInbox[0]['message'])->toContain($date->translatedFormat('D, d M Y'))
        ->and($employeeInbox[0]['message'])->toContain('1j 0m')
        ->and($employeeInbox[0]['message'])->toContain('disetujui oleh atasan');
});

test('cancelling an overtime request tells the supervisor waiting on it', function () {
    ['employeeUser' => $employeeUser, 'supervisorUser' => $supervisorUser] = approvalStyleFixture();

    $this->actingAs($employeeUser)->post('/my-overtime', [
        'work_date' => now()->subDay()->toDateString(),
        'start_time' => '17:00',
        'end_time' => '18:00',
        'reason' => 'Lembur singkat.',
    ])->assertRedirect('/my-overtime');

    $overtime = OvertimeApproval::query()->firstOrFail();
    $supervisorUser->notifications()->delete();

    $this->actingAs($employeeUser)->delete("/my-overtime/{$overtime->id}")->assertRedirect('/my-overtime');

    $inbox = inboxOf($supervisorUser);

    expect($inbox)->toHaveCount(1)
        ->and($inbox[0]['title'])->toBe('Pengajuan lembur dibatalkan')
        ->and($inbox[0]['message'])->toContain('Budi Staf membatalkan pengajuan lembur');
});

test('correction notifications carry the requested times and who decided', function () {
    ['employee' => $employee, 'employeeUser' => $employeeUser, 'hr' => $hr] = approvalStyleFixture();

    $date = now()->subDays(2);

    $this->actingAs($employeeUser)->post('/my-attendance/corrections', [
        'work_date' => $date->toDateString(),
        'requested_clock_in' => '08:00',
        'requested_clock_out' => '17:00',
        'reason' => 'Lupa absen pulang.',
    ])->assertRedirect('/my-attendance');

    $correction = AttendanceCorrection::query()->firstOrFail();
    $hrInbox = inboxOf($hr);

    expect($hrInbox[0]['title'])->toBe('Koreksi absensi baru')
        ->and($hrInbox[0]['message'])->toContain($date->translatedFormat('D, d M Y'))
        ->and($hrInbox[0]['message'])->toContain('masuk 08:00, keluar 17:00')
        ->and($hrInbox[0]['message'])->toContain('Lupa absen pulang.');

    $this->actingAs($hr)->patch("/attendance/corrections/{$correction->id}/approve")->assertRedirect();

    $employeeInbox = inboxOf($employeeUser);

    expect($employeeInbox[0]['title'])->toBe('Koreksi absensi disetujui')
        ->and($employeeInbox[0]['message'])->toContain($date->translatedFormat('D, d M Y'))
        ->and($employeeInbox[0]['message'])->toContain('disetujui oleh HR')
        ->and($employeeInbox[0]['message'])->toContain('Absensi hari itu sudah diperbarui.');
});

test('cancelling a correction tells HR it no longer needs a decision', function () {
    ['employeeUser' => $employeeUser, 'hr' => $hr] = approvalStyleFixture();

    $this->actingAs($employeeUser)->post('/my-attendance/corrections', [
        'work_date' => now()->subDay()->toDateString(),
        'requested_clock_in' => '08:00',
        'reason' => 'Salah input.',
    ])->assertRedirect('/my-attendance');

    $correction = AttendanceCorrection::query()->firstOrFail();
    $hr->notifications()->delete();

    $this->actingAs($employeeUser)->delete("/my-attendance/corrections/{$correction->id}")->assertRedirect('/my-attendance');

    $inbox = inboxOf($hr);

    expect($inbox)->toHaveCount(1)
        ->and($inbox[0]['title'])->toBe('Koreksi absensi dibatalkan')
        ->and($inbox[0]['message'])->toContain('Budi Staf membatalkan pengajuan koreksi absensi');
});

test('swap notifications carry the dates, the type and who decided', function () {
    ['employee' => $employee, 'employeeUser' => $employeeUser, 'supervisor' => $partner, 'supervisorUser' => $partnerUser, 'hr' => $hr] = approvalStyleFixture();

    $shift = Shift::query()->create(['code' => 'REG', 'name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00', 'is_active' => true]);
    $date = now()->addDays(3);

    // "Cover" = rekan mengambil alih shift saya, jadi rekan harus bebas hari itu.
    EmployeeSchedule::query()->create([
        'employee_id' => $employee->id, 'work_date' => $date->toDateString(),
        'shift_id' => $shift->id, 'is_day_off' => false, 'source' => 'generated',
    ]);
    EmployeeSchedule::query()->create([
        'employee_id' => $partner->id, 'work_date' => $date->toDateString(),
        'shift_id' => null, 'is_day_off' => true, 'source' => 'generated',
    ]);

    $this->actingAs($employeeUser)->post('/my-schedule/swaps', [
        'partner_id' => $partner->id,
        'requester_date' => $date->toDateString(),
        'type' => ShiftSwapRequest::TYPE_COVER,
        'reason' => 'Ada acara keluarga.',
    ])->assertRedirect('/my-schedule');

    $swap = ShiftSwapRequest::query()->firstOrFail();
    $partnerInbox = inboxOf($partnerUser);

    expect($partnerInbox[0]['title'])->toBe('Permintaan tukar jadwal')
        ->and($partnerInbox[0]['message'])->toContain('Budi Staf mengajukan')
        ->and($partnerInbox[0]['message'])->toContain($date->translatedFormat('D, d M Y'))
        ->and($partnerInbox[0]['message'])->toContain('Ada acara keluarga.');

    // Rekan setuju → HR mendapat jenis + tanggalnya, bukan hanya nama.
    $this->actingAs($partnerUser)->patch("/my-schedule/swaps/{$swap->id}/respond", ['decision' => 'accept'])->assertRedirect();

    $hrInbox = inboxOf($hr);

    expect($hrInbox[0]['title'])->toBe('Tukar jadwal menunggu HR')
        ->and($hrInbox[0]['message'])->toContain('Budi Staf')
        ->and($hrInbox[0]['message'])->toContain($date->translatedFormat('D, d M Y'))
        ->and($hrInbox[0]['message'])->toContain('menunggu keputusan HR');

    // HR menyetujui → kedua pihak tahu jadwalnya berubah.
    $employeeUser->notifications()->delete();
    $partnerUser->notifications()->delete();

    $this->actingAs($hr)->patch("/attendance/swaps/{$swap->id}/approve")->assertRedirect();

    expect(inboxOf($employeeUser)[0]['message'])->toContain('disetujui oleh HR. Jadwal Anda sudah diperbarui.')
        ->and(inboxOf($partnerUser)[0]['message'])->toContain('disetujui oleh HR. Jadwal Anda sudah diperbarui.');
});

test('cancelling a swap tells the partner it is off', function () {
    ['employee' => $employee, 'employeeUser' => $employeeUser, 'supervisor' => $partner, 'supervisorUser' => $partnerUser] = approvalStyleFixture();

    $shift = Shift::query()->create(['code' => 'REG', 'name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00', 'is_active' => true]);
    $date = now()->addDays(2);

    EmployeeSchedule::query()->create([
        'employee_id' => $employee->id, 'work_date' => $date->toDateString(),
        'shift_id' => $shift->id, 'is_day_off' => false, 'source' => 'generated',
    ]);
    EmployeeSchedule::query()->create([
        'employee_id' => $partner->id, 'work_date' => $date->toDateString(),
        'shift_id' => null, 'is_day_off' => true, 'source' => 'generated',
    ]);

    $this->actingAs($employeeUser)->post('/my-schedule/swaps', [
        'partner_id' => $partner->id,
        'requester_date' => $date->toDateString(),
        'type' => ShiftSwapRequest::TYPE_COVER,
        'reason' => 'Berubah rencana.',
    ])->assertRedirect('/my-schedule');

    $swap = ShiftSwapRequest::query()->firstOrFail();
    $partnerUser->notifications()->delete();

    $this->actingAs($employeeUser)->delete("/my-schedule/swaps/{$swap->id}")->assertRedirect('/my-schedule');

    $inbox = inboxOf($partnerUser);

    expect($inbox)->toHaveCount(1)
        ->and($inbox[0]['title'])->toBe('Permintaan tukar jadwal dibatalkan')
        ->and($inbox[0]['message'])->toContain('Budi Staf membatalkan permintaan');
});
