<?php

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\OvertimeApproval;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/** Pengguna dengan izin tertentu dan cakupan data penuh. */
function viewerWith(string ...$permissions): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach ([...$permissions, User::SCOPE_BYPASS_ATTENDANCE] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::factory()->create();
    $user->givePermissionTo([...$permissions, User::SCOPE_BYPASS_ATTENDANCE]);

    return $user;
}

function employeesWithAttendance(int $count, string $date, string $status = 'present', string $prefix = 'Karyawan'): void
{
    for ($i = 1; $i <= $count; $i++) {
        $employee = Employee::query()->create([
            'full_name' => sprintf('%s %03d', $prefix, $i), 'employment_status' => 'active',
        ]);

        Attendance::query()->create([
            'employee_id' => $employee->id, 'work_date' => $date, 'status' => $status,
        ]);
    }
}

test('the daily board paginates instead of loading every employee', function () {
    $date = '2026-06-15';
    $user = viewerWith('attendance-daily.view');
    employeesWithAttendance(30, $date);

    $response = $this->actingAs($user)->get("/attendance/daily?date={$date}&per_page=25")->assertOk();
    $employees = $response->viewData('employees');

    expect($employees)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($employees->total())->toBe(30)
        ->and($employees->count())->toBe(25);
});

test('the daily summary counts the whole population, not the visible page', function () {
    $date = '2026-06-15';
    $user = viewerWith('attendance-daily.view');
    employeesWithAttendance(30, $date, 'present');
    employeesWithAttendance(5, $date, 'absent', 'Alfa');

    $summary = $this->actingAs($user)
        ->get("/attendance/daily?date={$date}&per_page=25")
        ->assertOk()
        ->viewData('summary');

    // 35 karyawan, halaman hanya memuat 25 — ringkasannya tetap utuh.
    expect($summary['present'])->toBe(30)
        ->and($summary['absent'])->toBe(5);
});

test('the daily status filter runs in SQL so pages are never half empty', function () {
    $date = '2026-06-15';
    $user = viewerWith('attendance-daily.view');
    employeesWithAttendance(30, $date, 'present');
    employeesWithAttendance(4, $date, 'absent', 'Alfa');

    $employees = $this->actingAs($user)
        ->get("/attendance/daily?date={$date}&status=absent&per_page=25")
        ->assertOk()
        ->viewData('employees');

    // Sebelumnya penyaringan dilakukan setelah data ditarik, sehingga halaman 1 bisa
    // berisi sedikit baris sementara sisanya tersembunyi di halaman lain.
    expect($employees->total())->toBe(4)
        ->and($employees->count())->toBe(4);
});

test('the daily summary ignores the status filter but honours the search', function () {
    $date = '2026-06-15';
    $user = viewerWith('attendance-daily.view');
    employeesWithAttendance(6, $date, 'present');
    employeesWithAttendance(2, $date, 'absent', 'Alfa');

    // Filter status menyempitkan tabel saja; ringkasannya tetap memperlihatkan
    // komposisi seluruh populasi supaya HR tahu konteksnya.
    $summary = $this->actingAs($user)
        ->get("/attendance/daily?date={$date}&status=absent")
        ->assertOk()
        ->viewData('summary');

    expect($summary['present'])->toBe(6)->and($summary['absent'])->toBe(2);

    // Pencarian mempersempit populasinya, jadi ringkasan ikut menyempit.
    $narrowed = $this->actingAs($user)
        ->get("/attendance/daily?date={$date}&search=Karyawan 001")
        ->assertOk()
        ->viewData('summary');

    expect($narrowed->sum())->toBe(1);
});

test('the leave balance matrix paginates its employees', function () {
    $user = viewerWith('leave-balances.view');

    for ($i = 1; $i <= 30; $i++) {
        Employee::query()->create(['full_name' => sprintf('Pegawai %03d', $i), 'employment_status' => 'active']);
    }

    $employees = $this->actingAs($user)->get('/attendance/leave-balances?per_page=25')->assertOk()->viewData('employees');

    expect($employees)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($employees->total())->toBe(30)
        ->and($employees->count())->toBe(25);
});

test('the overtime list paginates while its totals stay across the whole month', function () {
    $user = viewerWith('overtime.view');

    for ($i = 1; $i <= 30; $i++) {
        $employee = Employee::query()->create(['full_name' => sprintf('Lembur %03d', $i), 'employment_status' => 'active']);

        OvertimeApproval::query()->create([
            'employee_id' => $employee->id,
            'work_date' => '2026-06-10',
            'status' => OvertimeApproval::STATUS_APPROVED,
            'approved_minutes' => 60,
        ]);
    }

    $response = $this->actingAs($user)->get('/attendance/overtime?month=2026-06&per_page=25')->assertOk();

    expect($response->viewData('requests')->total())->toBe(30)
        ->and($response->viewData('requests')->count())->toBe(25)
        // Total menit dihitung query terpisah, jadi tidak ikut terpotong paginasi.
        ->and($response->viewData('approvedMinutes'))->toBe(1800);
});

test('the map is deliberately not paginated because a partial map is a wrong map', function () {
    $date = '2026-06-15';
    $user = viewerWith('attendance-daily.view');

    // Peta dibatasi satu tanggal dan hanya pekerja jarak jauh, jadi jumlahnya kecil.
    // Memaginasinya akan menyembunyikan sebagian titik tanpa petunjuk apa pun.
    employeesWithAttendance(3, $date, AttendanceStatus::Wfh->value);

    $points = $this->actingAs($user)->get("/attendance/map?date={$date}")->assertOk()->viewData('points');

    expect($points)->toBeInstanceOf(Illuminate\Support\Collection::class);
});
