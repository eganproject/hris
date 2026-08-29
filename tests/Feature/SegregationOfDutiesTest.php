<?php

use App\Enums\LeaveRequestStatus;
use App\Models\AttendanceCorrection;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OvertimeApproval;
use App\Models\Shift;
use App\Models\ShiftSwapRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/** An HR user who is ALSO an employee (so they can file their own requests). */
function hrEmployee(array $extra = []): array
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    foreach (['leave.view', 'leave.update', 'corrections.view', 'corrections.update', 'attendance.view.all', 'my-leave.view', ...$extra] as $p) {
        Permission::findOrCreate($p, 'web');
    }

    $user = User::factory()->create();
    $user->givePermissionTo(['leave.view', 'leave.update', 'corrections.view', 'corrections.update', 'attendance.view.all', ...$extra]);
    // Absensi Harian & Jadwal Kerja dipersempit ke bawahan; pengguna ini
    // mewakili HR/administrator yang dikecualikan lewat Kontrol Akses.
    $user->forceFill(['bypass_team_scope' => true])->save();
    $employee = Employee::query()->create(['user_id' => $user->id, 'full_name' => 'HR Sendiri', 'employment_status' => 'active']);

    return [$user, $employee];
}

test('HR cannot approve or reject their own leave request', function () {
    [$user, $employee] = hrEmployee();
    $type = LeaveType::query()->create(['code' => 'IZ', 'name' => 'Izin', 'attendance_status' => 'leave', 'is_paid' => true, 'is_active' => true]);

    // Diajukan atas nama dirinya sendiri, langsung ke tahap HR (tanpa atasan).
    $leave = LeaveRequest::query()->create([
        'employee_id' => $employee->id, 'leave_type_id' => $type->id,
        'start_date' => now()->addDay()->toDateString(), 'end_date' => now()->addDay()->toDateString(),
        'status' => LeaveRequestStatus::PendingHr->value,
    ]);

    $this->actingAs($user)->patch("/attendance/leave/{$leave->id}/approve")->assertForbidden();
    $this->actingAs($user)->patch("/attendance/leave/{$leave->id}/reject")->assertForbidden();
    expect($leave->fresh()->status)->toBe(LeaveRequestStatus::PendingHr);
});

test('another HR can approve it — the rule only blocks self-decision', function () {
    [, $employee] = hrEmployee();
    [$otherHr] = hrEmployee();
    $type = LeaveType::query()->create(['code' => 'IZ', 'name' => 'Izin', 'attendance_status' => 'leave', 'is_paid' => true, 'is_active' => true]);

    $leave = LeaveRequest::query()->create([
        'employee_id' => $employee->id, 'leave_type_id' => $type->id,
        'start_date' => now()->addDay()->toDateString(), 'end_date' => now()->addDay()->toDateString(),
        'status' => LeaveRequestStatus::PendingHr->value,
    ]);

    $this->actingAs($otherHr)->patch("/attendance/leave/{$leave->id}/approve")->assertRedirect();
    expect($leave->fresh()->status)->toBe(LeaveRequestStatus::Approved);
});

test('HR cannot decide their own attendance correction', function () {
    [$user, $employee] = hrEmployee();

    $correction = AttendanceCorrection::query()->create([
        'employee_id' => $employee->id, 'work_date' => now()->subDay()->toDateString(),
        'requested_clock_in' => '08:00', 'reason' => 'Lupa absen.', 'status' => AttendanceCorrection::STATUS_PENDING,
    ]);

    $this->actingAs($user)->patch("/attendance/corrections/{$correction->id}/approve")->assertForbidden();
    $this->actingAs($user)->patch("/attendance/corrections/{$correction->id}/reject")->assertForbidden();
    expect($correction->fresh()->status)->toBe(AttendanceCorrection::STATUS_PENDING);
});

