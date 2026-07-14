<?php

use App\Enums\LeaveRequestStatus;
use App\Models\AttendanceCorrection;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobPosition;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Dua lokasi, masing-masing satu karyawan aktif, plus satu pengajuan cuti yang
 * menunggu HR di tiap lokasi.
 *
 * @return array<string, mixed>
 */
function dashboardFixture(): array
{
    $surabaya = Branch::query()->create(['code' => 'SBY', 'name' => 'Surabaya Office', 'is_active' => true]);
    $jakarta = Branch::query()->create(['code' => 'JKT', 'name' => 'Jakarta Office', 'is_active' => true]);
    $department = Department::query()->create(['code' => 'OPS', 'name' => 'Operasional', 'is_active' => true]);
    $position = JobPosition::query()->create(['code' => 'STF', 'name' => 'Staf', 'is_active' => true]);

    $type = LeaveType::query()->create([
        'code' => 'CT', 'name' => 'Cuti Tahunan', 'attendance_status' => 'leave',
        'is_paid' => true, 'counts_against_balance' => true, 'default_quota_days' => 12, 'is_active' => true,
    ]);

    $make = function (Branch $branch, string $name) use ($department, $position, $type) {
        $employee = Employee::query()->create([
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'job_position_id' => $position->id,
            'full_name' => $name,
            'join_date' => now()->subYear()->toDateString(),
            'employment_status' => 'active',
        ]);

        LeaveRequest::query()->create([
            'employee_id' => $employee->id, 'leave_type_id' => $type->id,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(),
            'reason' => 'Keperluan keluarga.', 'status' => LeaveRequestStatus::PendingHr->value,
        ]);

        return $employee;
    };

    return [
        'surabaya' => $surabaya,
        'jakarta' => $jakarta,
        'sby' => $make($surabaya, 'Karyawan Surabaya'),
        'jkt' => $make($jakarta, 'Karyawan Jakarta'),
    ];
}

/** @param  list<string>  $permissions */
function dashboardUser(array $permissions): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach ([...$permissions, User::SCOPE_BYPASS_EMPLOYEES, User::SCOPE_BYPASS_ATTENDANCE] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

test('dashboard numbers only count the employees within the user scope', function () {
    $fixture = dashboardFixture();

    // HR pusat: melihat semua.
    $hrPusat = dashboardUser(['dashboard.view', 'employees.view', User::SCOPE_BYPASS_EMPLOYEES]);

    $this->actingAs($hrPusat)->get('/dashboard')
        ->assertOk()
        ->assertSee('Karyawan Aktif')
        ->assertSeeInOrder(['Karyawan Aktif', '2']);

    // HR cabang Surabaya: hanya karyawan Surabaya yang dihitung.
    $hrCabang = dashboardUser(['dashboard.view', 'employees.view']);
    $hrCabang->accessBranches()->sync([$fixture['surabaya']->id]);

    $this->actingAs($hrCabang)->get('/dashboard')
        ->assertOk()
        ->assertSeeInOrder(['Karyawan Aktif', '1']);
});

test('the to-do list only counts requests the user may decide, within their scope', function () {
    $fixture = dashboardFixture();

    // HR cabang Surabaya yang boleh memutuskan cuti: 1 antrean (bukan 2).
    $hrCabang = dashboardUser(['dashboard.view', 'leave.view', 'leave.update']);
    $hrCabang->accessBranches()->sync([$fixture['surabaya']->id]);

    $this->actingAs($hrCabang)->get('/dashboard')
        ->assertOk()
        ->assertSeeInOrder(['Cuti/izin menunggu keputusan HR', '1']);

    // HR pusat melihat kedua-duanya.
    $hrPusat = dashboardUser(['dashboard.view', 'leave.view', 'leave.update', User::SCOPE_BYPASS_ATTENDANCE]);

    $this->actingAs($hrPusat)->get('/dashboard')
        ->assertOk()
        ->assertSeeInOrder(['Cuti/izin menunggu keputusan HR', '2']);
});

test('a queue the user may not decide is not shown at all', function () {
    $fixture = dashboardFixture();

    AttendanceCorrection::query()->create([
        'employee_id' => $fixture['sby']->id,
        'work_date' => now()->subDay()->toDateString(),
        'requested_clock_in' => '08:00',
        'reason' => 'Lupa absen.',
        'status' => AttendanceCorrection::STATUS_PENDING,
    ]);

    // Hanya boleh memutuskan koreksi — antrean cuti tidak ditampilkan sama sekali.
    $user = dashboardUser(['dashboard.view', 'corrections.view', 'corrections.update', User::SCOPE_BYPASS_ATTENDANCE]);

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertSee('Koreksi absensi menunggu keputusan')
        ->assertDontSee('Cuti/izin menunggu keputusan HR');
});

test('an employee without HR permissions sees only their own summary', function () {
    dashboardFixture();

    $user = dashboardUser(['dashboard.view', 'my-leave.view']);
    Employee::query()->create([
        'user_id' => $user->id, 'full_name' => 'Staf Biasa', 'employment_status' => 'active',
        'join_date' => now()->subYear()->toDateString(),
    ]);

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertSee('Pengajuan Saya Berjalan')
        ->assertSee('Jadwal 7 Hari ke Depan')
        ->assertDontSee('Karyawan Aktif')
        ->assertDontSee('Perlu Tindakan');
});
