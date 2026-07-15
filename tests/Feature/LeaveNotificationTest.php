<?php

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Karyawan + atasan (keduanya punya akun login) + satu HR.
 *
 * @return array{employee: Employee, employeeUser: User, supervisor: Employee, supervisorUser: User, hr: User, type: LeaveType}
 */
function leaveNotificationFixture(string $typeName = 'Sakit'): array
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach (['my-leave.view', 'leave.view', 'leave.create', 'leave.update', 'attendance.view.all'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $supervisorUser = User::factory()->create();
    $supervisorUser->givePermissionTo('my-leave.view');
    $supervisor = Employee::query()->create([
        'user_id' => $supervisorUser->id, 'full_name' => 'Sari Atasan', 'employment_status' => 'active',
    ]);

    $employeeUser = User::factory()->create();
    $employeeUser->givePermissionTo('my-leave.view');
    $employee = Employee::query()->create([
        'user_id' => $employeeUser->id, 'full_name' => 'Budi Staf', 'employment_status' => 'active',
        'manager_id' => $supervisor->id,
    ]);

    $hr = User::factory()->create();
    $hr->givePermissionTo(['leave.view', 'leave.create', 'leave.update', 'attendance.view.all']);

    $type = LeaveType::query()->create([
        'code' => 'SK', 'name' => $typeName, 'attendance_status' => 'sick',
        'is_paid' => true, 'counts_against_balance' => false, 'is_active' => true,
    ]);

    return compact('employee', 'employeeUser', 'supervisor', 'supervisorUser', 'hr', 'type');
}

/** @return array<int, array{title: string, message: string}> */
function notificationsOf(User $user): array
{
    return $user->notifications()->get()
        ->map(fn ($n) => ['title' => $n->data['title'], 'message' => $n->data['message']])
        ->all();
}

test('the whole leave flow tells each side what they need to know', function () {
    ['employeeUser' => $employeeUser, 'supervisorUser' => $supervisorUser, 'hr' => $hr, 'type' => $type] = leaveNotificationFixture('Sakit');

    // 1. Karyawan mengajukan → atasan diberi tahu: siapa, jenis, periode, alasan.
    $start = now()->addDays(3);
    $end = now()->addDays(5);

    $this->actingAs($employeeUser)->post('/my-leave', [
        'leave_type_id' => $type->id,
        'start_date' => $start->toDateString(),
        'end_date' => $end->toDateString(),
        'reason' => 'Demam tinggi.',
    ])->assertRedirect();

    $period = LeaveRequest::query()->firstOrFail()->days.' hari';

    $leave = LeaveRequest::query()->firstOrFail();
    $supervisorInbox = notificationsOf($supervisorUser);

    expect($supervisorInbox)->toHaveCount(1)
        ->and($supervisorInbox[0]['title'])->toBe('Pengajuan Sakit baru')
        ->and($supervisorInbox[0]['message'])->toContain('Budi Staf')
        ->and($supervisorInbox[0]['message'])->toContain('('.$period.')')
        ->and($supervisorInbox[0]['message'])->toContain('Demam tinggi.');

    // 2. Atasan setuju → HR diberi tahu, lengkap dengan jenis & periodenya.
    $this->actingAs($supervisorUser)->patch("/my-leave/{$leave->id}/approve")->assertRedirect();

    $hrInbox = notificationsOf($hr);

    expect($hrInbox)->toHaveCount(1)
        ->and($hrInbox[0]['title'])->toBe('Sakit menunggu persetujuan HR')
        ->and($hrInbox[0]['message'])->toContain('Budi Staf')
        ->and($hrInbox[0]['message'])->toContain('('.$period.')')
        ->and($hrInbox[0]['message'])->toContain('disetujui atasan');

    // 3. HR setuju → karyawan tahu siapa yang menyetujui.
    $this->actingAs($hr)->patch("/attendance/leave/{$leave->id}/approve")->assertRedirect();

    $employeeInbox = notificationsOf($employeeUser);

    expect($employeeInbox)->toHaveCount(1)
        ->and($employeeInbox[0]['title'])->toBe('Sakit disetujui')
        ->and($employeeInbox[0]['message'])->toContain('('.$period.')')
        ->and($employeeInbox[0]['message'])->toContain('disetujui oleh HR');
});

test('a rejection says at which step it was rejected, and why', function () {
    ['employeeUser' => $employeeUser, 'supervisorUser' => $supervisorUser, 'type' => $type] = leaveNotificationFixture('Izin');

    $date = now()->addDays(2);

    $this->actingAs($employeeUser)->post('/my-leave', [
        'leave_type_id' => $type->id,
        'start_date' => $date->toDateString(),
        'end_date' => $date->toDateString(),
        'reason' => 'Urusan keluarga.',
    ])->assertRedirect();

    $leave = LeaveRequest::query()->firstOrFail();

    $this->actingAs($supervisorUser)->patch("/my-leave/{$leave->id}/reject")->assertRedirect();

    $inbox = notificationsOf($employeeUser);

    expect($inbox)->toHaveCount(1)
        ->and($inbox[0]['title'])->toBe('Izin ditolak')
        ->and($inbox[0]['message'])->toContain($date->translatedFormat('d M Y').' (1 hari)')
        ->and($inbox[0]['message'])->toContain('ditolak oleh atasan');
});

test('an approved leave can no longer be cancelled by HR', function () {
    ['employeeUser' => $employeeUser, 'supervisorUser' => $supervisorUser, 'hr' => $hr, 'type' => $type] = leaveNotificationFixture('Cuti Tahunan');

    $this->actingAs($employeeUser)->post('/my-leave', [
        'leave_type_id' => $type->id,
        'start_date' => now()->addDays(7)->toDateString(),
        'end_date' => now()->addDays(9)->toDateString(),
        'reason' => 'Liburan keluarga.',
    ])->assertRedirect();

    $leave = LeaveRequest::query()->firstOrFail();

    $this->actingAs($supervisorUser)->patch("/my-leave/{$leave->id}/approve")->assertRedirect();
    $this->actingAs($hr)->patch("/attendance/leave/{$leave->id}/approve")->assertRedirect();

    // Rute pembatalan cuti sudah tidak ada — pengajuan yang disetujui bersifat final.
    $this->actingAs($hr)->patch("/attendance/leave/{$leave->id}/cancel")->assertNotFound();
    expect($leave->fresh()->status)->toBe(App\Enums\LeaveRequestStatus::Approved);
});

test('an employee cancelling their own request tells the supervisor waiting on it', function () {
    ['employeeUser' => $employeeUser, 'supervisorUser' => $supervisorUser, 'type' => $type] = leaveNotificationFixture('Izin');

    $this->actingAs($employeeUser)->post('/my-leave', [
        'leave_type_id' => $type->id,
        'start_date' => now()->addDays(4)->toDateString(),
        'end_date' => now()->addDays(4)->toDateString(),
        'reason' => 'Batal, urusannya selesai.',
    ])->assertRedirect();

    $leave = LeaveRequest::query()->firstOrFail();
    $supervisorUser->notifications()->delete();

    $this->actingAs($employeeUser)->patch("/my-leave/{$leave->id}/cancel")->assertRedirect();

    $supervisorInbox = notificationsOf($supervisorUser);

    expect($supervisorInbox)->toHaveCount(1)
        ->and($supervisorInbox[0]['title'])->toBe('Pengajuan Izin dibatalkan')
        ->and($supervisorInbox[0]['message'])->toContain('Budi Staf membatalkan')
        // Pelakunya sendiri tidak dinotifikasi.
        ->and(notificationsOf($employeeUser))->toHaveCount(0);
});

test('leave filed by HR on behalf of an employee notifies that employee', function () {
    ['employeeUser' => $employeeUser, 'employee' => $employee, 'hr' => $hr, 'type' => $type] = leaveNotificationFixture('Sakit');

    $this->actingAs($hr)->post('/attendance/leave', [
        'employee_id' => $employee->id,
        'leave_type_id' => $type->id,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-02',
    ])->assertRedirect();

    $inbox = notificationsOf($employeeUser);

    expect($inbox)->toHaveCount(1)
        ->and($inbox[0]['title'])->toBe('Pengajuan Sakit dibuat untuk Anda')
        ->and($inbox[0]['message'])->toContain('HR membuat pengajuan Sakit atas nama Anda')
        ->and($inbox[0]['message'])->toContain('01 – 02 Jun 2026 (2 hari)');
});