test('a self-managed employee cannot approve their own overtime', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('my-overtime.view', 'web');

    $user = User::factory()->create();
    $user->givePermissionTo('my-overtime.view');
    // Absensi Harian & Jadwal Kerja dipersempit ke bawahan; pengguna ini
    // mewakili HR/administrator yang dikecualikan lewat Kontrol Akses.
    $user->forceFill(['bypass_team_scope' => true])->save();
    $employee = Employee::query()->create(['user_id' => $user->id, 'full_name' => 'Mandiri', 'employment_status' => 'active']);
    // manager_id menunjuk dirinya sendiri (mis. dari impor) — tetap tidak boleh.
    $employee->update(['manager_id' => $employee->id]);

    $overtime = OvertimeApproval::query()->create([
        'employee_id' => $employee->id, 'supervisor_id' => $employee->id,
        'work_date' => now()->subDay()->toDateString(), 'start_time' => '17:00', 'end_time' => '19:00',
        'requested_minutes' => 120, 'reason' => 'Lembur.', 'requested_at' => now(),
        'computed_minutes' => 0, 'approved_minutes' => 0, 'status' => OvertimeApproval::STATUS_PENDING,
    ]);

    $this->actingAs($user)->patch("/my-overtime/{$overtime->id}/approve")->assertForbidden();
    expect($overtime->fresh()->status)->toBe(OvertimeApproval::STATUS_PENDING);
});

test('HR cannot file a leave request for an inactive employee', function () {
    [$user] = hrEmployee(['leave.create']);
    $type = LeaveType::query()->create(['code' => 'IZ', 'name' => 'Izin', 'attendance_status' => 'leave', 'is_paid' => true, 'is_active' => true]);
    $inactive = Employee::query()->create(['full_name' => 'Sudah Keluar', 'employment_status' => 'inactive']);

    $this->actingAs($user)
        ->from('/attendance/leave/create')
        ->post('/attendance/leave', [
            'employee_id' => $inactive->id, 'leave_type_id' => $type->id,
            'start_date' => now()->addDay()->toDateString(), 'end_date' => now()->addDay()->toDateString(),
        ])
        ->assertRedirect('/attendance/leave/create')
        ->assertSessionHasErrors('employee_id');

    expect(LeaveRequest::query()->count())->toBe(0);
});

test('HR cannot backdate a leave earlier than the start of last month', function () {
    [$user] = hrEmployee(['leave.create']);
    $type = LeaveType::query()->create(['code' => 'IZ', 'name' => 'Izin', 'attendance_status' => 'leave', 'is_paid' => true, 'is_active' => true]);
    $employee = Employee::query()->create(['full_name' => 'Karyawan', 'employment_status' => 'active']);

    // Sehari sebelum awal bulan lalu — di luar batas yang diizinkan.
    $tooEarly = now()->subMonthNoOverflow()->startOfMonth()->subDay();

    $this->actingAs($user)
        ->from('/attendance/leave/create')
        ->post('/attendance/leave', [
            'employee_id' => $employee->id, 'leave_type_id' => $type->id,
            'start_date' => $tooEarly->toDateString(), 'end_date' => $tooEarly->toDateString(),
        ])
        ->assertRedirect('/attendance/leave/create')
        ->assertSessionHasErrors('start_date');

    expect(LeaveRequest::query()->count())->toBe(0);

    // Tepat di awal bulan lalu — masih boleh.
    $earliest = now()->subMonthNoOverflow()->startOfMonth();

    $this->actingAs($user)->post('/attendance/leave', [
        'employee_id' => $employee->id, 'leave_type_id' => $type->id,
        'start_date' => $earliest->toDateString(), 'end_date' => $earliest->toDateString(),
    ])->assertRedirect('/attendance/leave');

    expect(LeaveRequest::query()->count())->toBe(1);
});

/**
 * Tukar jadwal melibatkan DUA pihak, jadi aturannya lebih luas daripada cuti/koreksi:
 * pemegang wewenang tidak boleh memutuskan permintaan yang ia sendiri jadi pengaju
 * MAUPUN yang ia jadi rekan tukarnya.
 */
function swapHrEmployee(string $name): array
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    foreach (['swaps.view', 'swaps.update', 'attendance.view.all'] as $p) {
        Permission::findOrCreate($p, 'web');
    }

    $user = User::factory()->create();
    $user->givePermissionTo(['swaps.view', 'swaps.update', 'attendance.view.all']);
    // Absensi Harian & Jadwal Kerja dipersempit ke bawahan; pengguna ini
    // mewakili HR/administrator yang dikecualikan lewat Kontrol Akses.
    $user->forceFill(['bypass_team_scope' => true])->save();
    $employee = Employee::query()->create(['user_id' => $user->id, 'full_name' => $name, 'employment_status' => 'active']);

    return [$user, $employee];
}

