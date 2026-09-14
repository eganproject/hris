<?php

use App\Enums\LeaveRequestStatus;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Support\LeaveReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Detail Cuti: riwayat pemakaian cuti satu karyawan beserta sisa kuotanya.
 */
function leaveDetailViewer(): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach (['reports.leave.view', User::SCOPE_BYPASS_ATTENDANCE] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::factory()->create();
    $user->givePermissionTo(['reports.leave.view', User::SCOPE_BYPASS_ATTENDANCE]);
    $user->forceFill(['bypass_team_scope' => true])->save();

    return $user;
}

function leaveDetailRequest(Employee $employee, LeaveType $type, string $start, string $end, LeaveRequestStatus $status): LeaveRequest
{
    return LeaveRequest::query()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $type->id,
        'start_date' => $start,
        'end_date' => $end,
        'reason' => 'Uji.',
        'status' => $status->value,
    ]);
}

/** Karyawan dengan cuti tahunan (kuota 12, ditimpa jadi 10) dan izin sakit tanpa kuota. */
function leaveDetailFixture(): array
{
    $annual = LeaveType::query()->create([
        'code' => 'CT', 'name' => 'Cuti Tahunan', 'attendance_status' => 'leave',
        'is_paid' => true, 'counts_against_balance' => true, 'default_quota_days' => 12, 'is_active' => true,
    ]);
    $sick = LeaveType::query()->create([
        'code' => 'SK', 'name' => 'Sakit', 'attendance_status' => 'sick',
        'is_paid' => true, 'counts_against_balance' => false, 'is_active' => true,
    ]);

    $employee = Employee::query()->create(['full_name' => 'Andi Cuti', 'employment_status' => 'active']);
    LeaveBalance::query()->create(['employee_id' => $employee->id, 'leave_type_id' => $annual->id, 'year' => 2026, 'quota_days' => 10]);

    $requests = [
        'march' => leaveDetailRequest($employee, $annual, '2026-03-10', '2026-03-11', LeaveRequestStatus::Approved),   // 2 hari
        'may' => leaveDetailRequest($employee, $annual, '2026-05-04', '2026-05-06', LeaveRequestStatus::Approved),     // 3 hari
        'pending' => leaveDetailRequest($employee, $annual, '2026-08-17', '2026-08-17', LeaveRequestStatus::PendingSupervisor),
        'rejected' => leaveDetailRequest($employee, $annual, '2026-07-01', '2026-07-04', LeaveRequestStatus::Rejected),
        'sick' => leaveDetailRequest($employee, $sick, '2026-06-15', '2026-06-15', LeaveRequestStatus::Approved),
        'lastYear' => leaveDetailRequest($employee, $annual, '2025-12-01', '2025-12-05', LeaveRequestStatus::Approved),
    ];

    return [$employee, $annual, $sick, $requests];
}

test('the balance per leave type counts only approved days, with pending shown apart', function () {
    [$employee, $annual, $sick] = leaveDetailFixture();

    $balances = collect(app(LeaveReport::class)->employeeHistory($employee, 2026)['balances'])
        ->keyBy(fn (array $balance) => $balance['type']->id);

    expect($balances[$annual->id])->toMatchArray(['quota' => 10, 'used' => 5, 'pending' => 1, 'remaining' => 5])
        ->and($balances[$sick->id])->toMatchArray(['quota' => null, 'used' => 1, 'pending' => 0, 'remaining' => null]);
});

test('each approved request carries the remaining quota after it, in date order', function () {
    [$employee, , , $requests] = leaveDetailFixture();

    $remainingAfter = app(LeaveReport::class)->employeeHistory($employee, 2026)['remainingAfter'];

    expect($remainingAfter[$requests['march']->id])->toBe(8)
        ->and($remainingAfter[$requests['may']->id])->toBe(5)
        // Tidak mengurangi kuota: menunggu, ditolak, jenis tanpa kuota, tahun lain.
        ->and($remainingAfter)->not->toHaveKeys([
            $requests['pending']->id, $requests['rejected']->id, $requests['sick']->id, $requests['lastYear']->id,
        ]);
});

test('the detail page matches the remaining quota on the leave recap', function () {
    [$employee, $annual] = leaveDetailFixture();
    $user = leaveDetailViewer();

    $recapRow = collect($this->actingAs($user)->get('/reports/leave?year=2026')->assertOk()->viewData('rows'))
        ->firstWhere('employee.id', $employee->id);

    $detail = $this->actingAs($user)->get("/reports/leave/{$employee->id}?year=2026")
        ->assertOk()
        ->assertSee('Sisa Kuota Cuti')
        ->assertSee('Riwayat Pengajuan Cuti')
        ->assertSee('menunggu 1 hari');

    $balance = collect($detail->viewData('balances'))->firstWhere('type.id', $annual->id);

    expect($balance['remaining'])->toBe($recapRow['cells'][$annual->id]['remaining'])
        ->and($detail->viewData('approvedDays'))->toBe(6);
});

test('a quota type with no request yet still shows its full balance', function () {
    [$employee] = leaveDetailFixture();

    $special = LeaveType::query()->create([
        'code' => 'CB', 'name' => 'Cuti Besar', 'attendance_status' => 'leave',
        'is_paid' => true, 'counts_against_balance' => true, 'default_quota_days' => 30, 'is_active' => true,
    ]);

    $balance = collect(app(LeaveReport::class)->employeeHistory($employee, 2026)['balances'])
        ->firstWhere('type.id', $special->id);

    expect($balance)->toMatchArray(['quota' => 30, 'used' => 0, 'remaining' => 30]);
});
