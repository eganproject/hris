<?php

use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\JobPosition;
use App\Models\SchedulePattern;
use App\Models\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Penyaring "Jadwal Bulan Ini" dan "Jam Kerja" pada daftar karyawan.
 * Memakai employeeManager() dan hrMasterData() dari EmployeeManagementDataTest.
 */

/**
 * Data induk untuk berkas test ini. Sengaja dibaca ulang dari database tiap
 * pemanggilan — RefreshDatabase mengosongkan tabelnya antar-test, jadi menyimpannya
 * di variabel static akan menyisakan id yang barisnya sudah tidak ada.
 *
 * @return array{branch: Branch, department: Department, position: JobPosition}
 */
function filterMaster(): array
{
    $branch = Branch::query()->firstWhere('code', 'SBY-OFC-01');

    if (! $branch) {
        return hrMasterData();
    }

    return [
        'branch' => $branch,
        'department' => Department::query()->firstWhere('code', 'AKR'),
        'position' => JobPosition::query()->firstWhere('code', 'SPV'),
    ];
}

function filterEmployee(string $name, array $overrides = []): Employee
{
    $master = filterMaster();

    return Employee::query()->create([
        'branch_id' => $master['branch']->id,
        'department_id' => $master['department']->id,
        'job_position_id' => $master['position']->id,
        'full_name' => $name,
        'join_date' => now()->subYear()->toDateString(),
        'employment_status' => 'active',
        'follows_office_hours' => false,
        ...$overrides,
    ]);
}

function giveScheduleOn(Employee $employee, string $date): void
{
    $shift = Shift::query()->firstOrCreate(
        ['code' => 'REG'],
        ['name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00', 'break_minutes' => 60, 'is_active' => true],
    );

    EmployeeSchedule::query()->create([
        'employee_id' => $employee->id,
        'work_date' => $date,
        'shift_id' => $shift->id,
        'is_day_off' => false,
        'source' => 'generated',
    ]);
}

test('the filter lists only employees with no roster row this month', function () {
    $user = employeeManager();

    $terjadwal = filterEmployee('Sudah Terjadwal');
    giveScheduleOn($terjadwal, now()->startOfMonth()->addDays(3)->toDateString());

    $kosong = filterEmployee('Belum Terjadwal');

    $this->actingAs($user)->get('/employees?schedule=none')
        ->assertOk()
        ->assertSee('Belum Terjadwal')
        ->assertDontSee('Sudah Terjadwal');
});

test('a roster row in another month does not count as scheduled', function () {
    $user = employeeManager();

    // Jadwalnya ada, tapi bulan lalu — bulan berjalan tetap kosong.
    $employee = filterEmployee('Jadwal Bulan Lalu');
    giveScheduleOn($employee, now()->subMonthNoOverflow()->startOfMonth()->addDay()->toDateString());

    $this->actingAs($user)->get('/employees?schedule=none')
        ->assertOk()
        ->assertSee('Jadwal Bulan Lalu');
});

test('employees on office hours are never reported as unscheduled', function () {
    $user = employeeManager();

    // Jadwalnya diturunkan dari pola, jadi memang tidak punya baris roster —
    // tanpa pengecualian ini mereka akan salah tampil sebagai belum terjadwal.
    $pattern = SchedulePattern::query()->create([
        'code' => 'OFFICE-5D', 'name' => 'Kantor Senin-Jumat', 'type' => 'fixed_weekly',
        'cycle_length' => 7, 'is_active' => true, 'is_office_pattern' => true,
    ]);

    filterEmployee('Karyawan Kantoran', [
        'follows_office_hours' => true,
        'office_pattern_id' => $pattern->id,
    ]);
    filterEmployee('Pekerja Shift');

    $this->actingAs($user)->get('/employees?schedule=none')
        ->assertOk()
        ->assertSee('Pekerja Shift')
        ->assertDontSee('Karyawan Kantoran');
});

test('the office-hours filter selects each group', function () {
    $user = employeeManager();

    filterEmployee('Orang Kantor', ['follows_office_hours' => true]);
    filterEmployee('Orang Shift');

    $this->actingAs($user)->get('/employees?office_hours=yes')
        ->assertOk()
        ->assertSee('Orang Kantor')
        ->assertDontSee('Orang Shift');

    $this->actingAs($user)->get('/employees?office_hours=no')
        ->assertOk()
        ->assertSee('Orang Shift')
        ->assertDontSee('Orang Kantor');
});

test('the summary cards count the filtered set, not everyone', function () {
    $user = employeeManager();

    $terjadwal = filterEmployee('Punya Jadwal');
    giveScheduleOn($terjadwal, now()->startOfMonth()->addDays(2)->toDateString());
    filterEmployee('Tanpa Jadwal Satu');
    filterEmployee('Tanpa Jadwal Dua');

    $this->actingAs($user)->get('/employees?schedule=none')
        ->assertOk()
        ->assertViewHas('summary', fn (array $summary) => $summary['total'] === 2 && $summary['active'] === 2);
});

test('both filters show as removable chips and open the filter panel', function () {
    $user = employeeManager();
    filterEmployee('Seseorang');

    $this->actingAs($user)->get('/employees?schedule=none&office_hours=no')
        ->assertOk()
        ->assertSee('Belum ada jadwal')
        ->assertSee('Dijadwalkan / shift');
});
