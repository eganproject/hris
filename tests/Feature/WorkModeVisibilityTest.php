<?php

use App\Enums\LeaveRequestStatus;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Shift;
use App\Models\User;
use App\Support\WorkMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function workModeWfhType(): LeaveType
{
    return LeaveType::query()->firstOrCreate(['code' => 'WFH'], [
        'name' => 'Work From Home', 'attendance_status' => 'wfh',
        'is_paid' => true, 'counts_against_balance' => false, 'is_active' => true,
    ]);
}

function workModeShift(): Shift
{
    return Shift::query()->firstOrCreate(['code' => 'REG'], [
        'name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00', 'is_active' => true,
    ]);
}

/** Karyawan reguler dengan akun login dan jadwal pada satu tanggal. */
function workModeEmployee(string $date, bool $isWfh = false): array
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user = User::factory()->create();
    $employee = Employee::query()->create([
        'user_id' => $user->id, 'full_name' => 'Rina Reguler', 'employment_status' => 'active',
    ]);

    EmployeeSchedule::query()->create([
        'employee_id' => $employee->id, 'work_date' => $date,
        'shift_id' => workModeShift()->id, 'is_day_off' => false, 'is_wfh' => $isWfh,
        'source' => 'generated',
    ]);

    return [$user, $employee];
}

function workModeApproveWfh(Employee $employee, string $date): LeaveRequest
{
    return LeaveRequest::query()->create([
        'employee_id' => $employee->id, 'leave_type_id' => workModeWfhType()->id,
        'start_date' => $date, 'end_date' => $date, 'reason' => 'WFH mendadak.',
        'status' => LeaveRequestStatus::Approved->value,
    ]);
}

test('WFH counts as working whether it comes from the roster or from a request', function () {
    $date = '2026-06-10';

    // Jalur A: ditandai di roster.
    [, $rosterEmployee] = workModeEmployee($date, isWfh: true);
    $fromRoster = WorkMode::for($rosterEmployee->schedules()->first());

    // Jalur B: pengajuan WFH yang disetujui, roster tetap reguler.
    [, $requestEmployee] = workModeEmployee($date);
    $fromRequest = WorkMode::for($requestEmployee->schedules()->first(), workModeApproveWfh($requestEmployee, $date)->load('leaveType'));

    // Dua jalur berbeda, satu hasil yang sama — inilah yang dulu tidak konsisten.
    expect($fromRoster->key)->toBe('wfh')
        ->and($fromRequest->key)->toBe('wfh')
        ->and($fromRoster->isWorking)->toBeTrue()
        ->and($fromRequest->isWorking)->toBeTrue()
        ->and($fromRequest->chipClasses())->toBe($fromRoster->chipClasses());
});

test('ordinary leave is still treated as not working', function () {
    $date = '2026-06-10';
    [, $employee] = workModeEmployee($date);

    $sick = LeaveType::query()->create([
        'code' => 'SK', 'name' => 'Sakit', 'attendance_status' => 'sick',
        'is_paid' => true, 'counts_against_balance' => false, 'is_active' => true,
    ]);
    $leave = LeaveRequest::query()->create([
        'employee_id' => $employee->id, 'leave_type_id' => $sick->id,
        'start_date' => $date, 'end_date' => $date, 'status' => LeaveRequestStatus::Approved->value,
    ]);

    $mode = WorkMode::for($employee->schedules()->first(), $leave->load('leaveType'));

    expect($mode->key)->toBe('leave')
        ->and($mode->isWorking)->toBeFalse()
        ->and($mode->isRemote())->toBeFalse();
});

test('the day-off and unscheduled cases stay distinct', function () {
    expect(WorkMode::for(null)->key)->toBe('none')
        ->and(WorkMode::for(new EmployeeSchedule(['is_day_off' => true]))->key)->toBe('off');
});

test('an employee sees WFH on their own roster page, from either source', function () {
    $date = now()->addDay()->toDateString();

    [$user, $employee] = workModeEmployee($date, isWfh: true);

    $this->actingAs($user)->get(route('my-roster.index', ['month' => now()->format('Y-m')]))
        ->assertOk()
        ->assertSee('WFH');

    // Jalur pengajuan: roster reguler, WFH datang dari cuti yang disetujui.
    [$user2, $employee2] = workModeEmployee($date);
    workModeApproveWfh($employee2, $date);

    $this->actingAs($user2)->get(route('my-roster.index', ['month' => now()->format('Y-m')]))
        ->assertOk()
        ->assertSee('WFH');
});

test('the schedule grid no longer paints an approved WFH day as an absence', function () {
    $date = now()->toDateString();
    [, $employee] = workModeEmployee($date);
    workModeApproveWfh($employee, $date);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    foreach (['schedules.view', User::SCOPE_BYPASS_ATTENDANCE] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    $hr = User::factory()->create();
    $hr->givePermissionTo(['schedules.view', User::SCOPE_BYPASS_ATTENDANCE]);

    $content = $this->actingAs($hr)
        ->get(route('attendance.schedules.index', ['month' => now()->format('Y-m')]))
        ->assertOk()
        ->getContent();

    // Sel-nya biru (kerja jarak jauh), bukan kuning (tidak masuk).
    expect($content)->toContain('bg-indigo-100 text-indigo-700');
});

test('the daily board flags a remote day before attendance is even processed', function () {
    $date = now()->toDateString();
    [, $employee] = workModeEmployee($date);
    workModeApproveWfh($employee, $date);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    foreach (['attendance-daily.view', User::SCOPE_BYPASS_ATTENDANCE] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    $hr = User::factory()->create();
    $hr->givePermissionTo(['attendance-daily.view', User::SCOPE_BYPASS_ATTENDANCE]);

    // Belum ada baris absensi sama sekali: kolom Status masih kosong, tapi kolom
    // Jadwal sudah harus memberi tahu bahwa hari itu WFH.
    $this->actingAs($hr)->get("/attendance/daily?date={$date}")
        ->assertOk()
        ->assertSee('WFH');
});
