<?php

use App\Enums\AttendanceStatus;
use App\Enums\LeaveRequestStatus;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function attendanceManager(): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $permissions = ['dashboard.view', ...attendanceMenuPermissions(), 'attendance.view.all'];

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

test('app runs in the Jakarta timezone', function () {
    expect(config('app.timezone'))->toBe('Asia/Jakarta');
});

test('all attendance foundation pages render without errors', function () {
    $user = attendanceManager();

    foreach ([
        '/attendance/shifts',
        '/attendance/shifts/create',
        '/attendance/holidays',
        '/attendance/holidays/create',
        '/attendance/leave',
        '/attendance/leave/create',
    ] as $url) {
        $this->actingAs($user)->get($url)->assertOk();
    }
});

test('a shift resolves an overnight window and computes work minutes', function () {
    $shift = Shift::query()->create([
        'code' => 'NGT',
        'name' => 'Shift Malam',
        'start_time' => '22:00',
        'end_time' => '06:00',
        'crosses_midnight' => true,
        'break_minutes' => 60,
        'is_active' => true,
    ]);

    $window = $shift->windowFor(Carbon::parse('2026-01-10'));

    expect($window['start']->format('Y-m-d H:i'))->toBe('2026-01-10 22:00')
        ->and($window['end']->format('Y-m-d H:i'))->toBe('2026-01-11 06:00')
        ->and($shift->gross_minutes)->toBe(480)
        ->and($shift->work_minutes)->toBe(420);
});

test('overtime respects the "starts after" grace and the minimum threshold', function () {
    $shift = Shift::query()->create([
        'code' => 'REG',
        'name' => 'Reguler',
        'start_time' => '08:00',
        'end_time' => '17:00',
        'crosses_midnight' => false,
        'break_minutes' => 60,
        'overtime_starts_after_minutes' => 30,
        'overtime_min_minutes' => 30,
        'is_active' => true,
    ]);

    $date = Carbon::parse('2026-01-10');
    $out = fn (string $time) => Carbon::parse("2026-01-10 {$time}");

    expect($shift->overtimeMinutesFor($out('16:50'), $date))->toBe(0)   // pulang sebelum jam kerja selesai
        ->and($shift->overtimeMinutesFor($out('17:20'), $date))->toBe(0) // 20m lewat, di bawah grace 30m
        ->and($shift->overtimeMinutesFor($out('17:45'), $date))->toBe(0) // 45-30=15m, di bawah minimal 30m
        ->and($shift->overtimeMinutesFor($out('18:30'), $date))->toBe(60); // 90-30=60m
});

test('storing a shift auto-detects the overnight flag from the times', function () {
    $user = attendanceManager();

    $this->actingAs($user)->post('/attendance/shifts', [
        'code' => 'MLM',
        'name' => 'Malam',
        'start_time' => '23:00',
        'end_time' => '07:00',
        'break_minutes' => 60,
        'late_tolerance_minutes' => 10,
        'early_leave_tolerance_minutes' => 5,
        'is_active' => '1',
    ])->assertRedirect('/attendance/shifts');

    expect(Shift::query()->where('code', 'MLM')->value('crosses_midnight'))->toEqual(true);
});

test('a national holiday can be created and applies to a branch', function () {
    $user = attendanceManager();

    $this->actingAs($user)->post('/attendance/holidays', [
        'date' => '2026-08-17',
        'name' => 'Hari Kemerdekaan RI',
        'is_national' => '1',
    ])->assertRedirect('/attendance/holidays');

    $holiday = Holiday::query()->firstWhere('name', 'Hari Kemerdekaan RI');

    expect($holiday->is_national)->toBeTrue()
        ->and($holiday->branch_id)->toBeNull()
        ->and(Holiday::query()->appliesTo(123)->whereDate('date', '2026-08-17')->exists())->toBeTrue();
});

