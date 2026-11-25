<?php

use App\Enums\LeaveRequestStatus;
use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OvertimeApproval;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/MobileHelpers.php';

uses(RefreshDatabase::class);

function annualLeaveType(): LeaveType
{
    return LeaveType::query()->create([
        'code' => 'CT', 'name' => 'Cuti Tahunan', 'attendance_status' => 'leave',
        'is_paid' => true, 'counts_against_balance' => true, 'default_quota_days' => 12, 'is_active' => true,
    ]);
}

test('karyawan mengajukan cuti dari HP dan masuk ke antrean atasan', function () {
    [, $boss] = mobileEmployee('Andi Atasan');
    [$user] = mobileEmployee('Budi', ['manager_id' => $boss->id]);
    $type = annualLeaveType();

    $start = now()->addDays(10)->toDateString();
    $end = now()->addDays(12)->toDateString();

    mobileAs($user)->postJson('/api/mobile/v1/leave', [
        'leave_type_id' => $type->id, 'start_date' => $start, 'end_date' => $end, 'reason' => 'Acara keluarga',
    ])->assertCreated()
        ->assertJsonPath('data.status', LeaveRequestStatus::PendingSupervisor->value)
        ->assertJsonPath('data.days', 3)
        ->assertJsonPath('data.category', 'cuti');

    expect(LeaveRequest::query()->first()->supervisor_id)->toBe($boss->id);
    expect($boss->user->notifications()->count())->toBe(1);
});

test('aturan saldo cuti web juga berlaku di HP', function () {
    [$user] = mobileEmployee('Budi');
    $type = annualLeaveType();

    mobileAs($user)->postJson('/api/mobile/v1/leave', [
        'leave_type_id' => $type->id,
        'start_date' => now()->addDays(1)->toDateString(),
        'end_date' => now()->addDays(20)->toDateString(),
    ])->assertUnprocessable()->assertJsonValidationErrors('leave_type_id');
});

test('ringkasan pengajuan memuat saldo dan pengajuan terbaru semua jenis', function () {
    [, $boss] = mobileEmployee('Andi Atasan');
    [$user, $me] = mobileEmployee('Budi', ['manager_id' => $boss->id]);
    $type = annualLeaveType();

    LeaveRequest::query()->create([
        'employee_id' => $me->id, 'leave_type_id' => $type->id, 'supervisor_id' => $boss->id,
        'start_date' => now()->addDays(3), 'end_date' => now()->addDays(3), 'status' => LeaveRequestStatus::PendingSupervisor,
    ]);
    OvertimeApproval::query()->create([
        'employee_id' => $me->id, 'supervisor_id' => $boss->id, 'work_date' => now()->subDay(),
        'start_time' => '17:00', 'end_time' => '19:30', 'requested_minutes' => 150, 'status' => 'pending',
    ]);

    $response = mobileAs($user)->getJson('/api/mobile/v1/pengajuan/ringkasan')->assertOk();

    expect($response->json('data.balances.0.quota'))->toBe(12)
        ->and($response->json('data.balances.0.pending_days'))->toBe(1)
        ->and(collect($response->json('data.recent'))->pluck('kind')->sort()->values()->all())->toBe(['leave', 'overtime']);

    mobileAs($user)->getJson('/api/mobile/v1/pengajuan?type=lembur')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Lembur · 2,5 jam');
});

test('lembur dari HP memakai aturan yang sama dengan web', function () {
    [, $boss] = mobileEmployee('Andi Atasan');
    [$user, $me] = mobileEmployee('Budi', ['manager_id' => $boss->id]);
    $date = now()->subDay()->toDateString();
    Attendance::query()->create(['employee_id' => $me->id, 'work_date' => $date, 'status' => 'present', 'overtime_minutes' => 60]);

    mobileAs($user)->postJson('/api/mobile/v1/overtime', [
        'work_date' => $date, 'start_time' => '17:00', 'end_time' => '19:00', 'reason' => 'Tutup buku',
    ])->assertCreated()->assertJsonPath('data.requested_minutes', 120);

    // Tanggal yang sama tidak boleh diajukan dua kali.
    mobileAs($user)->postJson('/api/mobile/v1/overtime', [
        'work_date' => $date, 'start_time' => '17:00', 'end_time' => '18:00', 'reason' => 'Lagi',
    ])->assertUnprocessable()->assertJsonValidationErrors('work_date');

    expect(OvertimeApproval::query()->first()->computed_minutes)->toBe(60);
});

test('lembur ditolak bila belum punya atasan', function () {
    [$user] = mobileEmployee('Budi');

    mobileAs($user)->postJson('/api/mobile/v1/overtime', [
        'work_date' => now()->subDay()->toDateString(), 'start_time' => '17:00', 'end_time' => '19:00', 'reason' => 'x',
    ])->assertUnprocessable()->assertJsonValidationErrors('work_date');
});

test('karyawan hanya bisa membatalkan pengajuan miliknya yang masih menunggu', function () {
    [, $boss] = mobileEmployee('Andi Atasan');
    [$user, $me] = mobileEmployee('Budi', ['manager_id' => $boss->id]);
    [$other, $otherEmployee] = mobileEmployee('Cici', ['manager_id' => $boss->id]);
    $type = annualLeaveType();

    $leave = LeaveRequest::query()->create([
        'employee_id' => $me->id, 'leave_type_id' => $type->id, 'supervisor_id' => $boss->id,
        'start_date' => now()->addDays(3), 'end_date' => now()->addDays(3), 'status' => LeaveRequestStatus::PendingSupervisor,
    ]);

    mobileAs($other)->postJson("/api/mobile/v1/leave/{$leave->id}/cancel")->assertForbidden();
    mobileAs($user)->postJson("/api/mobile/v1/leave/{$leave->id}/cancel")->assertOk();

    expect($leave->fresh()->status)->toBe(LeaveRequestStatus::Cancelled);
});
