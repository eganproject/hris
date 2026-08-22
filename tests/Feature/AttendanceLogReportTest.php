<?php

use App\Enums\AttendanceStatus;
use App\Exports\AttendanceLogExport;
use App\Exports\AttendanceLogSummary;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function logViewer(): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('reports.log.view', 'web');
    Permission::findOrCreate('reports.log.export', 'web');
    Permission::findOrCreate(User::SCOPE_BYPASS_ATTENDANCE, 'web');

    $user = User::factory()->create();
    $user->givePermissionTo(['reports.log.view', 'reports.log.export', User::SCOPE_BYPASS_ATTENDANCE]);

    return $user;
}

/** Satu karyawan dengan absensi harian berurutan mulai 2026-06-01. */
function logEmployee(string $name, int $days, string $status = 'present', array $extra = []): Employee
{
    $employee = Employee::query()->create([
        'full_name' => $name, 'employment_status' => 'active',
    ]);

    for ($i = 0; $i < $days; $i++) {
        Attendance::query()->create([
            'employee_id' => $employee->id,
            'work_date' => '2026-06-'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
            'status' => $status,
            'clock_in' => '2026-06-'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT).' 08:00:00',
            'clock_out' => '2026-06-'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT).' 17:00:00',
            'work_minutes' => 480,
            ...$extra,
        ]);
    }

    return $employee;
}

test('the log is paginated server-side instead of loading the whole month', function () {
    $user = logViewer();
    logEmployee('Ana', 20);
    logEmployee('Budi', 20);

    $response = $this->actingAs($user)->get('/reports/attendance-log?month=2026-06&per_page=25')->assertOk();

    $rows = $response->viewData('rows');

    expect($rows)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($rows->total())->toBe(40)      // seluruh periode
        ->and($rows->count())->toBe(25)      // hanya satu halaman yang dimuat
        ->and($rows->perPage())->toBe(25);
});

test('rows are grouped per employee then ordered by date', function () {
    $user = logViewer();
    logEmployee('Zainal', 3);
    logEmployee('Andi', 3);

    $rows = $this->actingAs($user)->get('/reports/attendance-log?month=2026-06')->assertOk()->viewData('rows');

    $names = collect($rows->items())->map(fn (Attendance $r) => $r->employee->full_name)->all();
    $andiDates = collect($rows->items())
        ->filter(fn (Attendance $r) => $r->employee->full_name === 'Andi')
        ->map(fn (Attendance $r) => $r->work_date->toDateString())
        ->values()->all();

    // Andi lebih dulu (alfabetis) dan seluruh barisnya berdempet, bukan berselang-seling.
    expect($names)->toBe(['Andi', 'Andi', 'Andi', 'Zainal', 'Zainal', 'Zainal'])
        ->and($andiDates)->toBe(['2026-06-01', '2026-06-02', '2026-06-03']);
});

test('the summary counts the whole period, not just the visible page', function () {
    $user = logViewer();
    logEmployee('Ana', 20);
    logEmployee('Budi', 5, 'absent');

    $summary = $this->actingAs($user)
        ->get('/reports/attendance-log?month=2026-06&per_page=25')
        ->assertOk()
        ->viewData('summary');

    expect($summary['present'])->toBe(20)
        ->and($summary['absent'])->toBe(5);
});

test('the search and status filters narrow the query at the database', function () {
    $user = logViewer();
    logEmployee('Ana Lestari', 4);
    logEmployee('Budi Santoso', 4, 'absent');

    $bySearch = $this->actingAs($user)->get('/reports/attendance-log?month=2026-06&search=Lestari')->assertOk()->viewData('rows');
    $byStatus = $this->actingAs($user)->get('/reports/attendance-log?month=2026-06&status=absent')->assertOk()->viewData('rows');

    expect($bySearch->total())->toBe(4)
        ->and($bySearch->first()->employee->full_name)->toBe('Ana Lestari')
        ->and($byStatus->total())->toBe(4)
        ->and($byStatus->first()->employee->full_name)->toBe('Budi Santoso');
});

test('the excel export carries five sheets, summary first', function () {
    $rows = collect();
    $export = new AttendanceLogExport($rows, ['periode' => 'Juni 2026']);

    $titles = array_map(fn ($sheet) => $sheet->title(), $export->sheets());

    expect($titles)->toBe(['Ringkasan Karyawan', 'Log Harian', 'Rekap Harian', 'Info Shift', 'Keterangan']);
});

test('the per-employee summary tallies statuses, hours and attendance rate', function () {
    // 10 hari kerja: 6 hadir, 2 telat, 1 alfa, 1 libur (tidak dihitung hari kerja).
    $employee = Employee::query()->create(['full_name' => 'Rina', 'employment_status' => 'active']);

    $plan = array_merge(
        array_fill(0, 6, ['present', 480, 0]),
        array_fill(0, 2, ['late', 450, 15]),
        [['absent', 0, 0]],
        [['holiday', 0, 0]],
    );

    foreach ($plan as $i => [$status, $minutes, $late]) {
        Attendance::query()->create([
            'employee_id' => $employee->id,
            'work_date' => '2026-06-'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
            'status' => $status, 'work_minutes' => $minutes, 'late_minutes' => $late,
        ]);
    }

    $row = (new AttendanceLogSummary(Attendance::with('employee')->get()))->perEmployee()->first();

    expect($row['hari_tercatat'])->toBe(10)
        ->and($row['hari_kerja'])->toBe(9)        // libur dikeluarkan
        ->and($row['hadir'])->toBe(8)             // hadir + terlambat
        ->and($row['alfa'])->toBe(1)
        ->and($row['jam_kerja'])->toBe(63.0)      // (6*480 + 2*450) / 60
        ->and($row['telat_menit'])->toBe(30)
        ->and($row['telat_menit_rata2'])->toBe(15.0) // dibagi 2 hari telat, bukan 9
        ->and($row['persen_kehadiran'])->toBe(88.9);  // 8 / 9
});