test('an admin leave request for an employee with a manager needs two approvals', function () {
    $user = attendanceManager();
    $manager = Employee::query()->create(['full_name' => 'Bos', 'employment_status' => 'active']);
    $employee = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active', 'manager_id' => $manager->id]);
    $type = LeaveType::query()->create(['code' => 'IZ', 'name' => 'Izin', 'attendance_status' => 'leave', 'is_paid' => true, 'is_active' => true]);

    $this->actingAs($user)->post('/attendance/leave', [
        'employee_id' => $employee->id,
        'leave_type_id' => $type->id,
        'start_date' => '2026-02-10',
        'end_date' => '2026-02-12',
    ])->assertRedirect('/attendance/leave');

    $leave = LeaveRequest::query()->firstOrFail();

    expect($leave->status)->toBe(LeaveRequestStatus::PendingSupervisor)
        ->and($leave->supervisor_id)->toBe($manager->id)
        ->and($leave->days)->toBe(3)
        ->and($leave->leaveType->attendance_status)->toBe(AttendanceStatus::Leave);

    // First approval advances to HR step.
    $this->actingAs($user)->patch("/attendance/leave/{$leave->id}/approve")->assertRedirect('/attendance/leave');
    expect($leave->refresh()->status)->toBe(LeaveRequestStatus::PendingHr);

    // Second approval finalises.
    $this->actingAs($user)->patch("/attendance/leave/{$leave->id}/approve")->assertRedirect('/attendance/leave');
    $leave->refresh();

    expect($leave->status)->toBe(LeaveRequestStatus::Approved)
        ->and($leave->approved_by)->toBe($user->id)
        ->and(LeaveRequest::query()->approvedOn('2026-02-11')->exists())->toBeTrue();
});

test('an approved leave can only be cancelled, never deleted or decided again', function () {
    $user = attendanceManager();
    $employee = Employee::query()->create(['full_name' => 'Sudah Disetujui', 'employment_status' => 'active']);
    $type = LeaveType::query()->create(['code' => 'IZ', 'name' => 'Izin', 'attendance_status' => 'leave', 'is_paid' => true, 'is_active' => true]);

    $this->actingAs($user)->post('/attendance/leave', [
        'employee_id' => $employee->id,
        'leave_type_id' => $type->id,
        'start_date' => '2026-02-10',
        'end_date' => '2026-02-11',
    ])->assertRedirect('/attendance/leave');

    $leave = LeaveRequest::query()->firstOrFail();

    // No manager, so a single approval finalises it.
    $this->actingAs($user)->patch("/attendance/leave/{$leave->id}/approve")->assertRedirect('/attendance/leave');
    expect($leave->refresh()->status)->toBe(LeaveRequestStatus::Approved);

    // Deciding it again (two HR users with the list open) must not go through.
    $this->actingAs($user)->patch("/attendance/leave/{$leave->id}/approve")->assertForbidden();
    $this->actingAs($user)->patch("/attendance/leave/{$leave->id}/reject")->assertForbidden();

    // Deleting an approved leave would erase the approval trail.
    $this->actingAs($user)->delete("/attendance/leave/{$leave->id}")->assertForbidden();
    expect(LeaveRequest::query()->whereKey($leave->id)->exists())->toBeTrue();

    // The list must not even offer "Hapus" for it.
    $this->actingAs($user)->get('/attendance/leave')
        ->assertOk()
        ->assertSee('Batalkan')
        ->assertDontSee('data-delete-leave="'.$leave->id.'"', escape: false);

    // Cancelling keeps the record (and who approved it) but takes it out of effect.
    $this->actingAs($user)->patch("/attendance/leave/{$leave->id}/cancel")->assertRedirect('/attendance/leave');
    $leave->refresh();

    expect($leave->status)->toBe(LeaveRequestStatus::Cancelled)
        ->and($leave->approved_by)->toBe($user->id)
        ->and(LeaveRequest::query()->approvedOn('2026-02-10')->exists())->toBeFalse();

    // Once cancelled it is no longer in effect, so it may be deleted.
    $this->actingAs($user)->delete("/attendance/leave/{$leave->id}")->assertRedirect('/attendance/leave');
    expect(LeaveRequest::query()->whereKey($leave->id)->exists())->toBeFalse();
});

test('a request for an employee without a manager starts at the HR step', function () {
    $user = attendanceManager();
    $employee = Employee::query()->create(['full_name' => 'Sendiri', 'employment_status' => 'active']);
    $type = LeaveType::query()->create(['code' => 'IZ', 'name' => 'Izin', 'attendance_status' => 'leave', 'is_paid' => true, 'is_active' => true]);

    $this->actingAs($user)->post('/attendance/leave', [
        'employee_id' => $employee->id,
        'leave_type_id' => $type->id,
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-01',
    ])->assertRedirect('/attendance/leave');

    expect(LeaveRequest::query()->firstOrFail()->status)->toBe(LeaveRequestStatus::PendingHr);
});
