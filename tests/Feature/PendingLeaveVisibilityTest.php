<?php

use App\Enums\LeaveRequestStatus;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Shift;
use App\Models\User;
use App\Support\LeaveCalendar;
use App\Support\WorkMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Karyawan yang baru mengajukan cuti harus melihat jejaknya di halaman jadwalnya.
 * Sebelumnya "Jadwal Saya" hanya memasang cuti yang SUDAH disetujui, sehingga hari
 * yang baru diajukan tampil seolah tidak terjadi apa-apa — mudah disalahartikan
 * sebagai pengajuan yang gagal terkirim.
 */
function pendingLeaveEmployee(string $date): array
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('my-schedule.view', 'web');

    $user = User::factory()->create();
    $user->givePermissionTo('my-schedule.view');

    $employee = Employee::query()->create([
        'user_id' => $user->id, 'full_name' => 'Rina', 'employment_status' => 'active',
    ]);

    $shift = Shift::query()->firstOrCreate(['code' => 'REG'], [
        'name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00', 'is_active' => true,
    ]);

    EmployeeSchedule::query()->create([
        'employee_id' => $employee->id, 'work_date' => $date,
        'shift_id' => $shift->id, 'is_day_off' => false, 'source' => 'generated',
    ]);

    return [$user, $employee];
}

function pendingLeaveType(): LeaveType
{
    return LeaveType::query()->firstOrCreate(['code' => 'CT'], [
        'name' => 'Cuti Tahunan', 'attendance_status' => 'leave',
        'is_paid' => true, 'counts_against_balance' => false, 'is_active' => true,
    ]);
}

function fileLeave(Employee $employee, string $date, LeaveRequestStatus $status): LeaveRequest
{
    return LeaveRequest::query()->create([
        'employee_id' => $employee->id, 'leave_type_id' => pendingLeaveType()->id,
        'start_date' => $date, 'end_date' => $date, 'reason' => 'Urusan keluarga.',
        'status' => $status->value,
    ]);
}

test('a pending leave is marked on the roster without changing the work day', function () {
    $date = now()->addDays(2)->toDateString();
    [$user, $employee] = pendingLeaveEmployee($date);
    fileLeave($employee, $date, LeaveRequestStatus::PendingHr);

    $response = $this->actingAs($user)->get(route('my-roster.index', ['month' => now()->format('Y-m')]));

    // Ditandai "Diajukan"…
    $response->assertOk()->assertSee('Diajukan');

    // …tapi harinya TETAP hari kerja: shift-nya masih tampil, belum jadi cuti.
    $calendar = LeaveCalendar::for($employee, now()->startOfMonth(), now()->endOfMonth());
    expect($calendar->approvedOn($date))->toBeNull()
        ->and($calendar->pendingOn($date))->not->toBeNull();

    expect(WorkMode::for(
        EmployeeSchedule::query()->where('employee_id', $employee->id)->where('work_date', $date)->first(),
        $calendar->approvedOn($date),
    )->isWorking)->toBeTrue();
});

test('an approved leave is not also labelled as merely submitted', function () {
    $date = now()->addDays(2)->toDateString();
    [, $employee] = pendingLeaveEmployee($date);
    fileLeave($employee, $date, LeaveRequestStatus::Approved);

    $calendar = LeaveCalendar::for($employee, now()->startOfMonth(), now()->endOfMonth());

    expect($calendar->approvedOn($date))->not->toBeNull()
        ->and($calendar->pendingOn($date))->toBeNull();
});

test('an approved leave wins over a pending one on the same day', function () {
    $date = now()->addDays(2)->toDateString();
    [, $employee] = pendingLeaveEmployee($date);
    $approved = fileLeave($employee, $date, LeaveRequestStatus::Approved);
    fileLeave($employee, $date, LeaveRequestStatus::PendingSupervisor);

    $calendar = LeaveCalendar::for($employee, now()->startOfMonth(), now()->endOfMonth());

    expect($calendar->approvedOn($date)?->id)->toBe($approved->id)
        ->and($calendar->pendingOn($date))->toBeNull();
});

test('decided requests never reach the calendar', function () {
    $date = now()->addDays(2)->toDateString();
    [, $employee] = pendingLeaveEmployee($date);
    fileLeave($employee, $date, LeaveRequestStatus::Rejected);
    fileLeave($employee, $date, LeaveRequestStatus::Cancelled);

    $calendar = LeaveCalendar::for($employee, now()->startOfMonth(), now()->endOfMonth());

    expect($calendar->approvedOn($date))->toBeNull()
        ->and($calendar->pendingOn($date))->toBeNull();
});

test('both self-service schedule pages describe the same day the same way', function () {
    $date = now()->addDays(2)->toDateString();
    [$user, $employee] = pendingLeaveEmployee($date);
    fileLeave($employee, $date, LeaveRequestStatus::PendingHr);

    // Dulu kedua halaman ini memakai aturan berbeda: yang satu menyembunyikan
    // pengajuan yang belum diputuskan, yang lain menampilkannya.
    $this->actingAs($user)->get(route('my-roster.index', ['month' => now()->format('Y-m')]))
        ->assertOk()->assertSee('Diajukan');

    $this->actingAs($user)->get(route('my-schedule.index'))
        ->assertOk()->assertSee('diajukan', false);
});
