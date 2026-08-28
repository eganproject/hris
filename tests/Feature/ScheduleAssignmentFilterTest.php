<?php

use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobPosition;
use App\Models\ScheduleAssignment;
use App\Models\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Tab "Penugasan Pola" pada Jadwal Kerja harus menuruti penyaring yang sama dengan
 * tab Roster. Formulir penyaringnya dipakai bersama kedua tab, jadi penyaring yang
 * hanya berlaku di salah satunya membuat kotak isian tetap terisi sementara tabelnya
 * diam-diam menampilkan semua orang.
 *
 * Memakai scheduleManager() dan everydayPattern() dari SchedulingTest.
 */
function assignmentFixture(): array
{
    $shift = Shift::query()->create([
        'code' => 'PG', 'name' => 'Pagi', 'start_time' => '07:00', 'end_time' => '15:00', 'is_active' => true,
    ]);
    $pattern = everydayPattern($shift->id);

    $gudang = Branch::query()->create(['code' => 'WHS', 'name' => 'Gudang', 'is_active' => true]);
    $kantor = Branch::query()->create(['code' => 'OFC', 'name' => 'Kantor', 'is_active' => true]);

    $produksi = Department::query()->create(['code' => 'PRD', 'name' => 'Produksi', 'is_active' => true]);
    $keuangan = Department::query()->create(['code' => 'FIN', 'name' => 'Keuangan', 'is_active' => true]);

    $staff = JobPosition::query()->create(['code' => 'STF', 'name' => 'Staff', 'level' => 'Staff', 'is_active' => true]);
    $spv = JobPosition::query()->create(['code' => 'SPV', 'name' => 'Supervisor', 'level' => 'Supervisor', 'is_active' => true]);

    $make = function (string $name, Branch $branch, Department $department, JobPosition $position) use ($pattern) {
        $employee = Employee::query()->create([
            'full_name' => $name,
            'employment_status' => 'active',
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'job_position_id' => $position->id,
        ]);

        ScheduleAssignment::query()->create([
            'employee_id' => $employee->id,
            'schedule_pattern_id' => $pattern->id,
            'start_date' => '2026-09-01',
            'end_date' => null,
        ]);

        return $employee;
    };

    return [
        'target' => $make('Bagas Produksi', $gudang, $produksi, $staff),
        'lainDivisi' => $make('Fitri Keuangan', $gudang, $keuangan, $staff),
        'lainJabatan' => $make('Eko Penyelia', $gudang, $produksi, $spv),
        'lainLokasi' => $make('Rina Kantor', $kantor, $produksi, $staff),
        'produksi' => $produksi,
        'staff' => $staff,
        'gudang' => $gudang,
    ];
}

test('the assignments tab honours the division filter', function () {
    $user = scheduleManager();
    $f = assignmentFixture();

    $this->actingAs($user)
        ->get("/attendance/schedules?month=2026-09&tab=assignments&department_id={$f['produksi']->id}")
        ->assertOk()
        ->assertSee('Bagas Produksi')
        ->assertDontSee('Fitri Keuangan');
});

test('the assignments tab honours the job position filter', function () {
    $user = scheduleManager();
    $f = assignmentFixture();

    $this->actingAs($user)
        ->get("/attendance/schedules?month=2026-09&tab=assignments&job_position_id={$f['staff']->id}")
        ->assertOk()
        ->assertSee('Bagas Produksi')
        ->assertDontSee('Eko Penyelia');
});

test('the assignments tab honours the search box', function () {
    $user = scheduleManager();
    assignmentFixture();

    $this->actingAs($user)
        ->get('/attendance/schedules?month=2026-09&tab=assignments&search=Bagas')
        ->assertOk()
        ->assertSee('Bagas Produksi')
        ->assertDontSee('Fitri Keuangan');
});

test('the assignments tab still honours the location filter', function () {
    $user = scheduleManager();
    $f = assignmentFixture();

    $this->actingAs($user)
        ->get("/attendance/schedules?month=2026-09&tab=assignments&branch_id={$f['gudang']->id}")
        ->assertOk()
        ->assertSee('Bagas Produksi')
        ->assertDontSee('Rina Kantor');
});

test('the tab badge counts the filtered assignments, not all of them', function () {
    $user = scheduleManager();
    $f = assignmentFixture();

    // Badge pada tab ikut menyempit, kalau tidak angkanya menjanjikan baris yang
    // tidak akan muncul saat tabnya dibuka. Divisi Produksi berisi tiga orang —
    // Bagas, Eko, dan Rina — sedangkan Fitri ada di Keuangan.
    $this->actingAs($user)
        ->get("/attendance/schedules?month=2026-09&tab=assignments&department_id={$f['produksi']->id}")
        ->assertOk()
        ->assertViewHas('assignmentCount', 3)
        ->assertDontSee('Fitri Keuangan');

    // Dua penyaring sekaligus menyempit lebih jauh: Produksi + Staff.
    $this->actingAs($user)
        ->get("/attendance/schedules?month=2026-09&tab=assignments&department_id={$f['produksi']->id}&job_position_id={$f['staff']->id}")
        ->assertOk()
        ->assertViewHas('assignmentCount', 2);
});
