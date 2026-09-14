<?php

use App\Enums\LeaveRequestStatus;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Detail karyawan menampilkan sisa cuti tahun berjalan, dengan angka yang sama
 * seperti Rekap Cuti dan Detail Cuti.
 */
function leaveSummaryViewer(array $extra = []): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $permissions = ['employees.view', 'employees.view.all', ...$extra];

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::factory()->create();
    $user->givePermissionTo($permissions);
    $user->forceFill(['bypass_team_scope' => true])->save();

    return $user;
}

/** Cuti tahunan kuota 12 (ditimpa jadi 10): 3 hari disetujui, 1 hari menunggu. */
function leaveSummaryEmployee(): array
{
    $year = (int) now()->year;

    $annual = LeaveType::query()->create([
        'code' => 'CT', 'name' => 'Cuti Tahunan', 'attendance_status' => 'leave',
        'is_paid' => true, 'counts_against_balance' => true, 'default_quota_days' => 12, 'is_active' => true,
    ]);

    $employee = Employee::query()->create(['full_name' => 'Rina Saldo', 'employment_status' => 'active']);
    LeaveBalance::query()->create(['employee_id' => $employee->id, 'leave_type_id' => $annual->id, 'year' => $year, 'quota_days' => 10]);

    foreach ([
        ["{$year}-01-05", "{$year}-01-07", LeaveRequestStatus::Approved],
        ["{$year}-01-20", "{$year}-01-20", LeaveRequestStatus::PendingHr],
        ["{$year}-01-26", "{$year}-01-29", LeaveRequestStatus::Rejected],
    ] as [$start, $end, $status]) {
        LeaveRequest::query()->create([
            'employee_id' => $employee->id, 'leave_type_id' => $annual->id,
            'start_date' => $start, 'end_date' => $end, 'reason' => 'Uji.', 'status' => $status->value,
        ]);
    }

    return [$employee, $annual];
}

test('the employee detail shows the remaining leave for this year', function () {
    [$employee, $annual] = leaveSummaryEmployee();

    $response = $this->actingAs(leaveSummaryViewer())->get("/employees/{$employee->id}")
        ->assertOk()
        ->assertSee('Sisa Cuti '.now()->year)
        ->assertSee('Cuti Tahunan')
        ->assertSee('menunggu 1 hari');

    $balance = collect($response->viewData('leaveSummary'))->firstWhere('type.id', $annual->id);

    expect($balance)->toMatchArray(['quota' => 10, 'used' => 3, 'pending' => 1, 'remaining' => 7]);
});

test('the history link only shows for someone who may open the leave detail', function () {
    [$employee] = leaveSummaryEmployee();
    $detailUrl = route('reports.leave.detail', ['employee' => $employee->id, 'year' => now()->year]);

    // Tanpa izin Rekap Cuti: saldonya tetap terlihat, tautannya tidak — kalau
    // ditampilkan, ia hanya berujung 403.
    $this->actingAs(leaveSummaryViewer())->get("/employees/{$employee->id}")
        ->assertOk()
        ->assertSee('Sisa Cuti')
        ->assertDontSee($detailUrl, false);

    $withReport = leaveSummaryViewer(['reports.leave.view', User::SCOPE_BYPASS_ATTENDANCE]);

    $this->actingAs($withReport)->get("/employees/{$employee->id}")
        ->assertOk()
        ->assertSee($detailUrl, false);

    $this->actingAs($withReport)->get($detailUrl)->assertOk();
});

test('an employee without any quota type says so instead of an empty card', function () {
    $employee = Employee::query()->create(['full_name' => 'Tanpa Jenis Cuti', 'employment_status' => 'active']);

    $this->actingAs(leaveSummaryViewer())->get("/employees/{$employee->id}")
        ->assertOk()
        ->assertSee('Belum ada jenis cuti yang memakai kuota.');
});