test('the daily recap groups the same numbers by date', function () {
    logEmployee('Ana', 2);
    logEmployee('Budi', 2, 'absent');

    $recap = (new AttendanceLogSummary(Attendance::with('employee')->get()))->perDate();

    expect($recap)->toHaveCount(2)
        ->and($recap[0]['tanggal']->toDateString())->toBe('2026-06-01')
        ->and($recap[0]['karyawan'])->toBe(2)
        ->and($recap[0]['hadir'])->toBe(1)
        ->and($recap[0]['alfa'])->toBe(1);
});

test('the log report stays behind its permission', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('reports.log.view', 'web');

    $this->actingAs(User::factory()->create())->get('/reports/attendance-log')->assertForbidden();
});

test('"hadir" means the same thing everywhere it is counted', function () {
    // Satu definisi di enum; kalau ada laporan yang menyimpang, tes ini yang jatuh.
    expect(AttendanceStatus::workedValues())
        ->toBe(['present', 'late', 'early_leave', 'wfh', 'business_trip'])
        ->and(AttendanceStatus::Wfh->isWorked())->toBeTrue()
        ->and(AttendanceStatus::BusinessTrip->isWorked())->toBeTrue()
        ->and(AttendanceStatus::Sick->isWorked())->toBeFalse()
        ->and(AttendanceStatus::DayOff->isWorked())->toBeFalse();
});

test('the export route really produces a workbook', function () {
    $user = logViewer();
    logEmployee('Ana', 3);
    logEmployee('Budi', 2, 'absent');

    $response = $this->actingAs($user)->get('/reports/attendance-log/export?month=2026-06');

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('log-absensi-2026-06.xlsx');

    // Benar-benar dirender PhpSpreadsheet, bukan sekadar respons kosong.
    expect(strlen($response->streamedContent() ?: $response->getContent()))->toBeGreaterThan(5000);
});

test('the shift sheet lists only shifts present in the exported data', function () {
    $pagi = App\Models\Shift::query()->create([
        'code' => 'P', 'name' => 'Pagi', 'start_time' => '08:00', 'end_time' => '17:00',
        'break_minutes' => 60, 'late_tolerance_minutes' => 10, 'early_leave_tolerance_minutes' => 5,
        'overtime_starts_after_minutes' => 30, 'overtime_min_minutes' => 30, 'is_active' => true,
    ]);
    // Shift malam yang menyeberang tengah malam — durasinya harus tetap benar.
    $malam = App\Models\Shift::query()->create([
        'code' => 'M', 'name' => 'Malam', 'start_time' => '22:00', 'end_time' => '06:00',
        'crosses_midnight' => true, 'break_minutes' => 30, 'is_active' => true,
    ]);
    // Sengaja tidak dipakai baris mana pun: tidak boleh muncul di sheet.
    App\Models\Shift::query()->create([
        'code' => 'X', 'name' => 'Tak Terpakai', 'start_time' => '09:00', 'end_time' => '18:00', 'is_active' => true,
    ]);

    $employee = logEmployee('Ana', 2, 'present', ['shift_id' => $pagi->id]);
    logEmployee('Budi', 1, 'present', ['shift_id' => $malam->id]);
    // Hari libur tanpa shift sama sekali.
    Attendance::query()->create([
        'employee_id' => $employee->id, 'work_date' => '2026-06-07', 'status' => 'day_off',
    ]);

    $sheet = new App\Exports\AttendanceLogShiftSheet(Attendance::with('shift')->get());
    $rows = $sheet->collection();

    expect($rows->pluck('kode')->all())->toBe(['M', 'P', '(tanpa shift)']);

    $pagiRow = $rows->firstWhere('kode', 'P');
    expect($pagiRow['mulai'])->toBe('08:00')
        ->and($pagiRow['selesai'])->toBe('17:00')
        ->and($pagiRow['durasi_jam'])->toBe(9.0)
        ->and($pagiRow['efektif_jam'])->toBe(8.0)   // 9 jam - 1 jam istirahat
        ->and($pagiRow['toleransi_telat'])->toBe(10)
        ->and($pagiRow['hari'])->toBe(2)
        ->and($pagiRow['karyawan'])->toBe(1);

    // Shift malam: 22:00-06:00 = 8 jam, bukan negatif.
    $malamRow = $rows->firstWhere('kode', 'M');
    expect($malamRow['durasi_jam'])->toBe(8.0)
        ->and($malamRow['lintas_malam'])->toBe('Ya')
        ->and($malamRow['efektif_jam'])->toBe(7.5);

    // Baris penutup berdamai dengan total log harian.
    expect($rows->sum('hari'))->toBe(Attendance::query()->count());
});