test('HR cannot decide a shift swap they filed themselves', function () {
    [$user, $me] = swapHrEmployee('HR Pengaju');
    [, $partner] = swapHrEmployee('Rekan');

    $swap = ShiftSwapRequest::query()->create([
        'requester_id' => $me->id, 'requester_date' => now()->addDays(3)->toDateString(),
        'partner_id' => $partner->id, 'partner_date' => now()->addDays(3)->toDateString(),
        'type' => 'swap', 'status' => ShiftSwapRequest::STATUS_PENDING_HR,
    ]);

    $this->actingAs($user)->patch(route('attendance.swaps.approve', $swap))->assertForbidden();
    $this->actingAs($user)->patch(route('attendance.swaps.reject', $swap))->assertForbidden();
    expect($swap->fresh()->status)->toBe(ShiftSwapRequest::STATUS_PENDING_HR);
});

test('HR cannot decide a shift swap where they are the partner', function () {
    [, $requester] = swapHrEmployee('Pengaju');
    [$user, $me] = swapHrEmployee('HR Rekan');

    $swap = ShiftSwapRequest::query()->create([
        'requester_id' => $requester->id, 'requester_date' => now()->addDays(3)->toDateString(),
        'partner_id' => $me->id, 'partner_date' => now()->addDays(3)->toDateString(),
        'type' => 'swap', 'status' => ShiftSwapRequest::STATUS_PENDING_HR,
    ]);

    $this->actingAs($user)->patch(route('attendance.swaps.approve', $swap))->assertForbidden();
    $this->actingAs($user)->patch(route('attendance.swaps.reject', $swap))->assertForbidden();
    expect($swap->fresh()->status)->toBe(ShiftSwapRequest::STATUS_PENDING_HR);
});

test('bulk approve skips a swap the deciding HR is party to', function () {
    [$user, $me] = swapHrEmployee('HR Pengaju');
    [, $partner] = swapHrEmployee('Rekan');

    // Jadwal keduanya diisi sungguhan: tanpa ini permintaannya tertahan sebagai
    // "bentrok" dan tesnya lulus tanpa pernah menyentuh aturan pemisahan wewenang.
    $pagi = Shift::query()->create(['code' => 'PG', 'name' => 'Pagi', 'start_time' => '07:00', 'end_time' => '15:00', 'is_active' => true]);
    $siang = Shift::query()->create(['code' => 'SG', 'name' => 'Siang', 'start_time' => '15:00', 'end_time' => '23:00', 'is_active' => true]);
    $date = now()->addDays(3)->toDateString();

    foreach ([[$me, $pagi], [$partner, $siang]] as [$employee, $shift]) {
        EmployeeSchedule::query()->create([
            'employee_id' => $employee->id, 'work_date' => $date,
            'shift_id' => $shift->id, 'is_day_off' => false, 'source' => 'generated',
        ]);
    }

    $mine = ShiftSwapRequest::query()->create([
        'requester_id' => $me->id, 'requester_date' => $date,
        'partner_id' => $partner->id, 'partner_date' => $date,
        'type' => 'swap', 'status' => ShiftSwapRequest::STATUS_PENDING_HR,
    ]);

    $this->actingAs($user)->post(route('attendance.swaps.bulk-approve'), ['ids' => [$mine->id]])->assertRedirect();

    // Dilewati, bukan diterapkan: status tetap menunggu DAN jadwal tidak tertukar.
    expect($mine->fresh()->status)->toBe(ShiftSwapRequest::STATUS_PENDING_HR)
        ->and(EmployeeSchedule::query()->where('employee_id', $me->id)->where('work_date', $date)->value('shift_id'))->toBe($pagi->id)
        ->and(EmployeeSchedule::query()->where('employee_id', $partner->id)->where('work_date', $date)->value('shift_id'))->toBe($siang->id);
});

test('an uninvolved HR can still decide the swap', function () {
    [, $requester] = swapHrEmployee('Pengaju');
    [, $partner] = swapHrEmployee('Rekan');
    [$otherHr] = swapHrEmployee('HR Netral');

    $swap = ShiftSwapRequest::query()->create([
        'requester_id' => $requester->id, 'requester_date' => now()->addDays(3)->toDateString(),
        'partner_id' => $partner->id, 'partner_date' => now()->addDays(3)->toDateString(),
        'type' => 'swap', 'status' => ShiftSwapRequest::STATUS_PENDING_HR,
    ]);

    $this->actingAs($otherHr)->patch(route('attendance.swaps.reject', $swap))->assertRedirect();
    expect($swap->fresh()->status)->toBe(ShiftSwapRequest::STATUS_REJECTED);
});
